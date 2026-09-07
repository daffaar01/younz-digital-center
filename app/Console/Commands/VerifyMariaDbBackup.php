<?php

namespace App\Console\Commands;

use App\Support\MariaDbBackup;
use App\Support\PrivateBackupDirectory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PDO;
use RuntimeException;
use Tests\Support\InMemoryConnectionFactory;
use Throwable;

class VerifyMariaDbBackup extends Command
{
    protected $signature = 'younz:backup-verify {backup : Relative path on the private backup disk}';

    protected $description = 'Restore MariaDB backup into a new restricted database, verify, then remove the test database';

    public function handle(MariaDbBackup $tools): int
    {
        if (app()->environment('testing') || app('db.factory') instanceof InMemoryConnectionFactory) {
            $this->error('Live restore drill is disabled in PHPUnit.');

            return self::FAILURE;
        }
        $path = (string) $this->argument('backup');
        if (! preg_match('#\Abackups/mariadb/younz-[0-9]{8}-[0-9]{6}-[a-f0-9]{8}\.mariadb\.enc\z#', $path)) {
            $this->error('Only a managed MariaDB backup path is accepted.');

            return self::FAILURE;
        }

        $database = 'younzrestorecheck'.gmdate('YmdHis').bin2hex(random_bytes(5));
        $username = 'yzrestore'.bin2hex(random_bytes(8));
        $password = bin2hex(random_bytes(32));
        $createdDatabase = false;
        $createdUser = false;
        $admin = null;
        $target = null;
        $success = false;
        $cleanupOk = true;
        $phase = 'preflight';
        $report = ['backup' => $path, 'restore_database' => $database, 'restore_user' => $username, 'started_at' => gmdate(DATE_ATOM)];

        try {
            $source = DB::connection();
            $config = $source->getConfig();
            if (! in_array($source->getDriverName(), ['mysql', 'mariadb'], true)
                || ($config['host'] ?? '') !== '127.0.0.1' || ! empty($config['unix_socket']) || ! empty($config['options'])
            ) {
                throw new RuntimeException('Restore drill requires the local 127.0.0.1 TCP MariaDB profile.');
            }
            $backup = MariaDbBackup::unpack(Crypt::decryptString(Storage::disk(config('filesystems.backup', 'local'))->get($path)));
            if ($backup['database'] !== $source->getDatabaseName()) {
                throw new RuntimeException('Backup source does not match the configured application database.');
            }
            self::assertTarget($database, $source->getDatabaseName());
            $admin = $source->getPdo();
            $tableNames = array_keys($backup['tables']);
            $before = self::fingerprints($admin, $tableNames);
            $account = $admin->quote($username)."@'127.0.0.1'";

            // No IF NOT EXISTS: never reuse or overwrite any existing schema/account.
            $phase = 'create-isolated-database';
            $admin->exec('CREATE DATABASE '.MariaDbBackup::identifier($database).' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $createdDatabase = true;
            $phase = 'create-restricted-user';
            $admin->exec('CREATE USER '.$account.' IDENTIFIED BY '.$admin->quote($password));
            $createdUser = true;
            // Alphanumeric schema name contains no SQL GRANT wildcard characters.
            $phase = 'grant-target-only';
            $admin->exec('GRANT ALL PRIVILEGES ON '.MariaDbBackup::identifier($database).'.* TO '.$account);
            $restoreConfig = array_replace($config, ['username' => $username, 'password' => $password, 'database' => $database]);
            $phase = 'connect-restricted-user';
            $target = new PDO('mysql:host=127.0.0.1;port='.(int) $config['port'].';dbname='.$database.';charset=utf8mb4', $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            // Verify privilege isolation before sending any SQL from the backup.
            try {
                $target->query('SELECT 1 FROM '.MariaDbBackup::identifier($source->getDatabaseName()).'.`users` LIMIT 1');
                throw new RuntimeException('Restore account unexpectedly has access to the source database.');
            } catch (\PDOException $exception) {
                if (! in_array((int) ($exception->errorInfo[1] ?? 0), [1044, 1142], true)) {
                    throw new RuntimeException('Cannot verify restore account isolation.');
                }
            }
            $report['source_access_denied'] = true;
            $phase = 'import';
            $tools->restore($restoreConfig, $database, $backup['sql']);
            $phase = 'verify-tables';
            $actualTables = array_map(fn ($row) => $row[0], $target->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM));
            $expectedTables = $tableNames;
            sort($actualTables);
            sort($expectedTables);
            if ($actualTables !== $expectedTables) {
                throw new RuntimeException('Restored table list does not match the dump manifest.');
            }
            $rows = [];
            foreach ($backup['tables'] as $table => $count) {
                $rows[$table] = (int) $target->query('SELECT COUNT(*) FROM '.MariaDbBackup::identifier($table))->fetchColumn();
                if ($rows[$table] !== $count) {
                    throw new RuntimeException('Restored row counts do not match the dump manifest.');
                }
                $check = $target->query('CHECK TABLE '.MariaDbBackup::identifier($table).' QUICK')->fetchAll(PDO::FETCH_ASSOC);
                if (! array_filter($check, fn ($row) => $row['Msg_type'] === 'status' && $row['Msg_text'] === 'OK')) {
                    throw new RuntimeException('Restored table integrity check failed.');
                }
            }
            $restored = self::fingerprints($target, $tableNames);
            $after = self::fingerprints($admin, $tableNames);
            $matching = [];
            $changedSinceBackup = [];
            foreach ($tableNames as $table) {
                if ($restored[$table] === $before[$table] && $before[$table] === $after[$table]) {
                    $matching[] = $table;
                } else {
                    // A prior backup need not equal a live database that has since changed.
                    $changedSinceBackup[] = $table;
                }
            }
            $report += ['row_counts' => $rows, 'table_count' => count($rows), 'row_counts_match_dump' => true,
                'table_integrity_ok' => true, 'source_matching_tables' => $matching,
                'source_changed_during_drill' => array_values(array_filter($tableNames, fn ($table) => $before[$table] !== $after[$table])),
                'source_difference_tables' => $changedSinceBackup];
            $success = true;
        } catch (Throwable $exception) {
            $report['failure_phase'] = $phase;
            $report['failure_class'] = get_class($exception);
            $report['failure_code'] = $exception instanceof PDOException ? (int) ($exception->errorInfo[1] ?? 0) : 0;
            // Never log the exception: native SQL/import errors can contain private values.
            $this->error('Restore drill gagal. Tidak ada SQL backup yang dijalankan dengan akun utama.');
        } finally {
            $target = null;
            if ($admin instanceof PDO) {
                try {
                    if ($createdUser) {
                        $admin->exec('DROP USER '.$admin->quote($username)."@'127.0.0.1'");
                    }
                    if ($createdDatabase) {
                        self::assertTarget($database, DB::connection()->getDatabaseName());
                        $admin->exec('DROP DATABASE '.MariaDbBackup::identifier($database));
                    }
                } catch (Throwable) {
                    $cleanupOk = false;
                    $this->error('Pembersihan database/akun uji gagal; periksa laporan sebelum mengulang.');
                }
            }
        }

        $report += ['success' => $success && $cleanupOk, 'temporary_database_removed' => $createdDatabase && $cleanupOk,
            'temporary_user_removed' => $createdUser && $cleanupOk, 'finished_at' => gmdate(DATE_ATOM)];
        try {
            $reportDirectory = storage_path('app/private/backup-verification');
            PrivateBackupDirectory::secure($reportDirectory);
            $reportPath = $reportDirectory.'/'.$database.'.json';
            if (file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false) {
                throw new RuntimeException;
            }
            $this->line('Laporan: '.$reportPath);
        } catch (Throwable) {
            $this->error('Laporan verifikasi tidak dapat disimpan.');

            return self::FAILURE;
        }
        if ($success && $cleanupOk) {
            $this->info('Pemulihan terverifikasi: '.count($report['row_counts']).' tabel. Database dan akun uji telah dihapus.');
            if ($report['source_difference_tables'] !== []) {
                $this->warn('Database live berbeda dari snapshot pada '.count($report['source_difference_tables']).' tabel; lihat laporan.');
            }

            return self::SUCCESS;
        }

        return self::FAILURE;
    }

    public static function assertTarget(string $target, string $source): void
    {
        if ($target === $source || ! preg_match('/\Ayounzrestorecheck[0-9]{14}[a-f0-9]{10}\z/', $target)) {
            throw new RuntimeException('Unsafe restore database target.');
        }
    }

    /** Hash raw database values without decrypting or exposing customer records. */
    public static function fingerprints(PDO $connection, array $tables): array
    {
        $result = [];
        foreach ($tables as $table) {
            $rows = $connection->query('SELECT * FROM '.MariaDbBackup::identifier($table));
            $digests = [];
            while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                ksort($row);
                $normalized = array_map(fn ($value) => $value === null ? null : (string) $value, $row);
                $digests[] = hash('sha256', serialize($normalized));
            }
            sort($digests);
            $result[$table] = hash('sha256', implode('', $digests));
        }

        return $result;
    }
}
