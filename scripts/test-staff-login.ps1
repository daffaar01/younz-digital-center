<#
.SYNOPSIS
    Uji login pegawai per faktor untuk mengetahui bagian mana yang salah.

.DESCRIPTION
    Endpoint login sengaja mengembalikan satu pesan sama untuk semua kegagalan.
    Skrip ini memeriksa email, password, dan kode akses secara terpisah supaya
    jelas faktor mana yang tidak cocok. Tidak ada nilai plaintext yang ditulis
    ke disk atau log.

.EXAMPLE
    & "C:\Daffa\Younz\YounzDigitalCenter\scripts\test-staff-login.ps1"
#>

[CmdletBinding()]
param(
    [string]$ProjectRoot = (Split-Path -Parent $PSScriptRoot),
    [string]$PhpPath = 'C:\xampp\php84-sampitmart\php.exe',
    [string]$AppEnv = 'xampp'
)

$ErrorActionPreference = 'Stop'

$probePath = Join-Path $ProjectRoot 'storage\app\staff-login-probe.php'

$probe = @'
<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Support\StaffAccessGate;

$root = dirname(__DIR__, 2);

require $root.'/vendor/autoload.php';

$app = require_once $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$payload = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);

$email = strtolower(trim((string) ($payload['email'] ?? '')));
$password = (string) ($payload['password'] ?? '');
$accessCode = (string) ($payload['access_code'] ?? '');

$gate = $app->make(StaffAccessGate::class);
$user = User::query()->where('email', $email)->first();

$result = [
    'environment' => app()->environment(),
    'database' => config('database.default'),
    'email_found' => (bool) $user,
    'is_staff' => $user ? $user->isStaff() : null,
    'is_active' => $user ? (bool) $user->is_active : null,
    'role' => $user ? (string) $user->role->value : null,
    'password_match' => $user ? Hash::check($password, $user->password) : null,
    'access_code_required' => $gate->isConfigured(),
    'access_code_match' => $gate->isConfigured() ? $gate->verify($accessCode) : null,
    'two_factor_confirmed' => $user ? $user->hasConfirmedTwoFactor() : null,
    'known_staff_emails' => User::query()
        ->whereIn('role', ['owner', 'admin', 'cashier'])
        ->orderBy('id')
        ->pluck('email')
        ->all(),
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
'@

Set-Content -LiteralPath $probePath -Value $probe -Encoding UTF8

try {
    Write-Host ''
    Write-Host 'Uji Login Pegawai - Younz Digital Center' -ForegroundColor Cyan
    Write-Host '---------------------------------------'
    Write-Host 'Input password dan kode akses tidak ditampilkan di layar.'
    Write-Host ''

    $email = Read-Host -Prompt 'Email'
    $passwordSecure = Read-Host -Prompt 'Password' -AsSecureString
    $accessSecure = Read-Host -Prompt 'Kode akses internal' -AsSecureString

    $password = [Runtime.InteropServices.Marshal]::PtrToStringBSTR(
        [Runtime.InteropServices.Marshal]::SecureStringToBSTR($passwordSecure)
    )
    $accessCode = [Runtime.InteropServices.Marshal]::PtrToStringBSTR(
        [Runtime.InteropServices.Marshal]::SecureStringToBSTR($accessSecure)
    )

    $json = @{
        email       = $email
        password    = $password
        access_code = $accessCode
    } | ConvertTo-Json -Compress

    $previousEnv = $env:APP_ENV
    $env:APP_ENV = $AppEnv

    Push-Location $ProjectRoot
    try {
        $output = $json | & $PhpPath $probePath
    }
    finally {
        Pop-Location
        if ($null -eq $previousEnv) { Remove-Item Env:APP_ENV -ErrorAction SilentlyContinue }
        else { $env:APP_ENV = $previousEnv }
    }

    $data = $output | ConvertFrom-Json

    Write-Host ''
    Write-Host ('Environment : {0} / {1}' -f $data.environment, $data.database) -ForegroundColor Gray
    Write-Host ''

    function Show-Check {
        param([string]$Label, $Value)

        if ($null -eq $Value) {
            Write-Host ('  [--] {0,-24} tidak diperiksa' -f $Label) -ForegroundColor DarkGray
            return
        }

        if ($Value -eq $true) {
            Write-Host ('  [OK] {0,-24} cocok' -f $Label) -ForegroundColor Green
            return
        }

        Write-Host ('  [!!] {0,-24} TIDAK cocok' -f $Label) -ForegroundColor Red
    }

    Show-Check 'Email terdaftar'    $data.email_found
    Show-Check 'Akun pegawai'       $data.is_staff
    Show-Check 'Akun aktif'         $data.is_active
    Show-Check 'Password'           $data.password_match
    Show-Check 'Kode akses'         $data.access_code_match
    Show-Check '2FA sudah aktif'    $data.two_factor_confirmed

    Write-Host ''
    Write-Host 'Email pegawai yang terdaftar:' -ForegroundColor Cyan
    foreach ($known in $data.known_staff_emails) {
        Write-Host ('  - {0}' -f $known)
    }

    Write-Host ''

    $blockers = @()
    if ($data.email_found -ne $true) { $blockers += 'email tidak terdaftar' }
    if ($data.is_active -eq $false) { $blockers += 'akun tidak aktif' }
    if ($data.password_match -eq $false) { $blockers += 'password salah' }
    if ($data.access_code_match -eq $false) { $blockers += 'kode akses salah' }

    if ($blockers.Count -eq 0) {
        Write-Host 'Semua faktor cocok. Login seharusnya berhasil.' -ForegroundColor Green

        if ($data.two_factor_confirmed -ne $true) {
            Write-Host 'Anda akan diarahkan ke layar aktivasi authenticator dulu.' -ForegroundColor Yellow
        }
    }
    else {
        Write-Host ('Penyebab kegagalan: {0}' -f ($blockers -join ', ')) -ForegroundColor Red
    }

    Write-Host ''
}
finally {
    $password = $null
    $accessCode = $null
    $json = $null
    Remove-Item -LiteralPath $probePath -Force -ErrorAction SilentlyContinue
    [GC]::Collect()
}
