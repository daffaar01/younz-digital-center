<?php

namespace App\Jobs;

use App\Enums\ServiceOrderStatus;
use App\Integrations\Discord\DiscordNotifier;
use App\Models\ServiceOrder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendServiceOrderDiscordNotification implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 30;
    public array $backoff = [30, 120, 300, 900];
    public int $uniqueFor = 86400;

    public function __construct(
        public readonly int $orderId,
        public readonly string $event,
        public readonly ?int $historyId = null,
        public readonly ?ServiceOrderStatus $previous = null,
        public readonly ?ServiceOrderStatus $next = null,
    ) {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return $this->historyId === null
            ? "service-order:{$this->orderId}:{$this->event}"
            : "service-order-history:{$this->historyId}:discord";
    }

    public function handle(DiscordNotifier $notifier): void
    {
        $order = ServiceOrder::query()->with('service:id,name')->find($this->orderId);
        if ($order === null) {
            return;
        }

        $created = $this->event === 'order.created';
        $title = $created ? 'Pesanan Baru' : 'Status Pesanan Berubah';
        $message = $created
            ? "Pesanan {$order->order_number} menunggu pemeriksaan."
            : "Pesanan {$order->order_number} berubah dari {$this->previous?->label()} menjadi {$this->next?->label()}.";

        $fields = [
            ['name' => 'Layanan', 'value' => $order->service?->name ?? $order->type, 'inline' => true],
            ['name' => 'Pelanggan', 'value' => $order->customer_name, 'inline' => true],
            ['name' => 'Status', 'value' => ($this->next ?? $order->status)->label(), 'inline' => true],
        ];

        if ($order->deadline_at !== null) {
            $fields[] = ['name' => 'Tenggat', 'value' => $order->deadline_at->format('d M Y H:i'), 'inline' => true];
        }

        $notifier->notify(
            event: $this->event,
            title: $title,
            message: $message,
            fields: $fields,
            amount: $order->payableAmount(),
            reference: $order->order_number,
            mentionStaff: $created || $this->next === ServiceOrderStatus::AwaitingOperator,
        );
    }
}
