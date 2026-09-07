#Requires -Version 5.1
[CmdletBinding()]
param([switch]$Rotate)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
if (-not $Rotate) { throw 'Rotasi tidak dijalankan. Tambahkan -Rotate jika memang ingin membuat sandi baru.' }
$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole(
    [Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) { throw 'Buka PowerShell dengan Run as administrator. Belum ada perubahan.' }

$repoDir = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$envFile = Join-Path $repoDir '.env'
$phpExe = (Get-Command php.exe -CommandType Application | Select-Object -First 1).Source
$originalEnv = [IO.File]::ReadAllText($envFile)
$pattern = [regex]::new('(?m)^[\t ]*YOUNZ_PPOB_WHATSAPP_CONFIRMATION_HASH[\t ]*=[^\r\n]*')
if ($pattern.Matches($originalEnv).Count -ne 1) {
    throw 'YOUNZ_PPOB_WHATSAPP_CONFIRMATION_HASH harus muncul tepat satu kali di .env.'
}

$rng = [Security.Cryptography.RandomNumberGenerator]::Create()
$randomBytes = New-Object byte[] 4
do {
    $rng.GetBytes($randomBytes)
    $randomValue = [BitConverter]::ToUInt32($randomBytes, 0)
} while ($randomValue -ge 4000000000) # Avoid modulo bias (4 * 1,000,000,000).
$code = [string](1000000000 + ($randomValue % 1000000000))
$hashScript = [IO.Path]::GetTempFileName()
$verifyScript = [IO.Path]::GetTempFileName()
$timestamp = (Get-Date -Format 'yyyyMMdd-HHmmss') + '-' + [guid]::NewGuid().ToString('N').Substring(0, 8)
$credentialDir = Join-Path $repoDir ".codex\credentials\whatsapp-confirmation-$timestamp"
$credentialFile = Join-Path $credentialDir 'sandi-konfirmasi.txt'
$changed = $false

try {
    [IO.File]::WriteAllText($hashScript, @'
<?php
$code=trim(stream_get_contents(STDIN));
if (preg_match('/^\d{10}$/D',$code)!==1) exit(20);
echo password_hash($code, PASSWORD_ARGON2ID);
'@, [Text.UTF8Encoding]::new($false))
    $hash = $code | & $phpExe $hashScript
    if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($hash) -or -not $hash.StartsWith('$argon2id$')) {
        throw 'Pembuatan hash sandi baru gagal.'
    }

    New-Item -ItemType Directory -Path $credentialDir | Out-Null
    $userSid = [Security.Principal.WindowsIdentity]::GetCurrent().User.Value
    & icacls.exe $credentialDir /inheritance:r /grant:r "*${userSid}:(OI)(CI)(F)" '*S-1-5-18:(OI)(CI)(F)' '*S-1-5-32-544:(OI)(CI)(F)' /Q | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Gagal mengamankan folder sandi baru.' }
    [IO.File]::WriteAllText($credentialFile, "Sandi konfirmasi WhatsApp baru:`r`n$code`r`n", [Text.UTF8Encoding]::new($false))

    # MatchEvaluator keeps Argon2 dollar signs literal; single quotes prevent dotenv expansion.
    $replacement = "YOUNZ_PPOB_WHATSAPP_CONFIRMATION_HASH='" + $hash.Trim() + "'"
    $newEnv = $pattern.Replace($originalEnv, [Text.RegularExpressions.MatchEvaluator]{ param($match) $replacement }, 1)
    [IO.File]::WriteAllText($envFile, $newEnv, [Text.UTF8Encoding]::new($false))
    $changed = $true
    Push-Location $repoDir
    try {
        & $phpExe artisan config:cache | Out-Null
        if ($LASTEXITCODE -ne 0) { throw 'Gagal membangun cache konfigurasi.' }

        [IO.File]::WriteAllText($verifyScript, @'
<?php
require 'vendor/autoload.php';$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$code=trim(stream_get_contents(STDIN));
$hash=(string)config('services.younz_ppob.whatsapp_confirmation_hash');
if (!Illuminate\Support\Facades\Hash::check($code,$hash)) exit(30);
foreach ((array)config('services.younz_ppob.whatsapp_operator_numbers',[]) as $phone) {
 $digits=preg_replace('/\D+/','',(string)$phone)??'';
 if(str_starts_with($digits,'00'))$digits=substr($digits,2);
 if(str_starts_with($digits,'0'))$digits='62'.substr($digits,1);
 Illuminate\Support\Facades\Cache::forget('whatsapp:ppob:confirmation:'.hash('sha256',$digits));
 Illuminate\Support\Facades\RateLimiter::clear('whatsapp:ppob:confirmation-attempts:'.hash('sha256',$digits));
}
echo 'verified';
'@, [Text.UTF8Encoding]::new($false))
        $verification = $code | & $phpExe $verifyScript
        if ($LASTEXITCODE -ne 0 -or $verification.Trim() -ne 'verified') { throw 'Verifikasi hash baru gagal.' }
        Restart-Service -Name YounzQueue
        Start-Sleep -Seconds 3
        if ((Get-Service -Name YounzQueue).Status -ne 'Running') { throw 'Queue worker gagal memuat konfigurasi baru.' }
    } finally { Pop-Location }

    Write-Host 'BERHASIL: sandi konfirmasi WhatsApp telah diganti; sesi konfirmasi lama dibatalkan.' -ForegroundColor Green
    Write-Host "Buka sandi baru di: $credentialFile"
} catch {
    $failure = $_
    if ($changed) {
        [IO.File]::WriteAllText($envFile, $originalEnv, [Text.UTF8Encoding]::new($false))
        Push-Location $repoDir
        try { & $phpExe artisan config:cache | Out-Null } finally { Pop-Location }
        Restart-Service -Name YounzQueue -ErrorAction SilentlyContinue
    }
    $resolvedCredentialDir = [IO.Path]::GetFullPath($credentialDir)
    $credentialRoot = [IO.Path]::GetFullPath((Join-Path $repoDir '.codex\credentials')) + '\'
    if (-not $resolvedCredentialDir.StartsWith($credentialRoot, [StringComparison]::OrdinalIgnoreCase)) {
        throw 'Lokasi folder sandi di luar folder credentials; pembersihan dibatalkan.'
    }
    if (Test-Path -LiteralPath $credentialFile) { Remove-Item -LiteralPath $credentialFile -Force }
    if (Test-Path -LiteralPath $resolvedCredentialDir) { Remove-Item -LiteralPath $resolvedCredentialDir -Force }
    throw $failure
} finally {
    $code = $null; $hash = $null
    $rng.Dispose()
    Remove-Item -LiteralPath $hashScript,$verifyScript -Force -ErrorAction SilentlyContinue
}
