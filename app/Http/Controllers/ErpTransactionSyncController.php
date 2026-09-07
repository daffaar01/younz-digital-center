<?php

namespace App\Http\Controllers;

use App\Enums\DigitalTransactionStatus;
use App\Enums\TopupFulfillmentStatus;
use App\Enums\TopupPaymentStatus;
use App\Models\DigitalTransaction;
use App\Models\TopupOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ErpTransactionSyncController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $input = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $page = (int) ($input['page'] ?? 1);
        $perPage = (int) ($input['per_page'] ?? 50);

        // Both source tables are intentionally capped. The ERP reads the
        // newest records first and does not copy or mutate source data.
        $manual = DigitalTransaction::query()
            ->with(['customer:id,name'])
            ->latest('created_at')
            ->limit(1000)
            ->get()
            ->map(fn (DigitalTransaction $transaction): array => $this->manual($transaction));

        $topups = TopupOrder::query()
            ->latest('created_at')
            ->limit(1000)
            ->get()
            ->map(fn (TopupOrder $order): array => $this->topup($order));

        $items = $manual
            ->concat($topups)
            ->sortByDesc('transaction_date')
            ->values();
        $total = $items->count();
        $lastPage = max(1, (int) ceil($total / $perPage));

        return response()->json([
            'data' => $items->forPage($page, $perPage)->values(),
            'current_page' => $page,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'total' => $total,
        ]);
    }

    /** @return array<string, mixed> */
    private function manual(DigitalTransaction $transaction): array
    {
        $rawStatus = $transaction->status instanceof DigitalTransactionStatus
            ? $transaction->status->value
            : (string) $transaction->getRawOriginal('status');
        $status = match ($rawStatus) {
            DigitalTransactionStatus::Success->value => 'success',
            DigitalTransactionStatus::Failed->value,
            DigitalTransactionStatus::Cancelled->value,
            DigitalTransactionStatus::Refunded->value => 'failed',
            default => 'pending',
        };
        $total = (int) $transaction->selling_price + (int) $transaction->admin_fee;

        return [
            'id' => 'legacy-digital-'.$transaction->getKey(),
            'transaction_number' => (string) $transaction->transaction_number,
            'transaction_date' => $transaction->created_at?->toIso8601String(),
            'status' => $status,
            'payment_status' => $status === 'success' ? 'paid' : 'unpaid',
            'total_amount' => $total,
            'paid_amount' => $status === 'success' ? $total : 0,
            'customer' => ['name' => $transaction->customer?->name],
            'items' => [[
                'name' => (string) ($transaction->type ?: $transaction->provider ?: 'Transaksi digital'),
                'quantity' => 1,
            ]],
            'source' => 'Younz Digital Center',
            'product_name' => (string) ($transaction->type ?: 'Transaksi digital'),
            'provider' => $transaction->provider,
            'destination' => $this->mask((string) $transaction->destination),
            'can_pay' => false,
        ];
    }

    /** @return array<string, mixed> */
    private function topup(TopupOrder $order): array
    {
        $paymentStatus = $order->payment_status instanceof TopupPaymentStatus
            ? $order->payment_status->value
            : (string) $order->getRawOriginal('payment_status');
        $fulfillmentStatus = $order->fulfillment_status instanceof TopupFulfillmentStatus
            ? $order->fulfillment_status->value
            : (string) $order->getRawOriginal('fulfillment_status');
        $status = match ($fulfillmentStatus) {
            TopupFulfillmentStatus::Success->value => 'success',
            TopupFulfillmentStatus::Failed->value => 'failed',
            default => 'pending',
        };
        $total = (int) $order->total_amount;

        return [
            'id' => 'legacy-topup-'.$order->order_number,
            'transaction_number' => (string) $order->order_number,
            'transaction_date' => $order->created_at?->toIso8601String(),
            'status' => $status,
            'payment_status' => $paymentStatus,
            'total_amount' => $total,
            'paid_amount' => $paymentStatus === TopupPaymentStatus::Paid->value ? $total : 0,
            'customer' => ['name' => $order->provider_customer_name ?: $order->customer_name],
            'items' => [[
                'name' => (string) $order->product_name,
                'quantity' => 1,
            ]],
            'source' => 'Younz Digital Center',
            'product_name' => $order->product_name,
            'provider' => 'Digiflazz',
            'destination' => $order->maskedDestination(),
            'can_pay' => false,
        ];
    }

    private function mask(string $value): string
    {
        $length = mb_strlen($value);

        if ($length <= 5) {
            return str_repeat('*', max(0, $length - 2)).mb_substr($value, -2);
        }

        return mb_substr($value, 0, 3).str_repeat('*', max(3, $length - 6)).mb_substr($value, -3);
    }
}
