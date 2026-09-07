<?php

namespace App\Integrations\WhatsApp;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppGatewayClient
{
    public function isConfigured(): bool
    {
        return strlen((string) config('services.whatsapp.internal_token')) >= 16
            && filled(config('services.whatsapp.gateway_url'));
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        return $this->request()->get('/v1/status')->throw()->json();
    }

    /** @return array<string, mixed> */
    public function reconnect(): array
    {
        return $this->request()->post('/v1/reconnect')->throw()->json();
    }

    /** @return array<string, mixed> */
    public function logout(): array
    {
        return $this->request()->post('/v1/logout')->throw()->json();
    }

    public function sendText(string $phone, string $text): ?string
    {
        $response = $this->request()->post('/v1/messages', [
            'to' => $this->normalizePhone($phone),
            'text' => $text,
        ])->throw()->json();

        return $response['messageId'] ?? null;
    }

    public function sendDocument(string $phone, string $url, string $filename, ?string $caption = null): ?string
    {
        $response = $this->request()->post('/v1/documents', array_filter([
            'to' => $this->normalizePhone($phone),
            'url' => $url,
            'filename' => $filename,
            'caption' => $caption,
        ], fn ($value) => $value !== null))->throw()->json();

        return $response['messageId'] ?? null;
    }

    /** @param list<array{id: string, label: string, description?: string}> $buttons @param array{label: string, url: string}|null $link */
    public function sendMenu(
        string $phone,
        string $title,
        string $body,
        array $buttons,
        ?array $link = null,
        string $footer = 'Younz Digital Center',
    ): ?string {
        $response = $this->request()->post('/v1/menu', array_filter([
            'to' => $this->normalizePhone($phone),
            'title' => $title,
            'body' => $body,
            'footer' => $footer,
            'buttons' => $buttons,
            'link' => $link,
        ], fn ($value) => $value !== null))->throw()->json();

        return $response['messageId'] ?? null;
    }

    private function request(): PendingRequest
    {
        $token = (string) config('services.whatsapp.internal_token');
        if (strlen($token) < 16) {
            throw new RuntimeException('WhatsApp gateway belum dikonfigurasi.');
        }

        return Http::baseUrl(rtrim((string) config('services.whatsapp.gateway_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->withToken($token)
            ->connectTimeout(3)
            ->timeout((int) config('services.whatsapp.timeout', 10));
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }
        if (! str_starts_with($digits, '62') || strlen($digits) < 10 || strlen($digits) > 15) {
            throw new RuntimeException('Nomor WhatsApp pelanggan tidak valid.');
        }

        return $digits;
    }
}
