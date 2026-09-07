<?php

namespace App\Jobs;

use App\Integrations\Digiflazz\DigiflazzClient;
use App\Integrations\Discord\DiscordNotifier;
use App\Models\TopupOrder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendTopupDiscordNotification implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 30;
    public array $backoff = [30, 120, 300, 900];
    public int $uniqueFor = 86400;

    public function __construct(public readonly int $orderId, public readonly string $event)
    {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return "topup:{$this->orderId}:{$this->event}:discord";
    }

    public function handle(DiscordNotifier $notifier, DigiflazzClient $digiflazz): void
    {
        $order = TopupOrder::query()->find($this->orderId);
        if ($order === null) {
            return;
        }

        [$title, $message] = match ($this->event) {
            'paid' => ['Pembayaran Top Up Diterima', "Top up {$order->order_number} masuk antrean provider."],
            'success' => ['Top Up Berhasil', "Top up {$order->order_number} berhasil diproses provider."],
            'failed' => ['Top Up Gagal', "Top up {$order->order_number} gagal diproses provider dan perlu ditindaklanjuti."],
            default => ['Pembaruan Top Up', "Top up {$order->order_number} diperbarui."],
        };

        $fields = [
            ['name' => 'Produk', 'value' => $order->product_name, 'inline' => true],
            ['name' => 'Tujuan', 'value' => $order->maskedDestination(), 'inline' => true],
            ['name' => 'Status Provider', 'value' => $order->fulfillment_status->label(), 'inline' => true],
        ];

        if ($this->event === 'success') {
            try {
                $balance = $digiflazz->balance();
                if (isset($balance['deposit']) && is_numeric($balance['deposit'])) {
                    $fields[] = [
                        'name' => 'Sisa Saldo Digiflazz',
                        'value' => 'Rp'.number_format((int) $balance['deposit'], 0, ',', '.'),
                        'inline' => true,
                    ];
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $notifier->notify(
            event: $this->event === 'failed' ? 'topup.failed' : 'topup.paid',
            title: $title,
            message: $message,
            fields: $fields,
            amount: $order->total_amount,
            reference: $order->order_number,
            mentionStaff: $this->event === 'failed',
        );
    }
}
