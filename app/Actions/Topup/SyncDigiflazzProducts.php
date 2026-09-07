<?php

namespace App\Actions\Topup;

use App\Enums\DigiflazzTransactionType;
use App\Integrations\Digiflazz\DigiflazzClient;
use App\Models\DigiflazzProduct;
use App\Support\DigiflazzPricing;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SyncDigiflazzProducts
{
    public function __construct(
        private readonly DigiflazzClient $client,
        private readonly DigiflazzPricing $pricing,
    ) {}

    public function handle(DigiflazzTransactionType $type = DigiflazzTransactionType::Prepaid): int
    {
        $products = $this->client->priceList($type);

        if ($products === []) {
            throw new RuntimeException('Katalog Digiflazz kosong; data lokal tidak diubah.');
        }

        $now = now();
        $rows = [];

        foreach ($products as $product) {
            $sku = trim((string) ($product['buyer_sku_code'] ?? ''));
            $name = trim((string) ($product['product_name'] ?? ''));
            $cost = max(0, (int) ($product['price'] ?? 0));

            if ($sku === '' || $name === '' || ($type === DigiflazzTransactionType::Prepaid && $cost <= 0)) {
                continue;
            }

            $category = trim((string) ($product['category'] ?? 'Lainnya')) ?: 'Lainnya';
            $rows[] = [
                'transaction_type' => $type->value,
                'buyer_sku_code' => $sku,
                'product_name' => $name,
                'category' => $category,
                'brand' => trim((string) ($product['brand'] ?? 'Lainnya')) ?: 'Lainnya',
                'type' => filled($product['type'] ?? null) ? (string) $product['type'] : null,
                'description' => filled($product['desc'] ?? null) ? (string) $product['desc'] : null,
                'cost_price' => $cost,
                'selling_price' => $type === DigiflazzTransactionType::Prepaid
                    ? $this->pricing->prepaidSellingPrice($cost, $category, $name, $sku)
                    : 0,
                'provider_admin' => max(0, (int) ($product['admin'] ?? 0)),
                'provider_commission' => max(0, (int) ($product['commission'] ?? 0)),
                'buyer_product_status' => (bool) ($product['buyer_product_status'] ?? false),
                'seller_product_status' => (bool) ($product['seller_product_status'] ?? false),
                'unlimited_stock' => $type === DigiflazzTransactionType::Postpaid || (bool) ($product['unlimited_stock'] ?? false),
                'stock' => max(0, (int) ($product['stock'] ?? 0)),
                'multi' => (bool) ($product['multi'] ?? false),
                'start_cut_off' => filled($product['start_cut_off'] ?? null) ? (string) $product['start_cut_off'] : null,
                'end_cut_off' => filled($product['end_cut_off'] ?? null) ? (string) $product['end_cut_off'] : null,
                'synced_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            throw new RuntimeException('Tidak ada produk valid pada respons Digiflazz.');
        }

        DB::transaction(function () use ($rows, $type): void {
            DigiflazzProduct::query()->where('transaction_type', $type->value)->update([
                'buyer_product_status' => false,
                'seller_product_status' => false,
            ]);

            foreach (array_chunk($rows, 250) as $chunk) {
                DigiflazzProduct::query()->upsert($chunk, ['transaction_type', 'buyer_sku_code'], [
                    'product_name', 'category', 'brand', 'type', 'description', 'cost_price', 'selling_price',
                    'provider_admin', 'provider_commission',
                    'buyer_product_status', 'seller_product_status', 'unlimited_stock', 'stock', 'multi',
                    'start_cut_off', 'end_cut_off', 'synced_at', 'updated_at',
                ]);
            }
        });

        return count($rows);
    }
}
