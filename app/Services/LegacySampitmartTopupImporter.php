<?php

namespace App\Services;

use App\Models\DigiflazzProduct;
use App\Models\TopupOrder;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class LegacySampitmartTopupImporter
{
    /**
     * @return array{source:int,eligible:int,created:int,skipped:int,conflicts:int}
     */
    public function import(string $sourceConnection, bool $dryRun = true): array
    {
        $source = DB::connection($sourceConnection);
        $orders = $this->sourceOrders($source);
        $summary = ['source' => $orders->count(), 'eligible' => 0, 'created' => 0, 'skipped' => 0, 'conflicts' => 0];
        $plans = [];

        foreach ($orders as $order) {
            if ($order->payment_status !== 'paid' || $order->topup_status !== 'success') {
                continue;
            }

            $summary['eligible']++;
            $sourceReference = $this->sourceReference((string) $order->id);
            $existing = TopupOrder::query()->where('source_reference', $sourceReference)->first();
            if ($existing) {
                $summary['skipped']++;
                continue;
            }

            $providerReference = trim((string) ($order->digiflazz_ref_id ?: $order->id));
            if (TopupOrder::query()->where('digiflazz_reference', $providerReference)->exists()) {
                $summary['conflicts']++;
                continue;
            }

            $plans[] = $this->mapOrder($order, $sourceReference, $providerReference);
        }

        if ($summary['conflicts'] > 0) {
            throw new RuntimeException("Impor dibatalkan: {$summary['conflicts']} reference provider bertabrakan.");
        }

        if ($dryRun) {
            return $summary;
        }

        DB::transaction(function () use ($plans, &$summary): void {
            foreach ($plans as $attributes) {
                TopupOrder::query()->create($attributes);
                $summary['created']++;
            }
        });

        return $summary;
    }

    private function sourceOrders(ConnectionInterface $source)
    {
        return $source->table('digital_orders as orders')
            ->leftJoin('digital_products as products', 'products.id', '=', 'orders.digital_product_id')
            ->select([
                'orders.*',
                'products.base_price as legacy_base_price',
                'products.sell_price as legacy_sell_price',
                'products.brand as legacy_brand',
            ])
            ->orderBy('orders.created_at')
            ->get();
    }

    /** @return array<string, mixed> */
    private function mapOrder(object $order, string $sourceReference, string $providerReference): array
    {
        $transactionType = strcasecmp((string) $order->product_category, 'Pascabayar') === 0
            ? 'postpaid'
            : 'prepaid';
        $product = DigiflazzProduct::query()
            ->where('transaction_type', $transactionType)
            ->where('buyer_sku_code', (string) $order->buyer_sku_code)
            ->first();

        $total = $this->money($order->gross_amount ?: $order->amount);
        $legacyBasePrice = $this->money($order->legacy_base_price);
        $postpaidPrice = $this->money($order->postpaid_price);
        $adminFee = $transactionType === 'postpaid' ? $this->money($order->postpaid_admin_fee) : 0;
        $costPrice = $transactionType === 'postpaid'
            ? $postpaidPrice + $legacyBasePrice
            : $legacyBasePrice;
        $sellingPrice = $transactionType === 'postpaid'
            ? $postpaidPrice
            : $total;
        $createdAt = (string) $order->created_at;
        $paidAt = $order->paid_at ? (string) $order->paid_at : $createdAt;
        $fulfilledAt = $order->processed_at ? (string) $order->processed_at : $paidAt;
        $destination = trim((string) $order->customer_no);
        $customerName = trim((string) $order->customer_name) ?: 'Pelanggan SampitMart';
        $customerPhone = trim((string) $order->customer_phone) ?: $destination;
        $customerEmail = trim((string) $order->customer_email)
            ?: 'legacy-'.substr(hash('sha256', (string) $order->id), 0, 12).'@example.invalid';
        $brand = trim((string) $order->legacy_brand) ?: (string) ($product?->brand ?: 'DIGITAL');

        return [
            'transaction_type' => $transactionType,
            'order_number' => $this->orderNumber((string) $order->id, $createdAt),
            'public_token' => (string) Str::uuid(),
            'user_id' => null,
            'digiflazz_product_id' => $product?->id,
            'idempotency_key' => $this->deterministicUuid($sourceReference),
            'request_fingerprint' => hash('sha256', $sourceReference),
            'source_reference' => $sourceReference,
            'sku' => (string) $order->buyer_sku_code,
            'product_name' => (string) $order->product_name,
            'category' => (string) $order->product_category,
            'brand' => $brand,
            'destination' => $destination,
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'customer_phone' => $customerPhone,
            'provider_customer_name' => $transactionType === 'postpaid'
                ? (trim((string) $order->postpaid_customer_name) ?: null)
                : null,
            'bill_details' => $transactionType === 'postpaid' ? array_filter([
                'period' => $order->postpaid_period,
                'bill_amount' => $postpaidPrice,
            ], fn ($value) => $value !== null && $value !== '') : null,
            'cost_price' => $costPrice,
            'selling_price' => $sellingPrice,
            'admin_fee' => $adminFee,
            'total_amount' => $total,
            'payment_status' => 'paid',
            'fulfillment_status' => 'success',
            'midtrans_order_id' => 'legacy-sampitmart-'.hash('sha256', (string) $order->id),
            'midtrans_transaction_id' => null,
            'midtrans_payment_type' => $order->payment_method ?: null,
            'midtrans_status' => 'settlement',
            'midtrans_snap_token' => null,
            'midtrans_redirect_url' => null,
            'digiflazz_reference' => $providerReference,
            'serial_number' => $order->serial_number ?: null,
            'provider_message' => $order->message ?: 'Imported from SampitMart history',
            'provider_rc' => $order->digiflazz_rc ?: '00',
            'paid_at' => $paidAt,
            'fulfilled_at' => $fulfilledAt,
            'expires_at' => null,
            'inquired_at' => $transactionType === 'postpaid' ? $createdAt : null,
            'provider_payment_requested_at' => $transactionType === 'postpaid' ? $fulfilledAt : null,
            'created_at' => $createdAt,
            'updated_at' => (string) ($order->updated_at ?: $createdAt),
        ];
    }

    private function sourceReference(string $id): string
    {
        return 'legacy:sampitmart:digital_order:'.$id;
    }

    private function orderNumber(string $id, string $createdAt): string
    {
        $date = preg_replace('/\D/', '', substr($createdAt, 0, 10));
        $suffix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $id), -10));

        return "SM-{$date}-{$suffix}";
    }

    private function deterministicUuid(string $value): string
    {
        $hex = hash('sha256', $value);

        return sprintf('%s-%s-5%s-a%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 13, 3),
            substr($hex, 17, 3),
            substr($hex, 20, 12),
        );
    }

    private function money(mixed $value): int
    {
        return max(0, (int) round((float) ($value ?? 0)));
    }
}
