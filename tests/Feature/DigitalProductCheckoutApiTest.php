<?php

namespace Tests\Feature;

use App\Enums\ServiceOrderStatus;
use App\Integrations\WhatsApp\WhatsAppGatewayClient;
use App\Jobs\SendDigitalProductOrderWhatsAppNotification;
use App\Models\Customer;
use App\Models\DigitalProduct;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DigitalProductCheckoutApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set([
            'services.midtrans.enabled' => true,
            'services.midtrans.server_key' => 'midtrans-service-test-key',
            'services.midtrans.production' => false,
            'services.midtrans.expiry_minutes' => 60,
            'services.midtrans.notification_url' => 'https://example.test/webhooks/midtrans',
            'services.whatsapp.gateway_url' => 'http://whatsapp.test',
            'services.whatsapp.internal_token' => 'test-internal-token-123456',
            'services.whatsapp.number' => '6281234567890',
        ]);
        Http::fake([
            'app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'android-snap-token',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/android-snap-token',
            ]),
            'http://whatsapp.test/v1/messages' => Http::response([
                'accepted' => true,
                'messageId' => 'wa-message',
            ], 202),
        ]);
    }

    public function test_authenticated_customer_can_create_digital_order_without_sending_customer_whatsapp_data(): void
    {
        Queue::fake();
        $user = User::factory()->create([
            'role' => 'customer',
            'email_verified_at' => now(),
            'phone' => '081234567890',
        ]);
        $customer = Customer::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'type' => 'umum',
        ]);
        $product = DigitalProduct::create([
            'name' => 'ChatGPT Plus',
            'category' => 'AI Assistant',
            'mark' => 'CG',
            'image_path' => '/products/chatgpt.svg',
            'image_disk' => 'local',
            'stock' => 5,
            'price' => 99000,
            'description' => 'Akses AI.',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        Sanctum::actingAs($user, ['profile:read', 'orders:read', 'orders:write']);

        $response = $this->postJson('/api/v1/customer/digital-product-orders', [
            'type' => 'digital',
            'source' => 'android-app',
            'product_id' => $product->id,
            'quantity' => 2,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated()
            ->assertJsonPath('payment.required', true)
            ->assertJsonPath('payment.amount', 198000)
            ->assertJsonPath('payment.redirect_url', 'https://app.sandbox.midtrans.com/snap/v4/redirection/android-snap-token');
        Queue::assertPushed(SendDigitalProductOrderWhatsAppNotification::class, 1);

        $order = ServiceOrder::query()->firstOrFail();
        $this->assertSame($customer->id, $order->customer_id);
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertSame('android-app', $order->specifications['acquisition_source']);
        $response->assertJsonPath('data.id', $order->id);

        $this->getJson('/api/v1/customer/digital-product-orders/'.$order->id.'/status')
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.amount', 198000);
    }

    public function test_paid_digital_order_notifies_admin_whatsapp_once_and_status_is_visible_to_customer(): void
    {
        Queue::fake();
        $user = User::factory()->create(['role' => 'customer', 'phone' => '081234567890']);
        $customer = Customer::create([
            'user_id' => $user->id,
            'name' => 'Pelanggan App',
            'email' => $user->email,
            'phone' => '081234567890',
            'type' => 'umum',
        ]);
        $product = DigitalProduct::create([
            'name' => 'Video Premium', 'category' => 'Streaming', 'mark' => 'VP',
            'image_path' => '/products/video-premium.svg', 'image_disk' => 'local',
            'stock' => 4, 'price' => 50000, 'description' => 'Akses video.',
            'is_active' => true, 'sort_order' => 1,
        ]);
        Sanctum::actingAs($user, ['orders:read', 'orders:write']);
        $this->postJson('/api/v1/customer/digital-product-orders', [
            'type' => 'digital', 'source' => 'android-app', 'product_id' => $product->id,
            'quantity' => 1, 'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated();
        $order = ServiceOrder::firstOrFail();
        $this->assertSame(50000, $order->estimated_price);
        $this->assertNotEmpty($order->midtrans_order_id);
        Queue::assertPushed(SendDigitalProductOrderWhatsAppNotification::class, 1);

        (new SendDigitalProductOrderWhatsAppNotification($order->id))
            ->handle(app(WhatsAppGatewayClient::class));
        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://whatsapp.test/v1/messages'
            && $request['to'] === '6281234567890'
            && str_contains((string) $request['text'], 'PESANAN DIGITAL BARU')
            && str_contains((string) $request['text'], $order->order_number));

        Http::fake(function (Request $request) use ($order) {
            if (str_contains((string) $request->url(), '/status')) {
                return Http::response([
                'order_id' => $order->midtrans_order_id,
                'gross_amount' => '50000.00',
                'transaction_status' => 'settlement',
                'status_code' => '200',
                'fraud_status' => 'accept',
                'payment_type' => 'qris',
                'transaction_id' => 'android-transaction-1',
                ]);
            }

            return Http::response(['accepted' => true, 'messageId' => 'wa-message'], 202);
        });
        $refresh = $this->postJson('/api/v1/customer/digital-product-orders/'.$order->id.'/refresh-payment');
        $refresh
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'paid');
        Queue::assertPushed(SendDigitalProductOrderWhatsAppNotification::class, 1);

        $this->getJson('/api/v1/customer/digital-product-orders/'.$order->id.'/status')
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.payment_terminal', true)
            ->assertJsonPath('data.order_status.code', ServiceOrderStatus::Queued->value);
        $this->assertSame($customer->id, $order->fresh()->customer_id);
    }
}
