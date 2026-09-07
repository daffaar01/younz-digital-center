<?php

namespace App\Integrations\Midtrans;

use App\Models\ServiceOrder;
use App\Models\TopupOrder;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MidtransClient
{
    public function isConfigured(): bool
    {
        return (bool) config('services.midtrans.enabled') && filled(config('services.midtrans.server_key'));
    }

    /** @return array{token: string, redirect_url: string} */
    public function createSnapTransaction(TopupOrder $order): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Integrasi Midtrans belum dikonfigurasi.');
        }

        $production = (bool) config('services.midtrans.production');
        $endpoint = $production
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';

        $response = $this->request()
            ->withHeader('X-Override-Notification', $this->notificationUrl())
            ->post($endpoint, [
                'transaction_details' => [
                    'order_id' => $order->midtrans_order_id,
                    'gross_amount' => $order->total_amount,
                ],
                'item_details' => [[
                    'id' => $order->sku,
                    'price' => $order->total_amount,
                    'quantity' => 1,
                    'name' => mb_substr($order->product_name, 0, 50),
                    'category' => mb_substr($order->category, 0, 50),
                ]],
                'customer_details' => [
                    'first_name' => mb_substr($order->customer_name, 0, 50),
                    'email' => $order->customer_email,
                    'phone' => $order->customer_phone,
                ],
                'callbacks' => [
                    'finish' => $order->temporarySignedUrl('topup.show'),
                ],
                'expiry' => [
                    'unit' => 'minutes',
                    'duration' => max(5, min(1440, (int) config('services.midtrans.expiry_minutes', 60))),
                ],
            ])->throw();

        $token = $response->json('token');
        $redirectUrl = $response->json('redirect_url');
        $allowedHosts = $production ? ['app.midtrans.com'] : ['app.sandbox.midtrans.com'];

        if (! is_string($token) || $token === '' || ! is_string($redirectUrl) || ! $this->isAllowedRedirect($redirectUrl, $allowedHosts)) {
            throw new RuntimeException('Respons pembayaran Midtrans tidak valid.');
        }

        return ['token' => $token, 'redirect_url' => $redirectUrl];
    }

    /** @return array{token: string, redirect_url: string} */
    public function createServiceOrderSnapTransaction(ServiceOrder $order): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Integrasi Midtrans belum dikonfigurasi.');
        }

        $productName = (string) ($order->specifications['digital_product_name'] ?? 'Produk Digital');
        $variantLabel = trim((string) ($order->specifications['digital_product_variant_label'] ?? ''));
        $productId = (int) ($order->specifications['digital_product_id'] ?? 0);
        $quantity = (int) ($order->specifications['quantity'] ?? 0);
        $unitPrice = $order->specifications['unit_price_snapshot'] ?? null;
        if ($productId < 1 || $quantity < 1 || ! is_int($unitPrice) || $unitPrice < 1 || $order->estimated_price !== $unitPrice * $quantity) {
            throw new RuntimeException('Data pembayaran produk digital tidak valid.');
        }

        $production = (bool) config('services.midtrans.production');
        $endpoint = $production
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
        $finishUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/').'/cek-pesanan/'.$order->public_token;

        $response = $this->request()
            ->withHeader('X-Override-Notification', $this->notificationUrl())
            ->post($endpoint, [
                'transaction_details' => [
                    'order_id' => $order->midtrans_order_id,
                    'gross_amount' => $order->estimated_price,
                ],
                'item_details' => [[
                    'id' => 'digital-'.$productId,
                    'price' => $unitPrice,
                    'quantity' => $quantity,
                    'name' => mb_substr($variantLabel !== '' ? $productName.' - '.$variantLabel : $productName, 0, 50),
                    'category' => 'Produk Digital',
                ]],
                'customer_details' => [
                    'first_name' => mb_substr($order->customer_name, 0, 50),
                    'phone' => $order->customer_phone,
                ],
                'callbacks' => ['finish' => $finishUrl],
                'expiry' => [
                    'unit' => 'minutes',
                    'duration' => max(5, min(1440, (int) config('services.midtrans.expiry_minutes', 60))),
                ],
            ])->throw();

        $token = $response->json('token');
        $redirectUrl = $response->json('redirect_url');
        $allowedHosts = $production ? ['app.midtrans.com'] : ['app.sandbox.midtrans.com'];
        if (! is_string($token) || $token === '' || ! is_string($redirectUrl) || ! $this->isAllowedRedirect($redirectUrl, $allowedHosts)) {
            throw new RuntimeException('Respons pembayaran Midtrans tidak valid.');
        }

        return ['token' => $token, 'redirect_url' => $redirectUrl];
    }

    /** @return array<string, mixed> */
    public function getTransactionStatus(TopupOrder $order): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Integrasi Midtrans belum dikonfigurasi.');
        }

        $host = (bool) config('services.midtrans.production')
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com';

        $response = $this->request()
            ->get($host.'/v2/'.rawurlencode($order->midtrans_order_id).'/status')
            ->throw();
        $payload = $response->json();

        if (is_array($payload) && (string) ($payload['status_code'] ?? '') === '404') {
            throw new RuntimeException('Transaksi belum tercatat di Midtrans.');
        }

        if (! is_array($payload)
            || (string) ($payload['order_id'] ?? '') !== $order->midtrans_order_id
            || ! preg_match('/^\d+(?:\.\d{1,2})?$/', (string) ($payload['gross_amount'] ?? ''))
            || (int) round((float) $payload['gross_amount'] * 100) !== $order->total_amount * 100
            || trim((string) ($payload['transaction_status'] ?? '')) === ''
        ) {
            throw new RuntimeException('Respons status transaksi Midtrans tidak valid.');
        }

        return $payload;
    }

    /** @return array<string, mixed>|null Null means Midtrans explicitly confirmed the order ID is not recorded. */
    public function getServiceOrderTransactionStatus(ServiceOrder $order): ?array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Integrasi Midtrans belum dikonfigurasi.');
        }

        $production = (bool) config('services.midtrans.production');
        $host = $production ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';
        $response = $this->request()->get($host.'/v2/'.rawurlencode((string) $order->midtrans_order_id).'/status');
        if ($response->status() === 404 || (string) $response->json('status_code') === '404') {
            return null;
        }
        $response->throw();
        $payload = $response->json();
        if (! is_array($payload)
            || (string) ($payload['order_id'] ?? '') !== $order->midtrans_order_id
            || ! preg_match('/^\d+(?:\.\d{1,2})?$/', (string) ($payload['gross_amount'] ?? ''))
            || (int) round((float) $payload['gross_amount'] * 100) !== (int) $order->estimated_price * 100
            || trim((string) ($payload['transaction_status'] ?? '')) === '') {
            throw new RuntimeException('Respons status transaksi Midtrans tidak valid.');
        }
        if (filled($payload['redirect_url'] ?? null)) {
            $allowedHosts = $production ? ['app.midtrans.com'] : ['app.sandbox.midtrans.com'];
            if (! is_string($payload['redirect_url']) || ! $this->isAllowedRedirect($payload['redirect_url'], $allowedHosts)) {
                throw new RuntimeException('URL pembayaran hasil rekonsiliasi Midtrans tidak valid.');
            }
        }

        return $payload;
    }

    /** @param list<string> $allowedHosts */
    private function isAllowedRedirect(string $url, array $allowedHosts): bool
    {
        return parse_url($url, PHP_URL_SCHEME) === 'https'
            && in_array(parse_url($url, PHP_URL_HOST), $allowedHosts, true);
    }

    private function request(): PendingRequest
    {
        return Http::withBasicAuth((string) config('services.midtrans.server_key'), '')
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout(30);
    }

    private function notificationUrl(): string
    {
        $url = trim((string) config('services.midtrans.notification_url'));
        $parts = $url === '' ? false : parse_url($url);

        if (! is_array($parts)
            || ($parts['scheme'] ?? null) !== 'https'
            || ! filled($parts['host'] ?? null)
            || isset($parts['user'], $parts['pass'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)
        ) {
            throw new RuntimeException('URL notifikasi Midtrans harus berupa alamat HTTPS publik yang valid.');
        }

        return $url;
    }
}
