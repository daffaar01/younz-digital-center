<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\UsesAgentGateway;
use Stringable;

class OrderIntakeAgent
{
    use UsesAgentGateway;

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
            Ubah permintaan layanan Younz Digital Center menjadi data terstruktur.
            Jangan menghitung atau mengarang harga. Tandai informasi yang belum pasti.
            Jenis layanan valid: print, fotokopi, scan, ketik, desain, website, aplikasi.
            Konversi jumlah berbahasa Indonesia menjadi integer, misalnya "dua rangkap" menjadi 2.
            Gunakan color untuk warna, black_white untuk hitam putih, duplex untuk bolak-balik,
            dan simplex untuk satu sisi. Isi null hanya bila informasi benar-benar tidak disebutkan.
            PROMPT;
    }

    public function structuredOutput(): bool
    {
        return true;
    }

    /** @return array<string, array<string, mixed>> */
    public function schema(): array
    {
        return [
            'service' => ['type' => 'string'],
            'paper_size' => ['type' => 'string'],
            'color_mode' => ['type' => 'string'],
            'sides' => ['type' => 'string'],
            'copies' => ['type' => 'integer', 'minimum' => 1],
            'finishing' => ['type' => 'string'],
            'pickup_time' => ['type' => 'string'],
            'notes' => ['type' => 'string'],
            'uncertain_fields' => ['type' => 'array', 'items' => ['type' => 'string']],
        ];
    }
}
