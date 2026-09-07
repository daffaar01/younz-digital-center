<?php

namespace App\Console\Commands;

use App\Actions\Topup\SyncDigiflazzProducts as SyncProducts;
use App\Enums\DigiflazzTransactionType;
use App\Integrations\Digiflazz\DigiflazzClient;
use Illuminate\Console\Command;
use Throwable;

class SyncDigiflazzProducts extends Command
{
    protected $signature = 'digiflazz:sync-products {--type=all : Jenis katalog: all, prepaid, atau postpaid}';

    protected $description = 'Sinkronkan katalog produk prabayar dan pascabayar Digiflazz ke database lokal';

    public function handle(SyncProducts $sync, DigiflazzClient $client): int
    {
        if (! $client->isConfigured()) {
            $this->warn('Digiflazz belum diaktifkan atau kredensial belum lengkap.');

            return self::FAILURE;
        }

        $value = strtolower(trim((string) $this->option('type')));
        $value = $value === 'pasca' ? DigiflazzTransactionType::Postpaid->value : $value;

        if ($value === 'all') {
            $types = DigiflazzTransactionType::cases();
        } else {
            $type = DigiflazzTransactionType::tryFrom($value);

            if ($type === null) {
                $this->error('Jenis katalog tidak valid. Gunakan all, prepaid, atau postpaid.');

                return self::INVALID;
            }

            $types = [$type];
        }

        try {
            foreach ($types as $type) {
                $count = $sync->handle($type);
                $this->info("{$count} produk {$type->label()} Digiflazz berhasil disinkronkan.");
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Sinkronisasi gagal. Periksa konfigurasi dan log aplikasi.');

            return self::FAILURE;
        }
    }
}
