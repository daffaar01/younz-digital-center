<?php

namespace App\Support;

use App\Enums\DigiflazzTransactionType;
use App\Models\DigiflazzProduct;

class DigiflazzCatalog
{
    public function answer(string $question, ?array &$context = null): ?string
    {
        $text = mb_strtolower(trim($question));
        $offset = 0;
        $selection = null;
        $parent = null;
        if (preg_match('/^\d{1,5}$/', $text)) {
            if (($context['kind'] ?? null) === 'products') {
                return 'Nomor ini hanya untuk melihat layanan. Untuk membeli, gunakan BELI; untuk daftar layanan baru, tulis pembayaran apa yang tersedia?';
            }
            $parent = $context;
            $selection = $context['choices'][(int) $text] ?? null;
            if (! is_array($selection)) {
                return 'Nomor layanan tidak tersedia atau sesi katalog sudah berakhir. Tulis pembayaran apa yang tersedia? untuk membuka daftar baru.';
            }
            $text = $context['question'];
        }
        if (preg_match('/^(?:masih\s+)?(?:ada\s+)?(?:(?:layanan|produk)\s+)?lain(?:nya| lagi)?[?!.]*$|^(?:lanjut|berikutnya|selanjutnya)(?:\s+(?:katalog|layanan|produk))?[?!.]*$/u', $text)) {
            if (! isset($context['question'], $context['next_offset'])) {
                return 'Sebutkan katalog yang ingin dilihat, misalnya: pembayaran apa yang tersedia?';
            }
            if (! ($context['has_more'] ?? false)) {
                return 'Semua hasil untuk katalog tersebut sudah ditampilkan. Sebutkan operator atau layanan lain untuk pencarian baru.';
            }
            $text = $context['question'];
            $offset = (int) $context['next_offset'];
            $selection = $context['selection'] ?? null;
            $parent = $context['parent'] ?? null;
        }
        if (preg_match('/\b(metode|cara)\s+(bayar|pembayaran)\b|bayar(?:nya)?\s+(pakai|pake|makek|menggunakan)|\b(qris|transfer|tunai)\b/u', $text)) {
            return null;
        }
        if (preg_match('/^(beli|proses|bayar|cek\s+tagihan)\b/u', $text)
            && ! preg_match('/\b(apa|tersedia|saja|aja|bisa)\b/u', $text)) {
            return null;
        }
        if (! preg_match('/\b(digiflazz|ppob|pulsa|pascabayar|prabayar|tagihan|token|pln|pdam|bpjs|telkomsel|indosat|xl|axis|tri|smartfren|ewallet|e-wallet)\b|pembayaran.*(apa|tersedia)|bayar\s+apa/u', $text)) {
            return null;
        }

        $query = DigiflazzProduct::query()->available();
        $filters = [
            'pln' => 'PLN', 'pdam' => 'PDAM', 'bpjs' => 'BPJS',
            'telkomsel' => 'TELKOMSEL', 'indosat' => 'INDOSAT', 'xl' => 'XL',
            'axis' => 'AXIS', 'tri' => 'TRI', 'smartfren' => 'SMARTFREN',
            'pulsa' => 'Pulsa', 'token' => 'PLN',
        ];
        $specific = false;
        foreach ($filters as $word => $term) {
            if (preg_match('/\b'.preg_quote($word, '/').'\b/u', $text)) {
                $specific = true;
                $query->where(function ($search) use ($term): void {
                    $search->where('product_name', 'like', "%{$term}%")
                        ->orWhere('brand', 'like', "%{$term}%")
                        ->orWhere('category', 'like', "%{$term}%");
                });
            }
        }
        if (preg_match('/\b(tagihan|pascabayar)\b/u', $text)) {
            $query->where('transaction_type', DigiflazzTransactionType::Postpaid->value);
        }
        if ($selection !== null) {
            $specific = true;
            $query->where('category', $selection['category'])->where('brand', $selection['brand']);
        }
        if (! $specific) {
            $groups = $query->select('category', 'brand')->distinct()->orderBy('category')->orderBy('brand')->offset($offset)->limit(21)->get();
            $choices = [];
            foreach ($groups->take(20)->values() as $index => $group) {
                $choices[$offset + $index + 1] = ['category' => $group->category, 'brand' => $group->brand];
            }
            $context = ['kind' => 'groups', 'choices' => $choices, 'question' => $text, 'next_offset' => $offset + 20, 'has_more' => $groups->count() > 20];
            if ($groups->isEmpty()) {
                return 'Belum ada produk yang tersedia pada katalog Digiflazz tersimpan untuk permintaan ini. Silakan hubungi operator.';
            }
            $lines = $groups->take(20)->values()->map(fn ($product, $index) => ($offset + $index + 1).'. '.$product->category.' — '.$product->brand)->all();
            return "*Daftar Layanan Digiflazz*\nHalaman ".(intdiv($offset, 20) + 1)." · Katalog terakhir\n\n".implode("\n", $lines)
                .($groups->count() > 20 ? "\n\n*Halaman berikutnya:*\nBalas *LANJUT KATALOG*." : '')
                ."\n\n*Lihat produk:*\nBalas *nomor layanan* untuk melihat paketnya, atau tulis nama layanan/operator, misalnya *pulsa Telkomsel* atau *tagihan PLN*.\n\n*Catatan:* Nominal tagihan diketahui setelah cek tagihan. Daftar ini hanya informasi, belum membuat transaksi.";
        }
        $products = $query->orderBy('selling_price')->orderBy('id')->offset($offset)->limit(11)->get();
        $productChoices = [];
        foreach ($products->take(10)->values() as $index => $product) {
            $productChoices[$offset + $index + 1] = (int) $product->id;
        }
        $context = ['parent' => $parent, 'product_choices' => $productChoices, 'kind' => 'products', 'selection' => $selection, 'question' => $text, 'next_offset' => $offset + 10, 'has_more' => $products->count() > 10];
        if ($products->isEmpty()) {
            return 'Produk tersebut belum tersedia pada katalog Digiflazz tersimpan. Coba nama operator atau layanan lain.';
        }
        $lines = $products->take(10)->values()->map(fn ($product, $index) => ($offset + $index + 1).'. *'.$this->plainText($product->product_name)."*\n   "
            .($product->transaction_type === DigiflazzTransactionType::Postpaid
                ? 'Tagihan: nominal setelah cek tagihan'
                : 'Harga: Rp'.number_format($product->selling_price, 0, ',', '.'))
            .(filled($product->description) ? "\n   Detail: ".$this->plainText($product->description) : ''))->all();

        $eligible = [];
        foreach ($products->take(10)->values() as $index => $product) {
            if ($product->supportsGuidedPhonePurchase()) $eligible[] = $offset + $index + 1;
        }
        $instruction = $eligible !== []
            ? '*Pilih paket:* Balas nomor '.implode(', ', $eligible).' untuk mengisi nomor HP (Data/Pulsa/GoPay prabayar, khusus operator). Pembelian memerlukan *KONFIRMASI <sandi>*.'
            : 'Pilihan angka untuk pembelian belum didukung pada halaman ini. Gunakan BELI nama produk ke nomor tujuan, atau CEK TAGIHAN untuk pascabayar.';

        return "*Daftar Produk Digiflazz*\nHalaman ".(intdiv($offset, 10) + 1)." · Katalog terakhir\n\n".implode("\n\n", $lines)
            .($products->count() > 10 ? "\n\n*Halaman berikutnya:*\nBalas *LANJUT KATALOG*, atau tulis nama produk lebih spesifik." : '')
            ."\n\n".$instruction
            ."\n\n*Catatan:* Ini informasi katalog, bukan pembelian. Ketersediaan diperiksa kembali saat transaksi.";
    }
    private function plainText(?string $value): string
    {
        $text = strip_tags(html_entity_decode($value ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        return trim(preg_replace('/[\x00-\x1F\x7F*_~`]+/u', ' ', $text) ?? '');
    }

}
