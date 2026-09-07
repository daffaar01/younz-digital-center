<?php

/*
|--------------------------------------------------------------------------
| Staff Access Code Hasher
|--------------------------------------------------------------------------
|
| Reads a plaintext access code from STDIN and prints the hash produced by
| the application's configured hash driver. The plaintext never touches the
| filesystem, the shell history, or the application logs.
|
| Usage:
|   echo <code> | php scripts/hash-staff-access-code.php
|
*/

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

$root = dirname(__DIR__);

require $root.'/vendor/autoload.php';

$app = require_once $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$plain = rtrim((string) fgets(STDIN), "\r\n");

if ($plain === '') {
    fwrite(STDERR, 'Kode akses tidak boleh kosong.'.PHP_EOL);
    exit(1);
}

if (mb_strlen($plain) < 12) {
    fwrite(STDERR, 'Kode akses minimal 12 karakter.'.PHP_EOL);
    exit(1);
}

if (mb_strlen($plain) > 128) {
    fwrite(STDERR, 'Kode akses maksimal 128 karakter.'.PHP_EOL);
    exit(1);
}

$hash = Hash::make($plain);

if (! Hash::check($plain, $hash)) {
    fwrite(STDERR, 'Verifikasi hash gagal. Periksa konfigurasi hashing.'.PHP_EOL);
    exit(1);
}

echo $hash;
