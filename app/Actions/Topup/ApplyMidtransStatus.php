<?php

namespace App\Actions\Topup;

use App\Enums\TopupFulfillmentStatus;
use App\Enums\TopupPaymentStatus;
use App\Jobs\ProcessTopupOrder;
use App\Jobs\SendTopupDiscordNotification;
use App\Jobs\SendTopupWhatsAppNotification;
use App\Models\TopupOrder;
use Illuminate\Support\Facades\DB;

class ApplyMidtransStatus
{
    /** @param array<string, mixed> $payload */
    public function handle(TopupOrder $order, array $payload): bool
    {
        $becamePaid = DB::transaction(function () use ($order, $payload): bool {
            $locked = TopupOrder::query()->lockForUpdate()->findOrFail($order->id);
            $transactionStatus = strtolower((string) ($payload['transaction_status'] ?? ''));
            $statusCode = (string) ($payload['status_code'] ?? '');
            $fraudStatus = strtolower((string) ($payload['fraud_status'] ?? 'accept'));
            $transactionId = filled($payload['transaction_id'] ?? null) ? (string) $payload['transaction_id'] : null;

            if ($locked->midtrans_transaction_id !== null && $transactionId !== null && $locked->midtrans_transaction_id !== $transactionId) {
                return false;
            }

            $wasPaid = $locked->payment_status === TopupPaymentStatus::Paid;
            $newPaymentStatus = $locked->payment_status;

            if ($statusCode === '200' && in_array($transactionStatus, ['settlement', 'capture'], true) && $fraudStatus === 'accept') {
                $newPaymentStatus = TopupPaymentStatus::Paid;
            } elseif (! $wasPaid) {
                $newPaymentStatus = match ($transactionStatus) {
                    'expire' => TopupPaymentStatus::Expired,
                    'cancel' => TopupPaymentStatus::Cancelled,
                    'deny', 'failure' => TopupPaymentStatus::Failed,
                    default => TopupPaymentStatus::Pending,
                };
            }

            if (in_array($transactionStatus, ['refund', 'partial_refund'], true)) {
                $newPaymentStatus = TopupPaymentStatus::Refunded;
            }

            $becamePaid = ! $wasPaid && $newPaymentStatus === TopupPaymentStatus::Paid;
            $locked->update([
                'payment_status' => $newPaymentStatus,
                'fulfillment_status' => $becamePaid ? TopupFulfillmentStatus::Queued : $locked->fulfillment_status,
                'midtrans_transaction_id' => $locked->midtrans_transaction_id ?: $transactionId,
                'midtrans_payment_type' => filled($payload['payment_type'] ?? null) ? (string) $payload['payment_type'] : $locked->midtrans_payment_type,
                'midtrans_status' => $transactionStatus,
                'paid_at' => $becamePaid ? now() : $locked->paid_at,
            ]);

            return $becamePaid;
        });

        if ($becamePaid) {
            SendTopupWhatsAppNotification::dispatch($order->id, 'paid');
            SendTopupDiscordNotification::dispatch($order->id, 'paid');
            ProcessTopupOrder::dispatch($order->id);
        }

        return $becamePaid;
    }
}
