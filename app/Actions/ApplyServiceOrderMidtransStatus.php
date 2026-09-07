<?php

namespace App\Actions;

use App\Enums\ServiceOrderStatus;
use App\Models\DigitalProduct;
use App\Models\DigitalProductVariant;
use App\Models\ServiceOrder;
use Illuminate\Support\Facades\DB;

class ApplyServiceOrderMidtransStatus
{
    /** @param array<string, mixed> $payload */
    public function handle(ServiceOrder $order, array $payload): bool
    {
        $becamePaid = DB::transaction(function () use ($order, $payload): bool {
            $locked = ServiceOrder::query()->lockForUpdate()->findOrFail($order->id);
            $originalStatus = $locked->status;
            $transactionStatus = strtolower((string) ($payload['transaction_status'] ?? ''));
            $statusCode = (string) ($payload['status_code'] ?? '');
            $fraudStatus = strtolower((string) ($payload['fraud_status'] ?? 'accept'));
            $transactionId = filled($payload['transaction_id'] ?? null) ? (string) $payload['transaction_id'] : null;
            $refundAmount = isset($payload['refund_amount']) && is_numeric($payload['refund_amount'])
                ? max(0, (int) $payload['refund_amount'])
                : 0;

            if ($locked->midtrans_transaction_id !== null && $transactionId !== null && $locked->midtrans_transaction_id !== $transactionId) {
                return false;
            }

            $wasPaid = in_array($locked->payment_status, ['paid', 'partial_refunded'], true);
            $wasRefunded = $locked->payment_status === 'refunded';
            $paid = ! $wasRefunded && $locked->payment_status !== 'partial_refunded' && $statusCode === '200'
                && in_array($transactionStatus, ['settlement', 'capture'], true)
                && $fraudStatus === 'accept';
            $paymentStatus = $locked->payment_status;
            if ($wasRefunded) {
                $paymentStatus = 'refunded';
            } elseif ($transactionStatus === 'refund') {
                $paymentStatus = 'refunded';
            } elseif ($transactionStatus === 'partial_refund') {
                $paymentStatus = 'partial_refunded';
            } elseif ($paid) {
                $paymentStatus = 'paid';
            } elseif (! $wasPaid) {
                $paymentStatus = match ($transactionStatus) {
                    'expire' => 'expired',
                    'cancel' => 'cancelled',
                    'deny', 'failure' => 'failed',
                    default => 'pending',
                };
            }
            $specifications = $locked->specifications ?? [];
            $canQueue = true;
            $workflowAllowsQueue = $locked->status === ServiceOrderStatus::AwaitingPayment;
            if ($paid && ($specifications['stock_reserved'] ?? false) && ($specifications['stock_released'] ?? false)) {
                $quantity = (int) ($specifications['quantity'] ?? 0);
                $stockOwner = $this->lockStockOwner($specifications);
                if ($stockOwner && $stockOwner->stock !== null && $quantity > 0 && $stockOwner->stock >= $quantity) {
                    $stockOwner->decrement('stock', $quantity);
                    $specifications['stock_released'] = false;
                    unset($specifications['stock_shortfall']);
                } else {
                    $canQueue = false;
                    $specifications['stock_shortfall'] = true;
                }
            }
            $becamePaid = ! $wasPaid && $paymentStatus === 'paid';
            $nextStatus = $becamePaid
                ? ($canQueue && $workflowAllowsQueue ? ServiceOrderStatus::Queued : ServiceOrderStatus::AwaitingReview)
                : $locked->status;
            $terminalFailure = in_array($paymentStatus, ['expired', 'cancelled', 'failed', 'refunded'], true);
            $originalAmount = (int) ($locked->estimated_price ?? ($locked->paid_amount + $locked->refunded_amount));
            $nextRefundedAmount = $locked->refunded_amount;
            $nextPaidAmount = $becamePaid ? $locked->estimated_price : $locked->paid_amount;
            if ($transactionStatus === 'partial_refund') {
                $nextRefundedAmount = min($originalAmount, max((int) $locked->refunded_amount, $refundAmount));
                $nextPaidAmount = max(0, $originalAmount - $nextRefundedAmount);
            } elseif ($transactionStatus === 'refund') {
                $nextRefundedAmount = $originalAmount;
                $nextPaidAmount = 0;
            }
            if ($terminalFailure && ($specifications['stock_reserved'] ?? false) && ! ($specifications['stock_released'] ?? false)) {
                $stockOwner = $this->lockStockOwner($specifications);
                if ($stockOwner && $stockOwner->stock !== null) {
                    $stockOwner->increment('stock', (int) ($specifications['quantity'] ?? 0));
                    $specifications['stock_released'] = true;
                    unset($specifications['stock_reconciliation_error']);
                } else {
                    $specifications['stock_reconciliation_error'] = 'stock_owner_missing';
                }
            }

            $locked->update([
                'payment_status' => $paymentStatus,
                'status' => $nextStatus,
                'paid_amount' => $nextPaidAmount,
                'refunded_amount' => $nextRefundedAmount,
                'payment_confirmation_at' => $becamePaid ? now() : $locked->payment_confirmation_at,
                'payment_confirmation_method' => filled($payload['payment_type'] ?? null) ? (string) $payload['payment_type'] : $locked->payment_confirmation_method,
                'payment_confirmation_reference' => $locked->payment_confirmation_reference ?: $transactionId,
                'midtrans_transaction_id' => $locked->midtrans_transaction_id ?: $transactionId,
                'midtrans_payment_type' => filled($payload['payment_type'] ?? null) ? (string) $payload['payment_type'] : $locked->midtrans_payment_type,
                'midtrans_status' => $transactionStatus,
                'specifications' => $specifications,
            ]);

            if ($becamePaid) {
                $locked->statusHistories()->create([
                    'from_status' => $originalStatus->value,
                    'to_status' => $nextStatus->value,
                    'notes' => $canQueue && $workflowAllowsQueue
                        ? 'Pembayaran Midtrans terverifikasi.'
                        : 'Pembayaran terverifikasi, tetapi pesanan perlu ditangani operator.',
                ]);
            }

            return $becamePaid;
        });

        return $becamePaid;
    }

    /** @param array<string, mixed> $specifications */
    private function lockStockOwner(array $specifications): DigitalProduct|DigitalProductVariant|null
    {
        if (! empty($specifications['digital_product_variant_id'])) {
            return DigitalProductVariant::query()->lockForUpdate()->find($specifications['digital_product_variant_id']);
        }

        return DigitalProduct::query()->lockForUpdate()->find($specifications['digital_product_id'] ?? null);
    }
}
