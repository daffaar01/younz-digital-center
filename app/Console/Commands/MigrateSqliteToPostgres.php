<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Throwable;

class MigrateSqliteToPostgres extends Command
{
    protected $signature = 'younz:migrate-sqlite-to-postgres
        {--source=sqlite : Nama koneksi SQLite sumber}
        {--target=pgsql : Nama koneksi PostgreSQL target}
        {--force : Izinkan eksekusi pada environment production}';

    protected $description = 'Copy all application tables from SQLite to an empty PostgreSQL schema atomically';

    public function handle(): int
    {
        if ($this->laravel->environment('production') && ! $this->option('force')) {
            $this->error('Gunakan --force untuk menjalankan migrasi data pada production.');

            return self::FAILURE;
        }

        $source = DB::connection((string) $this->option('source'));
        $target = DB::connection((string) $this->option('target'));

        if ($source->getDriverName() !== 'sqlite' || $target->getDriverName() !== 'pgsql') {
            $this->error('Sumber harus SQLite dan target harus PostgreSQL.');

            return self::FAILURE;
        }

        $sourceTables = collect($source->select(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
        ))->pluck('name')->all();
        $targetTables = collect($target->select(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = current_schema() AND table_type = 'BASE TABLE' ORDER BY table_name"
        ))->pluck('table_name')->all();
        $tables = array_values(array_intersect($sourceTables, $targetTables));
        $missing = array_values(array_diff($sourceTables, $targetTables));

        if ($missing !== []) {
            $this->error('Tabel target belum tersedia: '.implode(', ', $missing));

            return self::FAILURE;
        }

        if ($tables === []) {
            $this->error('Tidak ada tabel yang dapat dimigrasikan. Jalankan migration PostgreSQL terlebih dahulu.');

            return self::FAILURE;
        }

        $orderedTables = $this->dependencyOrder($source, $tables);
        $counts = [];

        try {
            $target->transaction(function () use ($source, $target, $tables, $orderedTables, &$counts): void {
                $grammar = $target->getQueryGrammar();
                $wrappedTables = array_map(fn (string $table): string => $grammar->wrapTable($table), $tables);
                $target->statement('TRUNCATE TABLE '.implode(', ', $wrappedTables).' RESTART IDENTITY CASCADE');

                foreach ($orderedTables as $table) {
                    $booleanColumns = $this->booleanColumns($target, $table);
                    $rows = $source->table($table)->get()->map(function (object $row) use ($booleanColumns): array {
                        $values = (array) $row;

                        foreach ($booleanColumns as $column) {
                            if (array_key_exists($column, $values) && $values[$column] !== null) {
                                $values[$column] = (bool) $values[$column];
                            }
                        }

                        return $values;
                    })->all();

                    foreach (array_chunk($rows, 250) as $chunk) {
                        $target->table($table)->insert($chunk);
                    }

                    $counts[$table] = count($rows);
                }

                $this->resetSequences($target, $tables);
            }, 1);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Migrasi dibatalkan dan seluruh perubahan PostgreSQL di-rollback: '.$exception->getMessage());

            return self::FAILURE;
        }

        foreach ($tables as $table) {
            $sourceCount = (int) ($counts[$table] ?? 0);
            $targetCount = (int) $target->table($table)->count();

            if ($sourceCount !== $targetCount) {
                $this->error("Verifikasi jumlah baris gagal untuk {$table}: {$sourceCount} != {$targetCount}");

                return self::FAILURE;
            }
        }

        $this->info('Migrasi SQLite ke PostgreSQL selesai dan terverifikasi: '.array_sum($counts).' baris pada '.count($tables).' tabel.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $tables
     * @return array<int, string>
     */
    private function dependencyOrder(Connection $source, array $tables): array
    {
        $dependencies = [];

        foreach ($tables as $table) {
            $quoted = '"'.str_replace('"', '""', $table).'"';
            $dependencies[$table] = collect($source->select("PRAGMA foreign_key_list({$quoted})"))
                ->pluck('table')
                ->filter(fn (mixed $dependency): bool => is_string($dependency) && in_array($dependency, $tables, true))
                ->unique()
                ->values()
                ->all();
        }

        $ordered = [];
        $remaining = array_fill_keys($tables, true);

        while ($remaining !== []) {
            $progress = false;

            foreach (array_keys($remaining) as $table) {
                if (array_intersect($dependencies[$table], array_keys($remaining)) === []) {
                    $ordered[] = $table;
                    unset($remaining[$table]);
                    $progress = true;
                }
            }

            if (! $progress) {
                $ordered = array_merge($ordered, array_keys($remaining));
                break;
            }
        }

        return $ordered;
    }

    /** @return array<int, string> */
    private function booleanColumns(Connection $target, string $table): array
    {
        return collect($target->select(
            "SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND data_type = 'boolean'",
            [$table]
        ))->pluck('column_name')->all();
    }

    /** @param array<int, string> $tables */
    private function resetSequences(Connection $target, array $tables): void
    {
        $grammar = $target->getQueryGrammar();

        foreach ($tables as $table) {
            $columns = collect($target->select(
                "SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND (column_default LIKE 'nextval(%' OR is_identity = 'YES')",
                [$table]
            ))->pluck('column_name');

            foreach ($columns as $column) {
                $sequence = $target->scalar('SELECT pg_get_serial_sequence(?, ?)', ['public.'.$table, $column]);

                if (! is_string($sequence) || $sequence === '') {
                    continue;
                }

                $wrappedTable = $grammar->wrapTable($table);
                $wrappedColumn = $grammar->wrap($column);
                $target->statement(
                    "SELECT setval(CAST(? AS regclass), GREATEST(COALESCE(MAX({$wrappedColumn}), 0), 1), COALESCE(MAX({$wrappedColumn}), 0) > 0) FROM {$wrappedTable}",
                    [$sequence]
                );
            }
        }
    }
}
