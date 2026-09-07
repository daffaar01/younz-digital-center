<?php

namespace Tests\Feature;

use App\Enums\DigiflazzTransactionType;
use App\Enums\UserRole;
use App\Integrations\WhatsApp\WhatsAppGatewayClient;
use App\Jobs\ProcessWhatsAppInboundMessage;
use App\Jobs\SendTopupWhatsAppNotification;
use App\Jobs\SendTopupWhatsAppReceipt;
use App\Models\DigiflazzProduct;
use App\Models\TopupOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class WhatsAppGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.whatsapp.gateway_url', 'http://whatsapp.test');
        config()->set('services.whatsapp.internal_token', 'test-internal-token-123456');
    }

    public function test_owner_can_open_gateway_qr_but_cashier_cannot(): void
    {
        Http::fake([
            'http://whatsapp.test/v1/status' => Http::response([
                'state' => 'qr',
                'connected' => false,
                'qr' => 'data:image/png;base64,dGVzdA==',
                'account' => null,
                'lastError' => null,
            ]),
        ]);

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);

        $this->actingAs($owner)->get(route('whatsapp.index'))
            ->assertOk()
            ->assertSee('WhatsApp Gateway')
            ->assertSee('data:image/png;base64,dGVzdA==', false);
        $this->actingAs($cashier)->get(route('whatsapp.index'))->assertForbidden();
    }

    public function test_gateway_client_authenticates_and_normalizes_indonesian_phone(): void
    {
        Http::fake([
            'http://whatsapp.test/v1/messages' => Http::response(['accepted' => true, 'messageId' => 'message-1'], 202),
        ]);

        $messageId = app(WhatsAppGatewayClient::class)->sendText('0812-3456-7890', 'Status diperbarui.');

        $this->assertSame('message-1', $messageId);
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer test-internal-token-123456')
            && $request['to'] === '6281234567890'
            && $request['text'] === 'Status diperbarui.');
    }

    public function test_gateway_client_can_send_a_pdf_document_from_a_signed_url(): void
    {
        Http::fake([
            'http://whatsapp.test/v1/documents' => Http::response(['accepted' => true, 'messageId' => 'document-1'], 202),
        ]);

        $messageId = app(WhatsAppGatewayClient::class)->sendDocument(
            '0812-3456-7890',
            'https://younzdigitalcenter.my.id/topup/status/token/struk.pdf?expires=1786000000&signature=test',
            'TOP-20260721-0001.pdf',
            'Struk PDF transaksi TOP-20260721-0001',
        );

        $this->assertSame('document-1', $messageId);
        Http::assertSent(fn (Request $request) => $request->url() === 'http://whatsapp.test/v1/documents'
            && $request->hasHeader('Authorization', 'Bearer test-internal-token-123456')
            && $request['to'] === '6281234567890'
            && $request['url'] === 'https://younzdigitalcenter.my.id/topup/status/token/struk.pdf?expires=1786000000&signature=test'
            && $request['filename'] === 'TOP-20260721-0001.pdf'
            && $request['caption'] === 'Struk PDF transaksi TOP-20260721-0001');
    }

    public function test_topup_success_sends_text_even_when_pdf_delivery_fails(): void
    {
        Http::fake([
            'http://whatsapp.test/v1/messages' => Http::response(['accepted' => true, 'messageId' => 'text-1'], 202),
            'http://whatsapp.test/v1/documents' => Http::response(['message' => 'Gateway error'], 500),
        ]);
        $product = DigiflazzProduct::query()->create([
            'transaction_type' => DigiflazzTransactionType::Prepaid,
            'buyer_sku_code' => 'TELKOMSEL10',
            'product_name' => 'Pulsa Telkomsel 10.000',
            'category' => 'Pulsa',
            'brand' => 'Telkomsel',
            'cost_price' => 10000,
            'selling_price' => 11000,
            'buyer_product_status' => true,
            'seller_product_status' => true,
            'unlimited_stock' => true,
            'stock' => 0,
            'multi' => false,
        ]);
        $order = TopupOrder::query()->create([
            'transaction_type' => DigiflazzTransactionType::Prepaid,
            'order_number' => 'TOP-20260721-0001',
            'public_token' => '8fce8f7c-62b4-4c87-9af6-a3cb6e1c9a32',
            'digiflazz_product_id' => $product->id,
            'idempotency_key' => (string) Str::uuid(),
            'request_fingerprint' => hash('sha256', 'topup-success'),
            'source_reference' => 'whatsapp:'.hash('sha256', 'topup-success'),
            'sku' => $product->buyer_sku_code,
            'customer_phone' => '081234567890',
            'customer_name' => 'Daffa',
            'customer_email' => 'whatsapp@example.invalid',
            'product_name' => $product->product_name,
            'category' => $product->category,
            'brand' => $product->brand,
            'destination' => '081234567890',
            'cost_price' => 10000,
            'selling_price' => 11000,
            'admin_fee' => 0,
            'total_amount' => 11000,
            'payment_status' => 'paid',
            'fulfillment_status' => 'success',
            'midtrans_order_id' => 'TOP-20260721-0001',
            'digiflazz_reference' => 'TOP-20260721-0001',
            'paid_at' => now(),
        ]);

        (new SendTopupWhatsAppNotification($order->id, 'success'))
            ->handle(app(WhatsAppGatewayClient::class));

        Http::assertSent(fn (Request $request) => $request->url() === 'http://whatsapp.test/v1/messages'
            && str_contains((string) $request['text'], 'berhasil'));
        Http::assertSent(fn (Request $request) => $request->url() === 'http://whatsapp.test/v1/documents'
            && str_ends_with((string) $request['filename'], '.pdf')
            && str_contains((string) $request['url'], '/topup/status/'));
    }

    public function test_topup_receipt_job_has_a_distinct_retryable_identity(): void
    {
        $job = new SendTopupWhatsAppReceipt(123);

        $this->assertSame('topup:123:receipt-pdf', $job->uniqueId());
        $this->assertSame(5, $job->tries);
        $this->assertSame(45, $job->timeout);
    }

    public function test_inbound_webhook_is_authenticated_deduplicated_and_rejects_groups(): void
    {
        Queue::fake();
        $payload = [
            'messageId' => 'message-inbound-1',
            'chatJid' => '6281234567890@s.whatsapp.net',
            'senderJid' => '6281234567890@s.whatsapp.net',
            'text' => 'Berapa harga print A4?',
            'timestamp' => now()->timestamp,
        ];

        $this->postJson(route('webhooks.whatsapp.messages'), $payload)->assertUnauthorized();
        $this->withToken('test-internal-token-123456')
            ->postJson(route('webhooks.whatsapp.messages'), $payload)
            ->assertAccepted();
        $this->withToken('test-internal-token-123456')
            ->postJson(route('webhooks.whatsapp.messages'), $payload)
            ->assertOk()
            ->assertJson(['duplicate' => true]);

        Queue::assertPushed(ProcessWhatsAppInboundMessage::class, 1);

        $this->withToken('test-internal-token-123456')->postJson(route('webhooks.whatsapp.messages'), [
            ...$payload,
            'messageId' => 'group-message-1',
            'chatJid' => '123456789@g.us',
        ])->assertOk()->assertJson(['accepted' => false, 'reason' => 'unsupported_chat']);
        Queue::assertPushed(ProcessWhatsAppInboundMessage::class, 1);
    }

    public function test_legacy_pesan_topup_and_pilih_commands_are_disabled(): void
    {
        config()->set('services.digiflazz.enabled', true);
        config()->set('services.digiflazz.username', 'test-user');
        config()->set('services.digiflazz.api_key', 'test-key');
        config()->set('services.midtrans.enabled', true);
        config()->set('services.midtrans.production', false);
        config()->set('services.midtrans.server_key', 'server-key');
        config()->set('services.midtrans.notification_url', 'https://example.test/webhooks/midtrans');

        $product = DigiflazzProduct::query()->create([
            'transaction_type' => DigiflazzTransactionType::Prepaid,
            'buyer_sku_code' => 'TELKOMSEL10',
            'product_name' => 'Telkomsel 10.000',
            'category' => 'Pulsa',
            'brand' => 'TELKOMSEL',
            'cost_price' => 10000,
            'selling_price' => 12000,
            'buyer_product_status' => true,
            'seller_product_status' => true,
            'unlimited_stock' => true,
            'stock' => 0,
            'multi' => false,
        ]);

        Http::fake([
            'http://whatsapp.test/v1/messages' => Http::response(['accepted' => true, 'messageId' => 'reply-1'], 202),
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'snap-token',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/snap-token',
            ]),
        ]);

        foreach (['TOPUP Telkomsel 081234567890', 'PILIH 1', 'PESAN print A4', '/topup', '/pesan'] as $index => $text) {
            $message = new ProcessWhatsAppInboundMessage(
                'disabled-command-'.$index,
                '6281234567890@s.whatsapp.net',
                '6281234567890@s.whatsapp.net',
                $text,
            );
            app()->call([$message, 'handle']);
        }

        $this->assertDatabaseCount('topup_orders', 0);
        $this->assertDatabaseCount('service_orders', 0);
        Http::assertSentCount(5);
        Http::assertSent(fn (Request $request) => str_contains((string) $request['text'], 'sudah dinonaktifkan'));
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'midtrans'));
    }

    public function test_menu_command_sends_interactive_whatsapp_menu(): void
    {
        Http::fake([
            'http://whatsapp.test/v1/menu' => Http::response(['accepted' => true, 'messageId' => 'menu-1'], 202),
        ]);

        $message = new ProcessWhatsAppInboundMessage(
            'menu-message-1',
            '6281234567890@s.whatsapp.net',
            '6281234567890@s.whatsapp.net',
            '/menu',
        );
        app()->call([$message, 'handle']);

        Http::assertSent(fn (Request $request) => $request->url() === 'http://whatsapp.test/v1/menu'
            && $request['title'] === 'Menu Younz Digital Center'
            && $request['buttons'] === [
                ['id' => '/tanya', 'label' => 'Tanya Younz AI', 'description' => 'Konsultasi layanan dan informasi Younz'],
                ['id' => '/cek', 'label' => 'Cek Pesanan', 'description' => 'Buka petunjuk pelacakan pesanan'],
            ]
            && ! isset($request['link']));
    }
}
