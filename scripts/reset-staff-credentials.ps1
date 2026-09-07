<#
.SYNOPSIS
    Reset password akun pegawai dan kode akses internal sekaligus.

.DESCRIPTION
    Menjalankan tiga hal secara aman dalam satu alur:
      1. Reset password akun pegawai yang dipilih.
      2. Reset kode akses internal (STAFF_ACCESS_CODE_HASH).
      3. Opsional: reset perangkat 2FA agar bisa mendaftarkan authenticator baru.

    Nilai plaintext hanya berada di memori, tidak ditulis ke disk, log, atau
    riwayat perintah. File .env yang diubah selalu dibackup lebih dulu.

.EXAMPLE
    & "C:\Daffa\Younz\YounzDigitalCenter\scripts\reset-staff-credentials.ps1"
#>

[CmdletBinding()]
param(
    [string]$ProjectRoot = (Split-Path -Parent $PSScriptRoot),
    [string]$PhpPath = 'C:\xampp\php84-sampitmart\php.exe',
    [string]$AppEnv = 'xampp',
    [string[]]$EnvFiles = @('.env', '.env.xampp'),
    [string[]]$RestartServices = @('Apache2.4', 'YounzOrigin', 'YounzQueue', 'YounzScheduler')
)

$ErrorActionPreference = 'Stop'

function Read-Secret {
    param([string]$Prompt, [int]$MinLength)

    while ($true) {
        $first = Read-Host -Prompt $Prompt -AsSecureString
        $second = Read-Host -Prompt "Ulangi $Prompt" -AsSecureString

        $firstPlain = [Runtime.InteropServices.Marshal]::PtrToStringBSTR(
            [Runtime.InteropServices.Marshal]::SecureStringToBSTR($first)
        )
        $secondPlain = [Runtime.InteropServices.Marshal]::PtrToStringBSTR(
            [Runtime.InteropServices.Marshal]::SecureStringToBSTR($second)
        )

        if ($firstPlain -ne $secondPlain) {
            Write-Host '  Tidak sama. Coba lagi.' -ForegroundColor Red
            continue
        }

        if ($firstPlain.Length -lt $MinLength) {
            Write-Host ("  Minimal {0} karakter. Coba lagi." -f $MinLength) -ForegroundColor Red
            continue
        }

        return $firstPlain
    }
}

$identity = [Security.Principal.WindowsIdentity]::GetCurrent()
$isAdmin = (New-Object Security.Principal.WindowsPrincipal($identity)).IsInRole(
    [Security.Principal.WindowsBuiltInRole]::Administrator
)

if (-not $isAdmin) {
    Write-Warning 'Jalankan sebagai Administrator agar service dapat direstart otomatis.'
}

$workerPath = Join-Path $ProjectRoot 'storage\app\reset-credentials-worker.php'

$worker = @'
<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

$root = dirname(__DIR__, 2);

require $root.'/vendor/autoload.php';

$app = require_once $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$payload = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$action = (string) ($payload['action'] ?? '');

