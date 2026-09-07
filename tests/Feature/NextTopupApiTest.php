<?php

namespace Tests\Feature;

use App\Enums\DigiflazzTransactionType;
use App\Models\DigiflazzProduct;
use App\Models\TopupOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class NextTopupApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set([
            'services.digiflazz.enabled' => true,
            'services.digiflazz.username' => 'buyer-test',
            'services.digiflazz.api_key' => 'digiflazz-key',
            'services.midtrans.enabled' => true,
            'services.midtrans.server_key' => 'midtrans-key',
            'services.midtrans.production' => false,
            'services.midtrans.notification_url' => 'https://example.test/webhooks/midtrans',
        ]);
    }

    public function test_catalog_api_exposes_public_product_data(): void
    {
        $product = $this->product();
        $this->getJson('/api/v1/topup/catalog?mode=prepaid')
            ->assertOk()
            ->assertJsonPath('data.products.0.id', $product->id)
            ->assertJsonPath('data.integrations_ready', true)
            ->assertJsonMissingPath('data.products.0.cost_price')
            ->assertJsonMissingPath('data.products.0.buyer_sku_code');
    }

    public function test_postpaid_catalog_exposes_postpaid_products_and_form_metadata(): void
    {
        $product = DigiflazzProduct::create([
            'transaction_type' => DigiflazzTransactionType::Postpaid,
            'buyer_sku_code' => 'PLNPOSTNEXT',
            'product_name' => 'PLN Pascabayar',
            'category' => 'Pascabayar',
            'brand' => 'PLN',
            'cost_price' => 0,
            'selling_price' => 0,
            'buyer_product_status' => true,
            'seller_product_status' => true,
            'unlimited_stock' => true,
            'stock' => 0,
            'multi' => false,
        ]);

        $this->getJson('/api/v1/topup/catalog?mode=postpaid')
            ->assertOk()
            ->assertJsonPath('data.mode.value', 'postpaid')
            ->assertJsonPath('data.products.0.id', $product->id)
            ->assertJsonPath('data.products.0.form_type', 'postpaid');
    }

    public function test_pln_token_catalog_exposes_pln_form_metadata(): void
    {
        $product = $this->product([
            'product_name' => 'PLN Token 20.000',
            'category' => 'PLN',
        ]);

        $this->getJson('/api/v1/topup/catalog?mode=prepaid')
            ->assertOk()
            ->assertJsonPath('data.products.0.id', $product->id)
            ->assertJsonPath('data.products.0.form_type', 'pln_token');
    }

    public function test_game_tokens_keep_the_general_destination_form(): void
    {
        $product = $this->product([
            'product_name' => 'Honor of Kings 80 Tokens',
            'category' => 'Games',
        ]);

        $this->getJson('/api/v1/topup/catalog?mode=prepaid')
            ->assertOk()
            ->assertJsonPath('data.products.0.id', $product->id)
            ->assertJsonPath('data.products.0.form_type', 'general');
    }

    public function test_catalog_does_not_hide_products_after_the_first_hundred_results(): void
    {
        foreach (range(1, 101) as $index) {
            $this->product([
                'buyer_sku_code' => 'GAME'.$index,
                'product_name' => 'Game Product '.$index,
                'category' => 'Games',
            ]);
        }
        $pulsa = $this->product([
            'buyer_sku_code' => 'PULSALATE',
            'product_name' => 'Pulsa Terakhir 10.000',
            'category' => 'Pulsa',
        ]);

        $this->getJson('/api/v1/topup/catalog?mode=prepaid')
            ->assertOk()
            ->assertJsonCount(102, 'data.products')
            ->assertJsonFragment([
                'id' => $pulsa->id,
                'form_type' => 'mobile',
            ]);
    }

    public function test_next_checkout_creates_order_and_returns_midtrans_url(): void
    {
        $product = $this->product();
        Http::fake(['app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
            'token' => 'snap-next',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/next',
        ], 201)]);

        $this->postJson('/api/v1/topup/checkout', [
            'idempotency_key' => (string) Str::uuid(),
            'product_id' => $product->id,
            'destination' => '08219207240',
            'destination_confirmation' => '08219207240',
            'customer_name' => 'Daffa',
            'customer_email' => 'daffa@example.com',
            'customer_phone' => '08219207240',
            'terms' => '1',
        ])->assertCreated()
            ->assertJsonPath('redirect_url', 'https://app.sandbox.midtrans.com/snap/v4/redirection/next')
            ->assertJsonPath('data.total_amount', 11000)
            ->assertJsonMissingPath('data.cost_price');

        $this->assertCount(1, TopupOrder::all());
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://app.sandbox.midtrans.com/snap/v1/transactions');
    }

    public function test_next_frontend_can_recover_and_render_a_signed_topup_status(): void
    {
        $product = $this->product();
        Http::fake(['app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
            'token' => 'snap-next-status',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/status',
        ], 201)]);

        $this->postJson('/api/v1/topup/checkout', [
            'idempotency_key' => (string) Str::uuid(),
            'product_id' => $product->id,
            'destination' => '08219207240',
            'destination_confirmation' => '08219207240',
            'customer_name' => 'Daffa',
            'customer_email' => 'daffa@example.com',
            'customer_phone' => '08219207240',
            'terms' => '1',
        ])->assertCreated();

        $order = TopupOrder::query()->sole();
        $access = $this->postJson('/api/v1/topup/access', [
            'order_number' => $order->order_number,
            'customer_email' => $order->customer_email,
            'customer_phone' => $order->customer_phone,
        ])->assertOk()
            ->assertJsonPath('message', 'Tautan akses transaksi berhasil dibuat.');

        $statusUrl = (string) $access->json('redirect_url');
        $this->assertStringContainsString('/topup/status/'.$order->public_token, $statusUrl);

        $this->getJson($statusUrl)
            ->assertOk()
            ->assertJsonPath('data.order_number', $order->order_number)
            ->assertJsonPath('data.payment_status.code', 'pending')
            ->assertJsonPath('data.destination', $order->maskedDestination())
            ->assertJsonMissingPath('data.customer_email')
            ->assertJsonMissingPath('data.customer_phone');
    }

    /** @param array<string, mixed> $attributes */
    private function product(array $attributes = []): DigiflazzProduct
    {
        return DigiflazzProduct::create(array_replace([
            'transaction_type' => DigiflazzTransactionType::Prepaid,
            'buyer_sku_code' => 'NEXT10',
            'product_name' => 'Pulsa Next 10.000',
            'category' => 'Pulsa',
            'brand' => 'Next',
            'cost_price' => 10000,
            'selling_price' => 11000,
            'buyer_product_status' => true,
            'seller_product_status' => true,
            'unlimited_stock' => true,
            'stock' => 0,
            'multi' => false,
        ], $attributes));
    }
}
