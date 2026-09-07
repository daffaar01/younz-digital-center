<?php

namespace App\Integrations\Digiflazz;

use App\Enums\DigiflazzTransactionType;
use App\Models\TopupOrder;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DigiflazzClient
{
    public function isConfigured(): bool
    {
        return (bool) config('services.digiflazz.enabled')
            && filled(config('services.digiflazz.username'))
            && filled(config('services.digiflazz.api_key'));
    }

    /** @return list<array<string, mixed>> */
    public function priceList(DigiflazzTransactionType $type = DigiflazzTransactionType::Prepaid): array
    {
        $this->ensureConfigured();
        $username = (string) config('services.digiflazz.username');

        $response = $this->request()->post('/price-list', [
            'cmd' => $type === DigiflazzTransactionType::Postpaid ? 'pasca' : 'prepaid',
            'username' => $username,
            'sign' => md5($username.(string) config('services.digiflazz.api_key').'pricelist'),
        ])->throw();
        $data = $response->json('data');

        if (! is_array($data) || ! array_is_list($data)) {
            $message = is_array($data) ? trim((string) ($data['message'] ?? '')) : '';
            throw new RuntimeException($message !== '' ? "Digiflazz: {$message}" : 'Respons katalog Digiflazz tidak valid.');
        }

        return array_values(array_filter($data, 'is_array'));
    }

    /** @return array<string, mixed> */
    public function balance(): array
    {
        $this->ensureConfigured();
        $username = (string) config('services.digiflazz.username');
        $data = $this->request()->post('/cek-saldo', [
            'cmd' => 'deposit',
            'username' => $username,
            'sign' => md5($username.(string) config('services.digiflazz.api_key').'depo'),
        ])->throw()->json('data');

        if (! is_array($data)) {
            throw new RuntimeException('Respons saldo Digiflazz tidak valid.');
        }

        return $data;
    }

    /** @return array<string, mixed> */
    public function topup(TopupOrder $order): array
    {
        $this->ensureConfigured();
        $username = (string) config('services.digiflazz.username');
        $reference = $order->digiflazz_reference ?: $order->order_number;
        $payload = [
            'username' => $username,
            'buyer_sku_code' => $order->sku,
            'customer_no' => $order->destination,
            'ref_id' => $reference,
            'sign' => md5($username.(string) config('services.digiflazz.api_key').$reference),
            'max_price' => $order->cost_price,
            'cb_url' => route('webhooks.digiflazz'),
        ];

        if ((bool) config('services.digiflazz.testing')) {
            $payload['testing'] = true;
        }

        $data = $this->request()->post('/transaction', $payload)->throw()->json('data');

        if (! is_array($data)) {
            throw new RuntimeException('Respons transaksi Digiflazz tidak valid.');
        }

        return $data;
    }

    /** @return array<string, mixed> */
    public function inquirePostpaid(TopupOrder $order): array
    {
        return $this->postpaidTransaction($order, 'inq-pasca');
    }

    /** @return array<string, mixed> */
    public function payPostpaid(TopupOrder $order): array
    {
        return $this->postpaidTransaction($order, 'pay-pasca');
    }

    /** @return array<string, mixed> */
    public function statusPostpaid(TopupOrder $order): array
    {
        return $this->postpaidTransaction($order, 'status-pasca');
    }

    /**
     * Normalize current Digiflazz pasca responses and legacy test responses.
     * @param array<string, mixed> $inquiry
     * @return array{successful: bool, cost: int, selling_price: int, rc: ?string, message: ?string}
     */
    public function normalizePostpaidInquiry(array $inquiry): array
    {
        $rc = filled($inquiry['response_code'] ?? null)
            ? (string) $inquiry['response_code']
            : (filled($inquiry['rc'] ?? null) ? (string) $inquiry['rc'] : null);
        $status = mb_strtolower(trim((string) ($inquiry['status'] ?? '')));
        $successful = $status === 'sukses' || $rc === '00';
        $cost = max(0, (int) ($inquiry['price'] ?? $inquiry['amount'] ?? 0));
        $sellingPrice = max(0, (int) ($inquiry['selling_price'] ?? 0));
        if ($sellingPrice <= 0 && $successful) {
            $sellingPrice = $cost;
        }

        return [
            'successful' => $successful,
            'cost' => $cost,
            'selling_price' => $sellingPrice,
            'rc' => $rc,
            'message' => filled($inquiry['message'] ?? null) ? (string) $inquiry['message'] : null,
        ];
    }

    /** @return array<string, mixed> */
    private function postpaidTransaction(TopupOrder $order, string $command): array
    {
        $this->ensureConfigured();
        $username = (string) config('services.digiflazz.username');
        $reference = $order->digiflazz_reference ?: $order->order_number;
        $payload = [
            'commands' => $command,
            'username' => $username,
            'buyer_sku_code' => $order->sku,
            'customer_no' => $order->destination,
            'ref_id' => $reference,
            'sign' => md5($username.(string) config('services.digiflazz.api_key').$reference),
        ];

        if ((bool) config('services.digiflazz.testing')) {
            $payload['testing'] = true;
        }

        $data = $this->request()->post('/transaction', $payload)->throw()->json('data');

        if (! is_array($data)) {
            throw new RuntimeException('Respons transaksi pascabayar Digiflazz tidak valid.');
        }

        return $data;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.digiflazz.endpoint'), '/'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout(30)
            ->retry(2, 250, throw: false);
    }

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Integrasi Digiflazz belum dikonfigurasi.');
        }
    }
}
