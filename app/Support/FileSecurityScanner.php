<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class FileSecurityScanner
{
    /** @return array{status:string,scanned_at:Carbon|null} */
    public function scan(UploadedFile $file): array
    {
        $extension = mb_strtolower($file->getClientOriginalExtension());
        $blocked = ['exe', 'com', 'bat', 'cmd', 'ps1', 'sh', 'js', 'jar', 'msi', 'scr', 'php', 'phtml', 'svg', 'html', 'htm'];
        if (in_array($extension, $blocked, true)) {
            throw ValidationException::withMessages(['file' => 'Jenis file tersebut tidak diizinkan karena berisiko dieksekusi.']);
        }

        $binary = trim((string) config('services.file_security.antivirus_binary'));
        if ($binary === '') {
            if (config('services.file_security.antivirus_required', app()->isProduction())) {
                throw ValidationException::withMessages([
                    'file' => 'Upload sementara dinonaktifkan karena pemindai keamanan belum tersedia.',
                ]);
            }

            return ['status' => 'validated', 'scanned_at' => null];
        }

        $driver = trim((string) config('services.file_security.antivirus_driver', 'clamav'));
        $command = match ($driver) {
            'clamav' => [$binary, '--no-summary', $file->getRealPath()],
            'windows-defender' => [$binary, '-Scan', '-ScanType', '3', '-File', $file->getRealPath(), '-DisableRemediation'],
            default => throw ValidationException::withMessages([
                'file' => 'Driver pemindai keamanan tidak dikenali.',
            ]),
        };

        $process = new Process($command);
        $process->setTimeout((int) config('services.file_security.scan_timeout', 30));
        $process->run();

        $threatExitCode = $driver === 'windows-defender' ? 2 : 1;
        if ($process->getExitCode() === $threatExitCode) {
            throw ValidationException::withMessages(['file' => 'File ditolak karena terdeteksi berbahaya.']);
        }

        if (! $process->isSuccessful()) {
            throw ValidationException::withMessages(['file' => 'Pemindaian keamanan file gagal. Silakan hubungi operator.']);
        }

        return ['status' => 'clean', 'scanned_at' => now()];
    }
}
