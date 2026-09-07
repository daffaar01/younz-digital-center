<#
.SYNOPSIS
    Reset kode akses internal (STAFF_ACCESS_CODE_HASH) Younz Digital Center.

.DESCRIPTION
    Meminta kode akses baru secara aman lewat prompt tersembunyi, membuat hash
    argon2id memakai konfigurasi hashing aplikasi, mem-backup .env, menulis
    hash baru, lalu merestart service Laravel agar konfigurasi terbaca.

    Kode plaintext tidak pernah ditulis ke disk, log, atau riwayat perintah.

.EXAMPLE
    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\reset-staff-access-code.ps1
#>

[CmdletBinding()]
param(
    [string]$ProjectRoot = (Split-Path -Parent $PSScriptRoot),
    [string]$PhpPath = 'C:\Program Files\YounzDigitalCenter\FrankenPHP\php.exe',
    [string[]]$RestartServices = @('YounzOrigin', 'YounzQueue', 'YounzScheduler')
)

$ErrorActionPreference = 'Stop'

$envPath = Join-Path $ProjectRoot '.env'
$hasherPath = Join-Path $ProjectRoot 'scripts\hash-staff-access-code.php'

foreach ($required in @($envPath, $hasherPath, $PhpPath)) {
    if (-not (Test-Path -LiteralPath $required)) {
        throw "Tidak ditemukan: $required"
    }
}

$identity = [Security.Principal.WindowsIdentity]::GetCurrent()
$isAdmin = (New-Object Security.Principal.WindowsPrincipal($identity)).IsInRole(
    [Security.Principal.WindowsBuiltInRole]::Administrator
)

if (-not $isAdmin) {
    Write-Warning 'Jalankan sebagai Administrator agar service dapat direstart otomatis.'
}

Write-Host ''
Write-Host 'Reset Kode Akses Internal - Younz Digital Center' -ForegroundColor Cyan
Write-Host '-----------------------------------------------'
Write-Host 'Kode minimal 12 karakter. Input tidak ditampilkan di layar.'
Write-Host ''

$first = Read-Host -Prompt 'Kode akses baru' -AsSecureString
$second = Read-Host -Prompt 'Ulangi kode akses' -AsSecureString

$firstPlain = [Runtime.InteropServices.Marshal]::PtrToStringBSTR(
    [Runtime.InteropServices.Marshal]::SecureStringToBSTR($first)
)
$secondPlain = [Runtime.InteropServices.Marshal]::PtrToStringBSTR(
    [Runtime.InteropServices.Marshal]::SecureStringToBSTR($second)
)

try {
    if ($firstPlain -ne $secondPlain) {
        throw 'Kode akses tidak sama. Tidak ada perubahan yang disimpan.'
    }

    if ($firstPlain.Length -lt 12) {
        throw 'Kode akses minimal 12 karakter. Tidak ada perubahan yang disimpan.'
    }

    Write-Host ''
    Write-Host 'Membuat hash argon2id...' -ForegroundColor Cyan

    $hash = $firstPlain | & $PhpPath $hasherPath

    if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($hash)) {
        throw 'Gagal membuat hash kode akses.'
    }

    $hash = $hash.Trim()

    if (-not $hash.StartsWith('$argon2id$')) {
        throw "Format hash tidak sesuai: $($hash.Substring(0, [Math]::Min(24, $hash.Length)))"
    }
}
finally {
    $firstPlain = $null
    $secondPlain = $null
    [GC]::Collect()
}

$backupPath = Join-Path $ProjectRoot (
    '.env.backup-staff-access-{0}' -f (Get-Date -Format 'yyyyMMdd-HHmmss')
)
Copy-Item -LiteralPath $envPath -Destination $backupPath -Force
Write-Host ("Backup .env dibuat: {0}" -f (Split-Path -Leaf $backupPath)) -ForegroundColor Green

$lines = Get-Content -LiteralPath $envPath
$replacement = "STAFF_ACCESS_CODE_HASH='$hash'"
$replaced = $false

$updated = foreach ($line in $lines) {
    if ($line -match '^\s*STAFF_ACCESS_CODE_HASH\s*=') {
        $replaced = $true
        $replacement
        continue
    }

    $line
}

if (-not $replaced) {
    $updated = @($updated) + $replacement
}

Set-Content -LiteralPath $envPath -Value $updated -Encoding UTF8
Write-Host 'STAFF_ACCESS_CODE_HASH diperbarui.' -ForegroundColor Green

Write-Host ''
Write-Host 'Membersihkan cache konfigurasi...' -ForegroundColor Cyan
Push-Location $ProjectRoot
try {
    & $PhpPath artisan config:clear | Out-Null
    & $PhpPath artisan cache:clear | Out-Null
}
finally {
    Pop-Location
}

Write-Host ''
Write-Host 'Merestart service...' -ForegroundColor Cyan

foreach ($service in $RestartServices) {
    $found = Get-Service -Name $service -ErrorAction SilentlyContinue

    if (-not $found) {
        Write-Host ("  [?] {0} tidak ditemukan" -f $service) -ForegroundColor Yellow
        continue
    }

    try {
        Restart-Service -Name $service -Force -ErrorAction Stop
        Write-Host ("  [OK] {0} direstart" -f $service) -ForegroundColor Green
    }
    catch {
        Write-Host ("  [!!] {0} gagal direstart: {1}" -f $service, $_.Exception.Message) -ForegroundColor Red
    }
}

Write-Host ''
Write-Host 'Selesai. Uji login di https://admin.younzdigitalcenter.my.id/admin/masuk' -ForegroundColor Green
Write-Host 'Simpan kode akses baru di password manager. Kode lama sudah tidak berlaku.' -ForegroundColor Yellow
Write-Host ''
