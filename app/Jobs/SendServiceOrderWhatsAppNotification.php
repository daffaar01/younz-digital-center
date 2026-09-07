<?php

namespace App\Jobs;

use App\Enums\ServiceOrderStatus;
use App\Integrations\WhatsApp\WhatsAppGatewayClient;
use App\Models\ServiceOrder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendServiceOrderWhatsAppNotification implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 30;
    public array $backoff = [30, 120, 300, 900];
    public int $uniqueFor = 86400;

    public function __construct(
        public readonly int $orderId,
        public readonly int $historyId,
        public readonly ServiceOrderStatus $previous,
        public readonly ServiceOrderStatus $next,
    ) {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return "service-order-history:{$this->historyId}";
    }

    public function handle(WhatsAppGatewayClient $gateway): void
    {
        if (! $gateway->isConfigured()) {
            return;
        }

        $order = ServiceOrder::query()->find($this->orderId);
        if (! $order || ! $order->customer_phone) {
            return;
        }

        $trackingUrl = route('public.track.show', $order->public_token);
        $message = implode("\n", [
            "Halo {$order->customer_name},",
            '',
            "Status pesanan *{$order->order_number}* telah diperbarui:",
            "*{$this->next->label()}*",
            '',
            "Pantau pesanan: {$trackingUrl}",
            '',
            'Pesan ini dikirim otomatis oleh Younz Digital Center.',
        ]);

        $gateway->sendText($order->customer_phone, $message);
    }
}
