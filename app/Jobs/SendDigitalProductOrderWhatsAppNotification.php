<?php

namespace App\Jobs;

use App\Integrations\WhatsApp\WhatsAppGatewayClient;
use App\Models\ServiceOrder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

class SendDigitalProductOrderWhatsAppNotification implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 30;
    public array $backoff = [30, 120, 300, 900];
    public int $uniqueFor = 86400;

    public function __construct(public readonly int $orderId)
    {
        // Keep the checkout response fast and retain retries with a sync app default.
        $this->onConnection('database');
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return "digital-product-order:{$this->orderId}:admin-notification";
    }

    public function handle(WhatsAppGatewayClient $gateway): void
    {
        if (! $gateway->isConfigured()) {
            throw new RuntimeException('WhatsApp admin notification is not configured.');
        }

        $order = ServiceOrder::query()->find($this->orderId);
        if (! $order || $order->type !== 'digital') {
            return;
        }

        $adminPhone = trim((string) config('services.whatsapp.number'));
        if ($adminPhone === '') {
            throw new RuntimeException('Nomor WhatsApp admin belum dikonfigurasi.');
        }

        $specifications = $order->specifications ?? [];
        $variant = trim((string) ($specifications['digital_product_variant_label'] ?? ''));
        $paymentStatus = (string) ($order->payment_status ?? 'unpaid');
        $paymentLabel = match ($paymentStatus) {
            'paid' => 'Sudah dibayar',
            'pending' => 'Menunggu pembayaran Midtrans',
            default => 'Perlu pemeriksaan harga/operator',
        };
        $message = implode("\n", array_filter([
            '*PESANAN DIGITAL BARU*',
            'Pesanan masuk dari aplikasi Younz Digital Center.',
            '',
            'Nomor: '.$order->order_number,
            'Produk: '.($specifications['digital_product_name'] ?? 'Produk Digital'),
            $variant !== '' ? 'Varian: '.$variant : null,
            'Kuantitas: '.max(1, (int) ($specifications['quantity'] ?? 1)),
            $order->estimated_price !== null
                ? 'Total: Rp'.number_format((int) $order->estimated_price, 0, ',', '.')
                : 'Total: Menunggu konfirmasi',
            'Status pembayaran: '.$paymentLabel,
            '',
            'Pelanggan: '.$order->customer_name,
            'Kontak: '.$order->customer_phone,
        ], fn ($line): bool => $line !== null));

        $gateway->sendText($adminPhone, $message);
    }
}
