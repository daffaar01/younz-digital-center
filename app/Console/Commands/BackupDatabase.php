<?php

namespace App\Console\Commands;

use App\Integrations\GoogleDrive\GoogleDriveBackupClient;
use App\Support\MariaDbBackup;
use App\Support\PrivateBackupDirectory;
use Aws\S3\S3Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'younz:backup {--keep= : Jumlah hari retensi backup}';

    protected $description = 'Create, encrypt, verify, and rotate a private database backup';

    public function handle(GoogleDriveBackupClient $googleDrive): int
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $directory = in_array($driver, ['mysql', 'mariadb'], true) ? 'backups/mariadb' : 'backups';
        $stamp = now()->format('Ymd-His').(in_array($driver, ['mysql', 'mariadb'], true) ? '-'.bin2hex(random_bytes(4)) : '');

        try {
            $contents = match ($driver) {
                'sqlite' => $this->sqliteSnapshot($connection->getDatabaseName()),
                'pgsql' => $this->postgresSnapshot(),
                'mysql', 'mariadb' => app(MariaDbBackup::class)->snapshot($connection),
                default => null,
            };
        } catch (Throwable) {
            $this->error('Pembuatan snapshot database gagal. Periksa binary, izin, dan profil koneksi backup.');

            return self::FAILURE;
        }

        if (! is_string($contents) || $contents === '') {
            $this->error("Backup gagal atau database driver tidak didukung: {$driver}");

            return self::FAILURE;
        }

        $extension = match ($driver) {
            'sqlite' => 'sqlite.enc',
            'mysql', 'mariadb' => 'mariadb.enc',
            default => 'dump.enc',
        };
        $target = "{$directory}/younz-{$stamp}.{$extension}";
        $encrypted = Crypt::encryptString($contents);
        $retentionDays = max(1, (int) ($this->option('keep') ?: config('filesystems.backup_retention_days', 30)));
        $diskNames = [(string) config('filesystems.backup', 'local')];

        foreach ($diskNames as $diskName) {
            try {
                $disk = Storage::disk($diskName);
                if (in_array($driver, ['mysql', 'mariadb'], true)) {
                    if (config('filesystems.disks.'.$diskName.'.driver') !== 'local' || $diskName === 'public') {
                        throw new RuntimeException('MariaDB requires a private local primary backup disk.');
                    }
                    PrivateBackupDirectory::secure($disk->path($directory));
                }
                $disk->makeDirectory($directory);

                if (! $disk->put($target, $encrypted)) {
                    $this->error("Backup terenkripsi tidak dapat ditulis ke disk [{$diskName}].");

                    return self::FAILURE;
                }

                $verified = Crypt::decryptString($disk->get($target));
                if (! hash_equals(hash('sha256', $contents), hash('sha256', $verified))) {
                    $disk->delete($target);
                    $this->error("Verifikasi backup pada disk [{$diskName}] gagal; file tidak valid telah dihapus.");

                    return self::FAILURE;
                }

                $this->rotate($diskName, $directory, $retentionDays);
            } catch (Throwable $exception) {
                report($exception);
                $this->error("Backup pada disk [{$diskName}] gagal.");

                return self::FAILURE;
            }
        }

        if ((bool) config('services.backup_r2.enabled')) {
            try {
                $this->writeR2($target, $encrypted, $contents, $retentionDays);
                $diskNames[] = 'r2';
            } catch (Throwable $exception) {
                report($exception);
                $this->error('Backup off-site R2 gagal. Salinan lokal tetap tersimpan.');

                return self::FAILURE;
            }
        }

        if ((bool) config('services.google_drive_backup.enabled')) {
            try {
                $uploaded = $googleDrive->upload($target, $encrypted, $retentionDays);
                $diskNames[] = 'Google Drive';
                $this->line("Google Drive terverifikasi: {$uploaded['name']} ({$uploaded['size']} byte).");
            } catch (Throwable $exception) {
                report($exception);
                $this->error('Backup off-site Google Drive gagal. Salinan lokal tetap tersimpan.');

                return self::FAILURE;
            }
        }

        $this->info('Backup terenkripsi dan terverifikasi pada disk ['.implode(', ', $diskNames)."]: {$target}");

        return self::SUCCESS;
    }

    private function sqliteSnapshot(string $source): ?string
    {
        if ($source === ':memory:' || ! File::isFile($source)) {
            return null;
        }

        $temporaryDirectory = storage_path('app/private/tmp');
        File::ensureDirectoryExists($temporaryDirectory);
        $temporary = $temporaryDirectory.'/backup-'.Str::uuid().'.sqlite';

        try {
            DB::statement("VACUUM INTO '".str_replace("'", "''", $temporary)."'");

            return File::get($temporary);
        } finally {
            File::delete($temporary);
        }
    }

    private function postgresSnapshot(): ?string
    {
        $config = config('database.connections.'.config('database.default'));
        $temporaryDirectory = storage_path('app/private/tmp');
        File::ensureDirectoryExists($temporaryDirectory);
        $temporary = $temporaryDirectory.'/backup-'.Str::uuid().'.dump';
        $process = new Process([
            (string) config('database.pg_dump_binary', 'pg_dump'),
            '--format=custom',
            '--no-owner',
            '--host='.(string) $config['host'],
            '--port='.(string) $config['port'],
            '--username='.(string) $config['username'],
            '--file='.$temporary,
            (string) $config['database'],
        ], base_path(), ['PGPASSWORD' => (string) $config['password']]);
        $process->setTimeout(600);

        try {
            $process->run();

            return $process->isSuccessful() && File::isFile($temporary) ? File::get($temporary) : null;
        } finally {
            File::delete($temporary);
        }
    }

    private function rotate(string $diskName, string $directory, int $days): void
    {
        $cutoff = now()->subDays($days)->getTimestamp();
        $disk = Storage::disk($diskName);
        foreach ($disk->files($directory) as $file) {
            $managed = preg_match('/\Ayounz-[0-9]{8}-[0-9]{6}(?:-[a-f0-9]{8})?\.(?:mariadb|sqlite|dump)\.enc\z/', basename($file));
            if ($managed && $disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
            }
        }
    }

    private function writeR2(string $target, string $encrypted, string $contents, int $retentionDays): void
    {
        $config = (array) config('services.backup_r2');
        foreach (['key', 'secret', 'bucket', 'endpoint'] as $required) {
            if (! filled($config[$required] ?? null)) {
                throw new RuntimeException("Konfigurasi R2 [{$required}] belum diisi.");
            }
        }

        $client = new S3Client([
            'version' => 'latest',
            'region' => (string) ($config['region'] ?? 'auto'),
            'endpoint' => rtrim((string) $config['endpoint'], '/'),
            'credentials' => [
                'key' => (string) $config['key'],
                'secret' => (string) $config['secret'],
            ],
            'use_path_style_endpoint' => false,
        ]);
        $bucket = (string) $config['bucket'];

        $client->putObject([
            'Bucket' => $bucket,
            'Key' => $target,
            'Body' => $encrypted,
            'ContentType' => 'application/octet-stream',
        ]);

        $stored = $client->getObject(['Bucket' => $bucket, 'Key' => $target]);
        $verified = Crypt::decryptString((string) $stored['Body']);
        if (! hash_equals(hash('sha256', $contents), hash('sha256', $verified))) {
            $client->deleteObject(['Bucket' => $bucket, 'Key' => $target]);

            throw new RuntimeException('Verifikasi backup R2 gagal.');
        }

        $cutoff = now()->subDays($retentionDays)->getTimestamp();
        $continuationToken = null;
        do {
            $parameters = ['Bucket' => $bucket, 'Prefix' => 'backups/'];
            if (is_string($continuationToken)) {
                $parameters['ContinuationToken'] = $continuationToken;
            }

            $objects = $client->listObjectsV2($parameters);
            foreach ($objects['Contents'] ?? [] as $object) {
                if ($object['LastModified']->getTimestamp() < $cutoff) {
                    $client->deleteObject([
                        'Bucket' => $bucket,
                        'Key' => (string) $object['Key'],
                    ]);
                }
            }
            $continuationToken = $objects['NextContinuationToken'] ?? null;
        } while (is_string($continuationToken));
    }
}
