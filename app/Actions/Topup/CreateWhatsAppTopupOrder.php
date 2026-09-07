<?php

namespace App\Actions\Topup;

use App\Enums\DigiflazzTransactionType;
use App\Enums\TopupFulfillmentStatus;
use App\Enums\TopupPaymentStatus;
use App\Integrations\Digiflazz\DigiflazzClient;
use App\Integrations\Midtrans\MidtransClient;
use App\Models\DigiflazzProduct;
use App\Models\TopupOrder;
use App\Support\DigiflazzPricing;
use App\Support\DocumentNumberGenerator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CreateWhatsAppTopupOrder
{
    public function __construct(
        private readonly DocumentNumberGenerator $numbers,
        private readonly MidtransClient $midtrans,
        private readonly DigiflazzClient $digiflazz,
        private readonly DigiflazzPricing $pricing,
    ) {}

    public function handle(int $productId, string $destination, string $phone, string $messageId): TopupOrder
    {
        return $this->create($productId, $destination, $phone, $messageId, direct: false);
    }

    public function handleDirect(int $productId, string $destination, string $phone, string $messageId, ?\Closure $claim = null): TopupOrder
    {
        return $this->create($productId, $destination, $phone, $messageId, direct: true, claim: $claim);
    }

    private function create(int $productId, string $destination, string $phone, string $messageId, bool $direct, ?\Closure $claim = null): TopupOrder
    {
        if (! $this->digiflazz->isConfigured() || (! $direct && ! $this->midtrans->isConfigured())) {
            throw new RuntimeException('Layanan top up sedang tidak tersedia.');
        }

        $sourceReference = 'whatsapp:'.hash('sha256', $messageId);
        try {
        $order = \App\Support\WhatsAppOperatorLease::transaction(function () use ($productId, $destination, $phone, $sourceReference, $claim): TopupOrder {
            $existing = TopupOrder::query()->where('source_reference', $sourceReference)->first();
            if ($existing !== null) {
                $normalizedPhone = preg_replace('/\D+/', '', $phone) ?? '';
                if (! hash_equals((string) $existing->request_fingerprint, hash('sha256', "{$productId}|{$destination}|{$normalizedPhone}"))) {
                    throw new RuntimeException('Referensi pesan sudah digunakan untuk permintaan berbeda.');
                }
                if ($claim !== null) {
                    if ($existing->payment_status !== TopupPaymentStatus::Pending || $existing->fulfillment_status !== TopupFulfillmentStatus::WaitingPayment || $existing->expires_at?->isPast()) {
                        throw new RuntimeException('Transaksi tidak lagi menunggu konfirmasi. Buat permintaan baru.');
                    }
                    $claim($existing);
                }
                return $existing;
            }

            $product = DigiflazzProduct::query()->available()->lockForUpdate()->find($productId);
            if ($product === null) throw new RuntimeException('Produk sudah tidak tersedia. Ketik TOPUP untuk memilih kembali.');

            $orderNumber = $this->numbers->next('TOP');
            $phone = preg_replace('/\D+/', '', $phone) ?? '';
            $isPostpaid = $product->transaction_type === DigiflazzTransactionType::Postpaid;
            $adminFee = $isPostpaid ? $this->pricing->postpaidAdminFee() : 0;

            $created = TopupOrder::create([
                'transaction_type' => $product->transaction_type,
                'order_number' => $orderNumber,
                'public_token' => (string) Str::uuid(),
                'digiflazz_product_id' => $product->id,
                'idempotency_key' => (string) Str::uuid(),
                'request_fingerprint' => hash('sha256', "{$product->id}|{$destination}|{$phone}"),
                'source_reference' => $sourceReference,
                'sku' => $product->buyer_sku_code,
                'product_name' => $product->product_name,
                'category' => $product->category,
                'brand' => $product->brand,
                'destination' => $destination,
                'customer_name' => 'Pelanggan WhatsApp',
                'customer_email' => "wa-{$phone}@younzdigitalcenter.my.id",
                'customer_phone' => $phone,
                'cost_price' => $isPostpaid ? 0 : $product->cost_price,
                'selling_price' => $isPostpaid ? 0 : $product->selling_price,
                'admin_fee' => $adminFee,
                'total_amount' => $isPostpaid ? 0 : $product->selling_price + $adminFee,
                'payment_status' => TopupPaymentStatus::Pending,
                'fulfillment_status' => TopupFulfillmentStatus::WaitingPayment,
                'midtrans_order_id' => $orderNumber,
                'digiflazz_reference' => $orderNumber,
                'expires_at' => now()->addMinutes(max(5, (int) config('services.midtrans.expiry_minutes', 60))),
            ]);
            if ($claim !== null) $claim($created);
            return $created;
        }, 3);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $error) {
            $order = TopupOrder::query()->where('source_reference', $sourceReference)->first();
            if ($order === null) throw $error;
            $normalizedPhone = preg_replace('/\D+/', '', $phone) ?? '';
            if (! hash_equals((string) $order->request_fingerprint, hash('sha256', "{$productId}|{$destination}|{$normalizedPhone}"))) {
                throw new RuntimeException('Referensi pesan sudah digunakan untuk permintaan berbeda.');
            }
            if ($claim !== null) \App\Support\WhatsAppOperatorLease::transaction(fn () => $claim($order));
        }

        return Cache::lock("topup-payment:{$order->id}", 40)->block(5, function () use ($order, $direct): TopupOrder {
            $fresh = TopupOrder::query()->findOrFail($order->id);
            if ($direct && ($fresh->payment_status !== TopupPaymentStatus::Pending
                || $fresh->fulfillment_status !== TopupFulfillmentStatus::WaitingPayment
                || $fresh->expires_at?->isPast())) {
                throw new RuntimeException('Transaksi tidak lagi menunggu konfirmasi. Buat permintaan baru.');
            }
            if (! $direct && filled($fresh->midtrans_redirect_url)) return $fresh;

            if ($fresh->transaction_type === DigiflazzTransactionType::Postpaid && $fresh->inquired_at === null) {
                $inquiry = $this->digiflazz->inquirePostpaid($fresh);
                $normalized = $this->digiflazz->normalizePostpaidInquiry($inquiry);
                $cost = $normalized['cost'];
                $sellingPrice = $normalized['selling_price'];
                if (! $normalized['successful'] || $cost <= 0 || $sellingPrice <= 0) {
                    throw new RuntimeException($normalized['message'] ?? 'Tagihan belum ditemukan. Periksa nomor tujuan lalu coba lagi.');
                }
                $details = $inquiry['desc'] ?? null;
                $fresh->update([
                    'provider_customer_name' => filled($inquiry['customer_name'] ?? null) ? (string) $inquiry['customer_name'] : null,
                    'bill_details' => is_array($details) ? $details : (filled($details) ? ['description' => (string) $details] : null),
                    'cost_price' => $cost,
                    'selling_price' => $sellingPrice,
                    'total_amount' => $sellingPrice + $fresh->admin_fee,
                    'provider_rc' => filled($inquiry['rc'] ?? null) ? (string) $inquiry['rc'] : null,
                    'provider_message' => filled($inquiry['message'] ?? null) ? (string) $inquiry['message'] : null,
                    'inquired_at' => now(),
                ]);
                $fresh->refresh();
            }

            if ($fresh->total_amount <= 0) throw new RuntimeException('Nominal pembayaran tidak valid.');
            if ($direct) return $fresh->fresh();

            $payment = $this->midtrans->createSnapTransaction($fresh);
            $fresh->update([
                'midtrans_snap_token' => $payment['token'],
                'midtrans_redirect_url' => $payment['redirect_url'],
                'midtrans_status' => 'pending',
            ]);
            return $fresh->fresh();
        });
    }
}
