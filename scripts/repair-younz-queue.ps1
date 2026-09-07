#Requires -Version 5.1
[CmdletBinding()]
param([switch]$CheckOnly, [switch]$Apply)

if ($CheckOnly -and $Apply) { throw 'Pilih CheckOnly atau Apply, bukan keduanya.' }
$CheckOnly = -not $Apply

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$serviceName = 'YounzQueue'
$repoDir = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$envFile = Join-Path $repoDir '.env'
$serviceXmlPath = Join-Path $env:ProgramFiles 'YounzDigitalCenter\Services\YounzQueue.xml'
$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole(
    [Security.Principal.WindowsBuiltInRole]::Administrator)

if (-not $CheckOnly -and -not $isAdmin) {
    throw 'Buka PowerShell dengan Run as administrator, lalu jalankan skrip ini lagi. Belum ada perubahan.'
}
foreach ($required in @($envFile, $serviceXmlPath, (Join-Path $repoDir 'artisan'))) {
    if (-not (Test-Path -LiteralPath $required -PathType Leaf)) { throw "File diperlukan: $required" }
}

$service = Get-CimInstance Win32_Service -Filter "Name='$serviceName'"
if (-not $service) { throw "Service $serviceName tidak ditemukan." }
$serviceXml = [xml][IO.File]::ReadAllText($serviceXmlPath)
$phpExe = [string]$serviceXml.service.executable
$arguments = [string]$serviceXml.service.arguments
$workingDirectory = [IO.Path]::GetFullPath([string]$serviceXml.service.workingdirectory)
if ($service.PathName.Trim('"') -ne (Join-Path (Split-Path $serviceXmlPath) 'YounzQueue.exe') -or
        $workingDirectory -ne $repoDir -or $arguments -notmatch '^scripts[/\\]queue-supervisor\.php run\s+"[^"]+"$' -or
        -not (Test-Path -LiteralPath $phpExe -PathType Leaf)) {
    throw 'Konfigurasi service tidak sesuai instalasi Younz yang dikenal; tidak akan diubah otomatis.'
}

$originalEnv = [IO.File]::ReadAllText($envFile)
$matches = [regex]::Matches($originalEnv, '(?m)^\s*QUEUE_CONNECTION\s*=\s*([^\r\n#]*)\s*(?:#.*)?$')
if ($matches.Count -ne 1) { throw 'QUEUE_CONNECTION harus muncul tepat satu kali di .env.' }
$currentDriver = $matches[0].Groups[1].Value.Trim().Trim('"').Trim("'")
if ($currentDriver -notin @('sync', 'database')) {
    throw "Driver antrean saat ini '$currentDriver'; skrip hanya menangani migrasi sync ke database."
}

Push-Location $repoDir
$probeFile = [IO.Path]::GetTempFileName()
try {
    [IO.File]::WriteAllText($probeFile, @'
<?php
require 'vendor/autoload.php'; $app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=Illuminate\Support\Facades\DB::connection();
foreach (['jobs','failed_jobs','job_batches'] as $table) {
    if (!Illuminate\Support\Facades\Schema::hasTable($table)) { fwrite(STDERR, "missing:$table\n"); exit(20); }
}
echo json_encode(['driver'=>config('queue.default'),'jobs'=>$db->table('jobs')->count(),'failed'=>$db->table('failed_jobs')->count()]);
'@, [Text.UTF8Encoding]::new($false))
    $queueState = & $phpExe $probeFile
    if ($LASTEXITCODE -ne 0) { throw 'Pemeriksaan tabel antrean gagal.' }
    $queueState = $queueState | ConvertFrom-Json
    Write-Host "Pemeriksaan OK: driver=$($queueState.driver), pending=$($queueState.jobs), failed=$($queueState.failed)."
    Write-Host "Service tujuan: $serviceName (database queue, Automatic)."
    if ($CheckOnly) {
        Write-Host "Mode pemeriksaan saja; tidak ada perubahan. Administrator: $isAdmin"
        return
    }

    # Never stop a running financial worker: the service wrapper may terminate PHP mid-request.
    $workers = @(Get-CimInstance Win32_Process | Where-Object {
        $_.Name -match '^php(?:-cgi)?\.exe$' -and $_.CommandLine -match 'artisan\s+queue:(?:work|listen)\b'
    })
    if ($service.State -ne 'Stopped' -or $workers.Count -gt 0 -or $queueState.jobs -gt 0) {
        throw 'Apply ditolak: service harus Stopped, tidak boleh ada worker PHP lain, dan antrean harus kosong. Tinjau/drain secara terpisah; skrip tidak menghentikan worker.'
    }
    $changedEnv = $false
    $startedService = $false
    try {
        if ($currentDriver -eq 'sync') {
            $queuePattern = [regex]::new('(?m)^\s*QUEUE_CONNECTION\s*=\s*([^\r\n#]*)\s*(?:#.*)?$')
            $newEnv = $queuePattern.Replace($originalEnv, 'QUEUE_CONNECTION=database', 1)
            [IO.File]::WriteAllText($envFile, $newEnv, [Text.UTF8Encoding]::new($false))
            $changedEnv = $true
        }
        & $phpExe artisan config:clear
        if ($LASTEXITCODE -ne 0) { throw 'Gagal membersihkan cache konfigurasi.' }
        & $phpExe artisan config:cache
        if ($LASTEXITCODE -ne 0) { throw 'Gagal membangun cache konfigurasi.' }

        $resolvedState = & $phpExe $probeFile
        $resolvedState = $resolvedState | ConvertFrom-Json
        if ($LASTEXITCODE -ne 0 -or $resolvedState.driver -ne 'database') {
            throw 'Aplikasi belum membaca queue driver database.'
        }
        Set-Service -Name $serviceName -StartupType Automatic
        $startedService = $true
        Start-Service -Name $serviceName
        Start-Sleep -Seconds 5
        if ((Get-Service -Name $serviceName).Status -ne 'Running') { throw 'Service berhenti setelah dinyalakan.' }
        $verified = Get-CimInstance Win32_Service -Filter "Name='$serviceName'"
        if ($verified.State -ne 'Running' -or $verified.StartMode -ne 'Auto' -or $verified.ProcessId -le 0) {
            throw 'Verifikasi restart/status service gagal.'
        }
        Write-Host 'BERHASIL: YounzQueue Running/Automatic, driver database, dan pemeriksaan status lulus.' -ForegroundColor Green
    } catch {
        $failure = $_
        if ($startedService) {
            Write-Warning 'Start service telah dicoba. Tidak melakukan stop atau rollback konfigurasi saat worker mungkin aktif; periksa status dan log secara manual.'
        }
        if ($changedEnv -and -not $startedService) {
            [IO.File]::WriteAllText($envFile, $originalEnv, [Text.UTF8Encoding]::new($false))
            & $phpExe artisan config:clear | Out-Null
            & $phpExe artisan config:cache | Out-Null
            Write-Warning 'Perubahan .env dibatalkan dan cache konfigurasi lama dibangun ulang.'
        }
        throw $failure
    }
} finally {
    Remove-Item -LiteralPath $probeFile -Force -ErrorAction SilentlyContinue
    Pop-Location
}
