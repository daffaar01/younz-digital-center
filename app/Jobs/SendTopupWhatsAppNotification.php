<?php

namespace App\Jobs;

use App\Integrations\WhatsApp\WhatsAppGatewayClient;
use App\Models\TopupOrder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendTopupWhatsAppNotification implements ShouldBeUnique, ShouldQueue
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
        return "topup:{$this->orderId}:{$this->event}";
    }

    public function handle(WhatsAppGatewayClient $gateway): void
    {
        if (! $gateway->isConfigured()) {
            return;
        }

        $order = TopupOrder::query()->find($this->orderId);
        if (! $order || ! str_starts_with((string) $order->source_reference, 'whatsapp:')) return;

        $message = match ($this->event) {
            'paid' => implode("\n", [
                "Pembayaran *{$order->order_number}* sudah diterima.",
                'Top up sedang diproses ke provider.', '',
                'Produk: '.$order->product_name,
                'Tujuan: '.$order->maskedDestination(),
                'Total: Rp'.number_format($order->total_amount, 0, ',', '.'),
            ]),
            'success' => implode("\n", array_filter([
                "Top up *{$order->order_number}* berhasil.", '',
                'Produk: '.$order->product_name,
                'Tujuan: '.$order->maskedDestination(),
                $order->serial_number ? 'SN: '.$order->serial_number : null, '',
                'Terima kasih telah menggunakan Younz Digital Center.',
            ])),
            'failed' => implode("\n", [
                "Top up *{$order->order_number}* belum berhasil diproses.",
                'Dana yang sudah dibayar akan diperiksa operator.',
                'Silakan hubungi Younz Digital Center untuk bantuan.',
            ]),
            default => null,
        };

        if ($message === null) {
            return;
        }

        $gateway->sendText($order->customer_phone, $message);

        if ($this->event !== 'success') {
            return;
        }

        try {
            SendTopupWhatsAppReceipt::dispatch($order->id);
        } catch (\Throwable $exception) {
            // A synchronous queue must not turn a PDF failure into a text failure.
            report($exception);
        }
    }
}
