<?php

namespace Tests\Feature;

use App\Integrations\Discord\DiscordNotifier;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiscordNotifierTest extends TestCase
{
    private const TOKEN = 'discord-webhook-token-that-is-at-least-32-chars';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.discord.enabled' => true,
            'services.discord.webhook_url' => 'http://203.0.113.10:3200',
            'services.discord.token' => self::TOKEN,
            'services.discord.timeout' => 3,
        ]);
    }

    public function test_notification_signs_and_sends_the_exact_json_body(): void
    {
        Http::fake([
            'http://203.0.113.10:3200/notify' => Http::response(['status' => 'sent']),
        ]);

        $payload = [
            'event' => 'topup.paid',
            'title' => 'Top Up Berhasil',
            'message' => 'Pesanan dibayar.',
            'fields' => [
                ['name' => 'Produk', 'value' => 'Pulsa', 'inline' => true],
            ],
            'amount' => 25000,
            'reference' => 'ORDER-123',
            'mention_staff' => true,
        ];

        $this->assertTrue(app(DiscordNotifier::class)->notify(
            event: $payload['event'],
            title: $payload['title'],
            message: $payload['message'],
            fields: $payload['fields'],
            amount: $payload['amount'],
            reference: $payload['reference'],
            mentionStaff: $payload['mention_staff'],
        ));

        Http::assertSent(function (Request $request) use ($payload): bool {
            $timestamp = $request->header('X-Younz-Timestamp')[0] ?? '';

            return $request->url() === 'http://203.0.113.10:3200/notify'
                && $request->hasHeader('Authorization', 'Bearer '.self::TOKEN)
                && preg_match('/^\d{10}$/', $timestamp) === 1
                && $request->body() === json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                && $request->header('X-Younz-Signature')[0] === hash_hmac(
                    'sha256',
                    $timestamp.'.'.$request->body(),
                    self::TOKEN,
                );
        });
    }

    public function test_health_check_does_not_send_webhook_signature_headers(): void
    {
        Http::fake([
            'http://203.0.113.10:3200/health' => Http::response(['ready' => true]),
        ]);

        $this->assertTrue(app(DiscordNotifier::class)->isHealthy());

        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://203.0.113.10:3200/health'
            && ! $request->hasHeader('X-Younz-Timestamp')
            && ! $request->hasHeader('X-Younz-Signature'));
    }
}
