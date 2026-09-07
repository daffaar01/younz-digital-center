<?php

namespace App\Console\Commands;

use App\Actions\Topup\ApplyMidtransStatus;
use App\Enums\TopupFulfillmentStatus;
use App\Enums\TopupPaymentStatus;
use App\Integrations\Midtrans\MidtransClient;
use App\Jobs\ProcessTopupOrder;
use App\Models\TopupOrder;
use App\Support\AuditLogger;
use Illuminate\Console\Command;
use Throwable;

class ReconcileMidtransPayments extends Command
{
    protected $signature = 'midtrans:reconcile-payments {--order= : Nomor pesanan tertentu} {--limit=50 : Jumlah maksimum transaksi}';

    protected $description = 'Cocokkan pembayaran tertunda dengan Midtrans dan pulihkan antrean Digiflazz';

    public function handle(MidtransClient $midtrans, ApplyMidtransStatus $applyStatus, AuditLogger $audit): int
    {
        if (! $midtrans->isConfigured()) {
            $this->warn('Midtrans belum diaktifkan atau kredensial belum lengkap.');

            return self::FAILURE;
        }

        $limit = max(1, min(100, (int) $this->option('limit')));
        $orderNumber = trim((string) $this->option('order'));
        $query = TopupOrder::query()
            ->where(function ($builder): void {
                $builder->where(function ($pending): void {
                    $pending->where('payment_status', TopupPaymentStatus::Pending)
                        ->whereNotNull('midtrans_redirect_url')
                        ->where(function ($eligible): void {
                            $eligible->where('expires_at', '<=', now())
                                ->orWhere('created_at', '>=', now()->subDays(7));
                        });
                })->orWhere(function ($queued): void {
                    $queued->where('payment_status', TopupPaymentStatus::Paid)
                        ->whereIn('fulfillment_status', [
                            TopupFulfillmentStatus::Queued,
                            TopupFulfillmentStatus::ProviderPending,
                        ]);
                });
            });

        if ($orderNumber !== '') {
            $query->where('order_number', $orderNumber);
        } else {
            $query->oldest();
        }

        $orders = $query->limit($limit)->get();
        $failures = 0;

        foreach ($orders as $order) {
            try {
                if ($order->payment_status === TopupPaymentStatus::Paid) {
                    ProcessTopupOrder::dispatch($order->id);
                    $audit->log('topup.digiflazz_reconciliation_dispatched', $order, metadata: [
                        'order_number' => $order->order_number,
                        'fulfillment_status' => $order->fulfillment_status->value,
                    ]);
                    $this->line("{$order->order_number}: status Digiflazz diperiksa kembali.");

                    continue;
                }

                if ($order->expires_at?->isPast()) {
                    $applyStatus->handle($order, [
                        'status_code' => '407',
                        'transaction_status' => 'expire',
                    ]);
                    $audit->log('topup.midtrans_expired_locally', $order, metadata: [
                        'order_number' => $order->order_number,
                        'expired_at' => $order->expires_at->toIso8601String(),
                    ]);
                    $this->line("{$order->order_number}: kedaluwarsa secara lokal.");

                    continue;
                }

                $payload = $midtrans->getTransactionStatus($order);
                $becamePaid = $applyStatus->handle($order, $payload);
                $audit->log('topup.midtrans_reconciled', $order, metadata: [
                    'transaction_status' => (string) ($payload['transaction_status'] ?? ''),
                    'payment_transitioned' => $becamePaid,
                ]);
                $this->line("{$order->order_number}: ".(string) ($payload['transaction_status'] ?? 'unknown').'.');
            } catch (Throwable $exception) {
                $failures++;
                report($exception);
                $this->error("{$order->order_number}: rekonsiliasi gagal.");
            }
        }

        if ($orders->isEmpty()) {
            $this->info('Tidak ada transaksi yang perlu direkonsiliasi.');
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
