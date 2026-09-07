<?php

namespace App\Support;

use Illuminate\Database\Connection;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\Support\InMemoryConnectionFactory;

class MariaDbBackup
{
    public function snapshot(Connection $connection): string
    {
        $this->assertLiveExecution();
        $database = $connection->getDatabaseName();
        self::identifier($database);
        $tables = $connection->select('SELECT TABLE_NAME AS name, ENGINE AS engine, TABLE_TYPE AS type FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?', [$database]);
        foreach ($tables as $table) {
            self::identifier($table->name);
            if ($table->type !== 'BASE TABLE' || $table->engine !== 'InnoDB') {
                throw new RuntimeException('Consistent backup currently requires InnoDB base tables only.');
            }
        }

        $sql = $this->runClient($connection->getConfig(), 'dump', [
            '--single-transaction', '--quick', '--skip-lock-tables', '--skip-add-locks',
            '--skip-comments', '--hex-blob', '--complete-insert', '--skip-extended-insert',
            '--routines', '--events', '--triggers', '--default-character-set=utf8mb4', $database,
        ]);

        $payload = self::pack($sql, $database);
        $manifest = self::unpack($payload);
        $actualNames = array_keys($manifest['tables']);
        $sourceNames = array_map(fn ($table) => $table->name, $tables);
        sort($actualNames);
        sort($sourceNames);
        if ($actualNames !== $sourceNames) {
            throw new RuntimeException('Dump table manifest is incomplete.');
        }

        return $payload;
    }

    public static function identifier(string $name): string
    {
        if (! preg_match('/\A[a-zA-Z0-9_]{1,64}\z/', $name)) {
            throw new RuntimeException('Unsupported database or table identifier.');
        }

        return '`'.$name.'`';
    }

    /** One INSERT per row makes the expected counts derive from the dump snapshot itself. */
    public static function pack(string $sql, string $database): string
    {
        if ($sql === '' || strlen($sql) > 64 * 1024 * 1024) {
            throw new RuntimeException('Empty dump or dump larger than the 64 MiB safety limit.');
        }
        preg_match_all('/^CREATE TABLE `([a-zA-Z0-9_]+)` /m', $sql, $matches);
        $tables = array_fill_keys($matches[1], 0);
        if ($tables === []) {
            throw new RuntimeException('Dump does not contain base tables.');
        }
        preg_match_all('/^INSERT INTO `([a-zA-Z0-9_]+)` /m', $sql, $inserts);
        foreach ($inserts[1] as $table) {
            if (! array_key_exists($table, $tables)) {
                throw new RuntimeException('Dump contains an unexpected insert target.');
            }
            $tables[$table]++;
        }
        ksort($tables);

        return json_encode([
            'format' => 'younz-mariadb-v1', 'database' => $database,
            'created_at' => gmdate(DATE_ATOM), 'tables' => $tables,
            'sha256' => hash('sha256', $sql), 'sql_gzip' => base64_encode(gzencode($sql, 6)),
        ], JSON_THROW_ON_ERROR);
    }

    /** @return array{database: string, tables: array<string, int>, sql: string} */
    public static function unpack(string $payload): array
    {
        try {
            $data = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
            $compressed = base64_decode($data['sql_gzip'] ?? '', true);
            $sql = is_string($compressed) ? @gzdecode($compressed, 64 * 1024 * 1024 + 1) : false;
            if (($data['format'] ?? null) !== 'younz-mariadb-v1'
                || ! is_string($sql) || $sql === '' || strlen($sql) > 64 * 1024 * 1024
                || ! hash_equals((string) ($data['sha256'] ?? ''), hash('sha256', $sql))
                || empty($data['tables']) || ! is_array($data['tables'])
            ) {
                throw new RuntimeException;
            }
            self::identifier($data['database']);
            foreach ($data['tables'] as $table => $count) {
                self::identifier($table);
                if (! is_int($count) || $count < 0) {
                    throw new RuntimeException;
                }
            }

            return ['database' => $data['database'], 'tables' => $data['tables'], 'sql' => $sql];
        } catch (\Throwable) {
            throw new RuntimeException('Invalid MariaDB backup payload.');
        }
    }

