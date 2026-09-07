<?php

namespace App\Http\Controllers;

use App\Enums\DigiflazzTransactionType;
use App\Enums\TopupFulfillmentStatus;
use App\Enums\TopupPaymentStatus;
use App\Integrations\Digiflazz\DigiflazzClient;
use App\Jobs\ProcessTopupOrder;
use App\Models\DigiflazzProduct;
use App\Models\TopupOrder;
use App\Support\DocumentNumberGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AgentPpobApiController extends Controller
{
    public function __construct(private readonly DocumentNumberGenerator $numbers) {}

    public function searchProducts(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $products = DigiflazzProduct::query()
            ->available()
            ->when($q !== '', fn ($query) => $query->where(function ($search) use ($q): void {
                $search->where('product_name', 'like', "%{$q}%")
                    ->orWhere('buyer_sku_code', 'like', "%{$q}%")
                    ->orWhere('brand', 'like', "%{$q}%")
                    ->orWhere('category', 'like', "%{$q}%");
            }))
            ->orderBy('selling_price')
            ->limit(50)
            ->get()
            ->map(fn (DigiflazzProduct $product): array => [
                'sku' => $product->buyer_sku_code,
                'transaction_type' => $product->transaction_type->value,
                'product_name' => $product->product_name,
                'category' => $product->category,
                'brand' => $product->brand,
                'cost_price' => $product->cost_price,
                'selling_price' => $product->selling_price,
                'profit' => $product->selling_price - $product->cost_price,
            ]);

        return response()->json(['data' => $products]);
    }

    public function balance(DigiflazzClient $digiflazz): JsonResponse
    {
        return response()->json(['data' => $digiflazz->balance()]);
    }

    public function preparePrepaid(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:100'],
            'customer_no' => ['required', 'string', 'min:4', 'max:40'],
            'request_id' => ['required', 'uuid'],
        ]);
        $requestId = mb_strtolower($data['request_id']);
        $source = 'agent:'.$requestId;
        $fingerprint = hash('sha256', json_encode([$data['sku'], $data['customer_no']], JSON_THROW_ON_ERROR));
        $existing = TopupOrder::query()->where('source_reference', $source)->first();

        if ($existing !== null) {
            if (! hash_equals($existing->request_fingerprint, $fingerprint)) {
                throw ValidationException::withMessages(['request_id' => 'Request ID sudah digunakan untuk transaksi berbeda.']);
            }

            return response()->json(['data' => $this->publicTransaction($existing)]);
        }

        $product = DigiflazzProduct::query()
            ->available()
            ->where('transaction_type', DigiflazzTransactionType::Prepaid->value)
            ->where('buyer_sku_code', $data['sku'])
            ->first();
        if ($product === null) {
            throw ValidationException::withMessages(['sku' => 'Produk prepaid tidak tersedia.']);
        }

        try {
            $order = DB::transaction(function () use ($data, $fingerprint, $product, $requestId, $source): TopupOrder {
                $existing = TopupOrder::query()->where('source_reference', $source)->lockForUpdate()->first();
                if ($existing !== null) {
                    if (! hash_equals($existing->request_fingerprint, $fingerprint)) {
                        throw ValidationException::withMessages(['request_id' => 'Request ID sudah digunakan untuk transaksi berbeda.']);
                    }
                    return $existing;
                }

                $number = $this->numbers->next('TOP');
                return TopupOrder::create([
                    'transaction_type' => DigiflazzTransactionType::Prepaid,
                    'order_number' => $number,
                    'public_token' => (string) Str::uuid(),
                    'digiflazz_product_id' => $product->id,
                    'idempotency_key' => $requestId,
                    'request_fingerprint' => $fingerprint,
                    'source_reference' => $source,
                    'sku' => $product->buyer_sku_code,
                    'product_name' => $product->product_name,
                    'category' => $product->category,
                    'brand' => $product->brand,
                    'destination' => $data['customer_no'],
                    'customer_name' => 'Pelanggan Agent',
                    'customer_email' => 'agent-'.$number.'@example.invalid',
                    'customer_phone' => $data['customer_no'],
                    'cost_price' => $product->cost_price,
                    'selling_price' => $product->selling_price,
                    'admin_fee' => 0,
                    'total_amount' => $product->selling_price,
                    'payment_status' => TopupPaymentStatus::Pending,
                    'fulfillment_status' => TopupFulfillmentStatus::WaitingPayment,
                    'midtrans_order_id' => $number,
                    'digiflazz_reference' => $number,
                ]);
            }, 3);
        } catch (QueryException) {
            $order = TopupOrder::query()->where('source_reference', $source)->firstOrFail();
        }

        return response()->json(['data' => $this->publicTransaction($order)], 201);
    }

    public function inquiryPostpaid(Request $request, DigiflazzClient $digiflazz): JsonResponse
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:100'],
            'customer_no' => ['required', 'string', 'min:4', 'max:40'],
            'request_id' => ['required', 'uuid'],
        ]);
        $requestId = mb_strtolower($data['request_id']);
        $source = 'agent:'.$requestId;
        $fingerprint = hash('sha256', json_encode([$data['sku'], $data['customer_no']], JSON_THROW_ON_ERROR));
        $existing = TopupOrder::query()->where('source_reference', $source)->first();

        if ($existing !== null) {
            if (! hash_equals($existing->request_fingerprint, $fingerprint)) {
                throw ValidationException::withMessages(['request_id' => 'Request ID sudah digunakan untuk transaksi berbeda.']);
            }

            return response()->json(['data' => $this->publicTransaction($existing)]);
        }

        $product = DigiflazzProduct::query()
            ->available()
            ->where('transaction_type', DigiflazzTransactionType::Postpaid->value)
            ->where('buyer_sku_code', $data['sku'])
            ->first();
        if ($product === null) {
            throw ValidationException::withMessages(['sku' => 'Produk pascabayar tidak tersedia.']);
        }

        $order = DB::transaction(function () use ($data, $fingerprint, $product, $requestId, $source): TopupOrder {
            $existing = TopupOrder::query()->where('source_reference', $source)->lockForUpdate()->first();
            if ($existing !== null) {
                return $existing;
            }

            $number = $this->numbers->next('TOP');
            return TopupOrder::create([
                'transaction_type' => DigiflazzTransactionType::Postpaid,
                'order_number' => $number,
                'public_token' => (string) Str::uuid(),
                'digiflazz_product_id' => $product->id,
                'idempotency_key' => $requestId,
                'request_fingerprint' => $fingerprint,
                'source_reference' => $source,
                'sku' => $product->buyer_sku_code,
                'product_name' => $product->product_name,
                'category' => $product->category,
                'brand' => $product->brand,
                'destination' => $data['customer_no'],
                'customer_name' => 'Pelanggan Agent',
                'customer_email' => 'agent-'.$number.'@example.invalid',
                'customer_phone' => $data['customer_no'],
                'cost_price' => 0,
                'selling_price' => 0,
                'admin_fee' => (int) config('services.digiflazz.postpaid_admin_fee'),
                'total_amount' => 0,
                'payment_status' => TopupPaymentStatus::Pending,
                'fulfillment_status' => TopupFulfillmentStatus::WaitingPayment,
                'midtrans_order_id' => $number,
                'digiflazz_reference' => $number,
                'expires_at' => now()->addMinutes(60),
            ]);
        }, 3);

        if ($order->inquired_at === null) {
            $inquiry = $digiflazz->inquirePostpaid($order);
            $normalized = $digiflazz->normalizePostpaidInquiry($inquiry);
            $cost = $normalized['cost'];
            $sellingPrice = $normalized['selling_price'];
            if (! $normalized['successful'] || $cost <= 0 || $sellingPrice <= 0) {
                $order->update([
                    'provider_rc' => $normalized['rc'],
                    'provider_message' => $normalized['message'],
                ]);
                throw ValidationException::withMessages([
                    'customer_no' => $normalized['message'] ?? 'Tagihan belum ditemukan.',
                ]);
            }

            $details = $inquiry['desc'] ?? null;
            $order->update([
                'provider_customer_name' => filled($inquiry['customer_name'] ?? null) ? (string) $inquiry['customer_name'] : null,
                'bill_details' => is_array($details) ? $details : null,
                'cost_price' => $cost,
                'selling_price' => $sellingPrice,
                'total_amount' => $sellingPrice + $order->admin_fee,
                'provider_rc' => $normalized['rc'],
                'provider_message' => $normalized['message'],
                'inquired_at' => now(),
            ]);
            $order->refresh();
        }

        return response()->json(['data' => $this->publicTransaction($order)], 201);
    }

    public function transaction(string $refId): JsonResponse
    {
        $order = TopupOrder::query()->where('order_number', $refId)->firstOrFail();
        return response()->json(['data' => $this->publicTransaction($order)]);
    }

    public function approve(Request $request, string $refId): JsonResponse
    {
        $data = $request->validate([
            'confirmation' => ['required', 'string', 'max:150'],
        ]);

        if (! hash_equals('APPROVE '.$refId, trim($data['confirmation']))) {
            throw ValidationException::withMessages([
                'confirmation' => "Konfirmasi harus persis: APPROVE {$refId}",
            ]);
        }

        $transitioned = DB::transaction(function () use ($refId): bool {
            $order = TopupOrder::query()
                ->where('order_number', $refId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->payment_status === TopupPaymentStatus::Paid) {
                return false;
            }

            if ($order->payment_status !== TopupPaymentStatus::Pending
                || $order->fulfillment_status !== TopupFulfillmentStatus::WaitingPayment
            ) {
                throw ValidationException::withMessages([
                    'transaction' => 'Transaksi tidak lagi menunggu approval.',
                ]);
            }

            if ($order->transaction_type === DigiflazzTransactionType::Postpaid
                && ($order->inquired_at === null
                    || ! $order->inquired_at->isSameDay(now())
                    || $order->total_amount <= 0)
            ) {
                throw ValidationException::withMessages([
                    'transaction' => 'Inquiry pascabayar sudah tidak berlaku. Buat inquiry baru sebelum approval.',
                ]);
            }

            $order->update([
                'payment_status' => TopupPaymentStatus::Paid,
                'fulfillment_status' => TopupFulfillmentStatus::Queued,
                'paid_at' => now(),
                'provider_payment_requested_at' => null,
            ]);

            return true;
        }, 3);

        $order = TopupOrder::query()->where('order_number', $refId)->firstOrFail();
        if ($transitioned) {
            ProcessTopupOrder::dispatch($order->id);
        }

        return response()->json([
            'message' => $transitioned
                ? 'Approval diterima. Pembelian telah masuk antrean provider.'
                : 'Transaksi sudah pernah di-approve; tidak dieksekusi ulang.',
            'data' => $this->publicTransaction($order->fresh()),
        ]);
    }

    public function dailyReport(): JsonResponse
    {
        $query = TopupOrder::query()->where('created_at', '>=', now()->startOfDay());
        return response()->json(['data' => [
            'total' => (clone $query)->count(),
            'success' => (clone $query)->where('fulfillment_status', TopupFulfillmentStatus::Success->value)->count(),
            'pending' => (clone $query)->whereIn('fulfillment_status', [TopupFulfillmentStatus::Queued->value, TopupFulfillmentStatus::Processing->value, TopupFulfillmentStatus::ProviderPending->value])->count(),
            'failed' => (clone $query)->where('fulfillment_status', TopupFulfillmentStatus::Failed->value)->count(),
            'omzet' => (int) (clone $query)->where('payment_status', TopupPaymentStatus::Paid->value)->sum('total_amount'),
            'profit' => (int) (clone $query)->where('fulfillment_status', TopupFulfillmentStatus::Success->value)->selectRaw('coalesce(sum(total_amount - cost_price), 0) as aggregate')->value('aggregate'),
        ]]);
    }

    private function publicTransaction(TopupOrder $order): array
    {
        return [
            'ref_id' => $order->order_number,
            'transaction_type' => $order->transaction_type->value,
            'sku' => $order->sku,
            'product_name' => $order->product_name,
            'customer_no_masked' => $order->maskedDestination(),
            'cost_price' => $order->cost_price,
            'selling_price' => $order->selling_price,
            'profit' => $order->total_amount - $order->cost_price,
            'payment_status' => $order->payment_status->value,
            'fulfillment_status' => $order->fulfillment_status->value,
            'provider_status' => $order->provider_message,
            'rc' => $order->provider_rc,
            'sn' => $order->serial_number,
            'approval_required' => $order->payment_status !== TopupPaymentStatus::Paid,
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }
}