if ($action === 'list') {
    echo json_encode([
        'environment' => app()->environment(),
        'database' => config('database.default'),
        'users' => User::query()
            ->whereIn('role', ['owner', 'admin', 'cashier'])
            ->orderBy('id')
            ->get(['id', 'name', 'email', 'role'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => (string) $user->role->value,
                'two_factor' => $user->hasConfirmedTwoFactor(),
            ])
            ->all(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit(0);
}

if ($action === 'hash') {
    $plain = (string) ($payload['value'] ?? '');

    if ($plain === '') {
        fwrite(STDERR, 'Nilai kosong.'.PHP_EOL);
        exit(1);
    }

    $hash = Hash::make($plain);

    if (! Hash::check($plain, $hash)) {
        fwrite(STDERR, 'Verifikasi hash gagal.'.PHP_EOL);
        exit(1);
    }

    echo $hash;
    exit(0);
}

if ($action === 'password') {
    $user = User::query()->find((int) ($payload['user_id'] ?? 0));

    if (! $user) {
        fwrite(STDERR, 'Akun tidak ditemukan.'.PHP_EOL);
        exit(1);
    }

    $plain = (string) ($payload['value'] ?? '');
    $user->password = $plain;
    $user->is_active = true;

    if (! empty($payload['reset_two_factor'])) {
        $user->two_factor_secret = null;
        $user->two_factor_confirmed_at = null;
    }

    $user->save();
    $user->tokens()->delete();
    $user->refresh();

    echo json_encode([
        'email' => $user->email,
        'password_verified' => Hash::check($plain, $user->password),
        'is_active' => (bool) $user->is_active,
        'two_factor' => $user->hasConfirmedTwoFactor(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit(0);
}

fwrite(STDERR, 'Aksi tidak dikenal.'.PHP_EOL);
exit(1);
'@

Set-Content -LiteralPath $workerPath -Value $worker -Encoding UTF8

$previousEnv = $env:APP_ENV
$env:APP_ENV = $AppEnv

function Invoke-Worker {
    param([hashtable]$Payload)

    $json = $Payload | ConvertTo-Json -Compress
    Push-Location $ProjectRoot
    try {
        $output = $json | & $PhpPath $workerPath
        if ($LASTEXITCODE -ne 0) {
            throw "Worker gagal (exit $LASTEXITCODE)."
        }
        return $output
    }
    finally {
        Pop-Location
    }
}

$newPassword = $null
$newAccessCode = $null

try {
    Write-Host ''
    Write-Host 'Reset Kredensial Pegawai - Younz Digital Center' -ForegroundColor Cyan
    Write-Host '----------------------------------------------'

    $listing = Invoke-Worker -Payload @{ action = 'list' } | ConvertFrom-Json

    Write-Host ("Environment : {0} / {1}" -f $listing.environment, $listing.database) -ForegroundColor Gray
    Write-Host ''
    Write-Host 'Akun pegawai terdaftar:' -ForegroundColor Cyan

    $index = 0
    foreach ($user in $listing.users) {
        $index++
        $twoFactor = if ($user.two_factor) { '2FA aktif' } else { '2FA belum aktif' }
        Write-Host ('  {0}. {1,-32} {2,-9} {3}' -f $index, $user.email, $user.role, $twoFactor)
    }

    Write-Host ''
    $choice = Read-Host -Prompt ('Pilih akun (1-{0})' -f $listing.users.Count)
    $selectedIndex = 0

    if (-not [int]::TryParse($choice, [ref]$selectedIndex) -or
        $selectedIndex -lt 1 -or
        $selectedIndex -gt $listing.users.Count) {
        throw 'Pilihan tidak valid. Tidak ada perubahan yang disimpan.'
    }

    $selected = $listing.users[$selectedIndex - 1]

    Write-Host ''
    Write-Host ('Akun dipilih: {0}' -f $selected.email) -ForegroundColor Green
    Write-Host ''

    $resetTwoFactorAnswer = Read-Host -Prompt 'Reset juga perangkat 2FA? (y/N)'
    $resetTwoFactor = $resetTwoFactorAnswer -match '^(y|Y)'

    Write-Host ''
    Write-Host 'Password baru (minimal 12 karakter). Input tidak ditampilkan.' -ForegroundColor Cyan
    $newPassword = Read-Secret -Prompt 'Password baru' -MinLength 12

    Write-Host ''
    Write-Host 'Kode akses internal baru (minimal 12 karakter). Input tidak ditampilkan.' -ForegroundColor Cyan
    $newAccessCode = Read-Secret -Prompt 'Kode akses baru' -MinLength 12

    Write-Host ''
    Write-Host 'Menyimpan password baru...' -ForegroundColor Cyan

    $passwordResult = Invoke-Worker -Payload @{
        action           = 'password'
        user_id          = $selected.id
        value            = $newPassword
        reset_two_factor = $resetTwoFactor
    } | ConvertFrom-Json

    if ($passwordResult.password_verified -ne $true) {
        throw 'Password tersimpan tetapi verifikasi gagal. Hentikan dan periksa manual.'
    }

    Write-Host ('  [OK] Password {0} diperbarui dan terverifikasi' -f $passwordResult.email) -ForegroundColor Green

    if ($resetTwoFactor) {
        Write-Host '  [OK] Perangkat 2FA direset, daftarkan authenticator saat login' -ForegroundColor Green
    }

    Write-Host '  [OK] Sesi dan token lama dicabut' -ForegroundColor Green

    Write-Host ''
    Write-Host 'Membuat hash kode akses...' -ForegroundColor Cyan

    $accessHash = (Invoke-Worker -Payload @{ action = 'hash'; value = $newAccessCode }).Trim()

    if (-not $accessHash.StartsWith('$argon2id$')) {
        throw 'Format hash kode akses tidak sesuai.'
    }

    $replacement = "STAFF_ACCESS_CODE_HASH='$accessHash'"
    $stamp = Get-Date -Format 'yyyyMMdd-HHmmss'

    foreach ($file in $EnvFiles) {
        $envPath = Join-Path $ProjectRoot $file

        if (-not (Test-Path -LiteralPath $envPath)) {
            Write-Host ('  [--] {0} tidak ada, dilewati' -f $file) -ForegroundColor DarkGray
            continue
        }

        Copy-Item -LiteralPath $envPath -Destination ("{0}.backup-credentials-{1}" -f $envPath, $stamp) -Force

        $lines = Get-Content -LiteralPath $envPath
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
        Write-Host ('  [OK] {0} diperbarui (backup dibuat)' -f $file) -ForegroundColor Green
    }

    Write-Host ''
    Write-Host 'Membersihkan cache...' -ForegroundColor Cyan
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
            Write-Host ('  [--] {0} tidak ditemukan' -f $service) -ForegroundColor DarkGray
            continue
        }

        try {
            Restart-Service -Name $service -Force -ErrorAction Stop
            Write-Host ('  [OK] {0} direstart' -f $service) -ForegroundColor Green
        }
        catch {
            Write-Host ('  [!!] {0} gagal: {1}' -f $service, $_.Exception.Message) -ForegroundColor Red
        }
    }

    Write-Host ''
    Write-Host 'Selesai.' -ForegroundColor Green
    Write-Host ''
    Write-Host 'Login dengan:' -ForegroundColor Cyan
    Write-Host ('  URL      : https://admin.younzdigitalcenter.my.id/admin/masuk')
    Write-Host ('  Email    : {0}' -f $passwordResult.email)
    Write-Host  '  Password : password baru yang Anda set'
    Write-Host  '  Kode     : kode akses baru yang Anda set'
    Write-Host ''
    Write-Host 'Simpan keduanya di password manager sekarang.' -ForegroundColor Yellow
    Write-Host ''
}
finally {
    $newPassword = $null
    $newAccessCode = $null
    Remove-Item -LiteralPath $workerPath -Force -ErrorAction SilentlyContinue

    if ($null -eq $previousEnv) { Remove-Item Env:APP_ENV -ErrorAction SilentlyContinue }
    else { $env:APP_ENV = $previousEnv }

    [GC]::Collect()
}