    /** @param array<string, mixed> $config */
    public function restore(array $config, string $database, string $sql): void
    {
        $this->assertLiveExecution();
        self::identifier($database);
        $this->runClient($config, 'client', [
            '--batch', '--binary-mode', '--local-infile=0', '--default-character-set=utf8mb4',
            '--database='.$database,
        ], $sql);
    }

    /** @param array<string, mixed> $config */
    public static function optionFile(array $config): string
    {
        $quote = static function (string $value): string {
            if (str_contains($value, "\0")) {
                throw new RuntimeException('Invalid database connection value.');
            }

            return '"'.strtr($value, ['\\' => '\\\\', '"' => '\\"', "\n" => '\\n', "\r" => '\\r', "\t" => '\\t']).'"';
        };

        return "[client]\nprotocol=TCP\n"
            .'host='.$quote((string) ($config['host'] ?? '127.0.0.1'))."\n"
            .'port='.(int) ($config['port'] ?? 3306)."\n"
            .'user='.$quote((string) ($config['username'] ?? ''))."\n"
            .'password='.$quote((string) ($config['password'] ?? ''))."\n";
    }

    /** @param array<string, mixed> $config @param list<string> $arguments */
    protected function runClient(array $config, string $kind, array $arguments, ?string $input = null): string
    {
        $this->assertLiveExecution();
        // Refuse silently downgrading a socket/TLS connection to this local TCP profile.
        if (! in_array($config['host'] ?? '', ['127.0.0.1', 'localhost'], true)
            || ! empty($config['unix_socket']) || ! empty($config['options'])
        ) {
            throw new RuntimeException('MariaDB backup profile requires local TCP without custom PDO options.');
        }
        $directory = storage_path('app/private/backup-tmp/'.bin2hex(random_bytes(12)));
        PrivateBackupDirectory::secure($directory);
        $options = $directory.'/client.cnf';
        try {
            if (file_put_contents($options, self::optionFile($config)) === false) {
                throw new RuntimeException('Cannot write private database options.');
            }
            chmod($options, 0600);
            $process = new Process([$this->binary($kind), '--defaults-file='.$options, ...$arguments], base_path(), ['MYSQL_PWD' => false]);
            $process->setTimeout(600);
            if ($input !== null) {
                $process->setInput($input);
            }
            $process->run();
            if (! $process->isSuccessful()) {
                // Native SQL errors can include passwords or customer data: do not log them.
                throw new RuntimeException('MariaDB '.$kind.' failed; exit code '.(int) $process->getExitCode().'.');
            }

            return $process->getOutput();
        } finally {
            if (is_file($options)) {
                unlink($options);
            }
            rmdir($directory);
        }
    }

    public function binary(string $kind): string
    {
        $configured = config('database.mariadb_'.$kind.'_binary');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }
        $names = $kind === 'dump' ? ['mariadb-dump', 'mysqldump'] : ['mariadb', 'mysql'];
        foreach ($names as $name) {
            $bundled = dirname(PHP_BINARY, 2).'/mysql/bin/'.$name.'.exe';
            if (PHP_OS_FAMILY === 'Windows' && is_file($bundled)) {
                return $bundled;
            }
            if ($found = (new ExecutableFinder)->find($name)) {
                return $found;
            }
        }

        throw new RuntimeException('MariaDB '.$kind.' binary is not available.');
    }

    private function assertLiveExecution(): void
    {
        if (app()->environment('testing') || app('db.factory') instanceof InMemoryConnectionFactory) {
            throw new RuntimeException('Native database backup/restore is disabled in PHPUnit.');
        }
    }
}
