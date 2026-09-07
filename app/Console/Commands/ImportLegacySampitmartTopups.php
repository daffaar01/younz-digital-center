<?php

namespace App\Console\Commands;

use App\Services\LegacySampitmartTopupImporter;
use Illuminate\Console\Command;

class ImportLegacySampitmartTopups extends Command
{
    protected $signature = 'legacy:import-sampitmart-topups
        {--connection=legacy_sampitmart : Nama koneksi database sumber}
        {--commit : Tulis data; tanpa opsi ini hanya dry-run}';

    protected $description = 'Import riwayat transaksi digital SampitMart secara idempotent';

    public function handle(LegacySampitmartTopupImporter $importer): int
    {
        $dryRun = ! $this->option('commit');
        $summary = $importer->import((string) $this->option('connection'), $dryRun);

        $this->table(['Mode', 'Sumber', 'Layak', 'Dibuat', 'Dilewati', 'Konflik'], [[
            $dryRun ? 'DRY-RUN' : 'COMMIT',
            $summary['source'],
            $summary['eligible'],
            $summary['created'],
            $summary['skipped'],
            $summary['conflicts'],
        ]]);

        if ($dryRun) {
            $this->warn('Tidak ada data yang ditulis. Tambahkan --commit setelah backup terverifikasi.');
        }

        return self::SUCCESS;
    }
}
