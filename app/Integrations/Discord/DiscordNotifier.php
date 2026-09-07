<?php

namespace App\Integrations\Discord;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Klien notifikasi bot Discord Younz Digital Center.
 *
 * Bot berjalan sebagai layanan terpisah dan menerima payload lewat webhook
 * internal. Kegagalan pengiriman tidak boleh menggagalkan alur bisnis, jadi
 * setiap error hanya dicatat pada log.
 */
class DiscordNotifier
{
    public function isEnabled(): bool
    {
        return (bool) config('services.discord.enabled')
            && filled(config('services.discord.webhook_url'))
            && strlen((string) config('services.discord.token')) >= 32;
    }

    /**
     * Mengirim notifikasi ke channel Discord.
     *
     * @param  list<array{name: string, value: string, inline?: bool}>  $fields
     */
    public function notify(
        string $event,
        string $title,
        string $message = '',
        array $fields = [],
        ?int $amount = null,
        ?string $reference = null,
        bool $mentionStaff = false,
        ?string $channelId = null,
    ): bool {
        if (! $this->isEnabled()) {
            return false;
        }

        $payload = array_filter([
            'event' => $event,
            'title' => $title,
            'message' => $message !== '' ? $message : null,
            'fields' => $fields !== [] ? array_values($fields) : null,
            'amount' => $amount,
            'reference' => $reference,
            'mention_staff' => $mentionStaff ?: null,
            'channel_id' => $channelId,
        ], static fn ($value) => $value !== null);

        try {
            $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $this->signedRequest($body)->post('/notify')->throw();

            return true;
        } catch (Throwable $exception) {
            Log::warning('Notifikasi Discord gagal dikirim.', [
                'event' => $event,
                'reason' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function isHealthy(): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        try {
            $response = $this->request()->get('/health');

            return $response->successful() && $response->json('ready') === true;
        } catch (Throwable) {
            return false;
        }
    }

    private function signedRequest(string $body): PendingRequest
    {
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac(
            'sha256',
            $timestamp.'.'.$body,
            (string) config('services.discord.token'),
        );

        return $this->request()
            ->withBody($body, 'application/json')
            ->withHeaders([
                'X-Younz-Timestamp' => $timestamp,
                'X-Younz-Signature' => $signature,
            ]);
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.discord.webhook_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->withToken((string) config('services.discord.token'))
            ->connectTimeout(3)
            ->timeout((int) config('services.discord.timeout', 8))
            ->retry(2, 250, throw: false);
    }
}
