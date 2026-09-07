<?php

namespace App\Jobs;

use App\Integrations\WhatsApp\WhatsAppGatewayClient;
use App\Models\TopupOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendTopupWhatsAppReceipt implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 45;
    /** @var list<int> */
    public array $backoff = [30, 120, 300, 900];
    public int $uniqueFor = 86400;

    public function __construct(public readonly int $orderId)
    {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return "topup:{$this->orderId}:receipt-pdf";
    }

    public function handle(WhatsAppGatewayClient $gateway): void
    {
        if (! $gateway->isConfigured()) {
            return;
        }

        $order = TopupOrder::query()->find($this->orderId);
        if (! $order
            || ! str_starts_with((string) $order->source_reference, 'whatsapp:')
            || $order->paid_at === null
        ) {
            return;
        }

        $gateway->sendDocument(
            $order->customer_phone,
            $order->receiptPdfUrl(),
            $order->order_number.'.pdf',
            'Struk PDF transaksi '.$order->order_number,
        );
    }
}
