<?php

namespace Tests\Feature;

use App\Enums\DigiflazzTransactionType;
use App\Enums\TopupFulfillmentStatus;
use App\Enums\TopupPaymentStatus;
use App\Models\DigiflazzProduct;
use App\Models\TopupOrder;
use App\Models\User;
use App\Enums\UserRole;
use App\Jobs\ProcessTopupOrder;
use App\Jobs\SendTopupDiscordNotification;
use App\Integrations\Digiflazz\DigiflazzClient;
use App\Integrations\Discord\DiscordNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class AgentPpobApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.younz_ppob.agent_token', 'agent-test-token');
        config()->set('services.younz_ppob.operator_token', 'operator-test-token');
    }

    public function test_agent_routes_require_the_dedicated_token(): void
    {
        $this->getJson('/api/ppob/products')->assertUnauthorized();
        $this->getJson('/api/ppob/products', ['X-Younz-Agent-Token' => 'wrong'])->assertUnauthorized();
        $this->postJson('/api/ppob/operator/transactions/unknown/approve', [
            'confirmation' => 'APPROVE unknown',
        ])->assertUnauthorized();
        $this->postJson('/api/ppob/operator/transactions/unknown/approve', [
            'confirmation' => 'APPROVE unknown',
        ], ['X-Younz-Agent-Token' => 'agent-test-token'])->assertUnauthorized();
        config()->set('services.younz_ppob.operator_token', 'agent-test-token');
        $this->postJson('/api/ppob/operator/transactions/unknown/approve', [
            'confirmation' => 'APPROVE unknown',
        ], ['X-Younz-Operator-Token' => 'agent-test-token'])->assertUnauthorized();
    }

    public function test_agent_can_search_only_available_cached_products(): void
    {
        $active = $this->product('TSEL25', 'Telkomsel 25.000', true);
        $this->product('TSEL50', 'Telkomsel 50.000', false);

        $this->agentGet('/api/ppob/products?q=telkomsel')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', $active->buyer_sku_code)
            ->assertJsonMissingPath('data.0.raw');
    }

    public function test_agent_prepares_an_idempotent_prepaid_draft_without_execution(): void
    {
        $product = $this->product('TSEL25', 'Telkomsel 25.000', true);
        $requestId = (string) Str::uuid();
        $payload = ['sku' => $product->buyer_sku_code, 'customer_no' => '08219207240', 'request_id' => $requestId];

        $first = $this->agentPost('/api/ppob/prepaid/prepare', $payload)
            ->assertCreated()
            ->assertJsonPath('data.customer_no_masked', '082*****240')
            ->assertJsonPath('data.payment_status', TopupPaymentStatus::Pending->value)
            ->assertJsonPath('data.fulfillment_status', TopupFulfillmentStatus::WaitingPayment->value)
            ->assertJsonPath('data.approval_required', true);

        $second = $this->agentPost('/api/ppob/prepaid/prepare', $payload)
            ->assertOk()
            ->assertJsonPath('data.ref_id', $first->json('data.ref_id'));

        $this->assertDatabaseCount('topup_orders', 1);
        $order = TopupOrder::firstOrFail();
        $this->assertSame('agent:'.$requestId, $order->source_reference);
        $this->assertSame(TopupPaymentStatus::Pending, $order->payment_status);
        $this->assertSame(TopupFulfillmentStatus::WaitingPayment, $order->fulfillment_status);
        $this->assertNull($order->paid_at);
        $this->assertNull($order->provider_payment_requested_at);
    }

    public function test_agent_token_cannot_approve_but_operator_token_can_queue_provider_purchase(): void
    {
        Queue::fake();
        $product = $this->product('TSEL25', 'Telkomsel 25.000', true);
        $created = $this->agentPost('/api/ppob/prepaid/prepare', [
            'sku' => $product->buyer_sku_code,
            'customer_no' => '08219207240',
            'request_id' => (string) Str::uuid(),
        ])->assertCreated()->json('data');

        $payload = ['confirmation' => 'APPROVE '.$created['ref_id']];
        $this->agentPost('/api/ppob/operator/transactions/'.$created['ref_id'].'/approve', $payload)
            ->assertUnauthorized();
        $this->postJson('/api/ppob/transactions/'.$created['ref_id'].'/approve', $payload)
            ->assertNotFound();

        $this->operatorPost('/api/ppob/operator/transactions/'.$created['ref_id'].'/approve', $payload)
            ->assertOk()
            ->assertJsonPath('data.payment_status', TopupPaymentStatus::Paid->value)
            ->assertJsonPath('data.fulfillment_status', TopupFulfillmentStatus::Queued->value)
            ->assertJsonPath('data.approval_required', false);

        $this->operatorPost('/api/ppob/operator/transactions/'.$created['ref_id'].'/approve', $payload)
            ->assertOk()
            ->assertJsonPath('data.payment_status', TopupPaymentStatus::Paid->value);

        $order = TopupOrder::firstOrFail();
        $this->assertNotNull($order->paid_at);
        Queue::assertPushed(ProcessTopupOrder::class, 1);
    }

    public function test_operator_approval_requires_exact_explicit_confirmation(): void
    {
        Queue::fake();
        $product = $this->product('TSEL25', 'Telkomsel 25.000', true);
        $created = $this->agentPost('/api/ppob/prepaid/prepare', [
            'sku' => $product->buyer_sku_code,
            'customer_no' => '08219207240',
            'request_id' => (string) Str::uuid(),
        ])->assertCreated()->json('data');

        $this->operatorPost('/api/ppob/operator/transactions/'.$created['ref_id'].'/approve', ['confirmation' => 'ya'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('confirmation');

        $order = TopupOrder::firstOrFail();
        $this->assertSame(TopupPaymentStatus::Pending, $order->payment_status);
        Queue::assertNothingPushed();
    }

    public function test_operator_cannot_approve_stale_postpaid_inquiry(): void
    {
        Queue::fake();
        $product = DigiflazzProduct::create([
            'transaction_type' => DigiflazzTransactionType::Postpaid,
            'buyer_sku_code' => 'PDAM-SAMPIT',
            'product_name' => 'PDAM Sampit',
            'category' => 'Pascabayar',
            'brand' => 'PDAM',
            'cost_price' => 0,
            'selling_price' => 0,
            'provider_admin' => 2500,
            'provider_commission' => 500,
            'buyer_product_status' => true,
            'seller_product_status' => true,
            'unlimited_stock' => true,
            'stock' => 0,
            'multi' => false,
        ]);
        config()->set('services.digiflazz.enabled', true);
        config()->set('services.digiflazz.username', 'buyer-test');
        config()->set('services.digiflazz.api_key', 'secret-test');
        Http::fake(['*' => Http::response(['data' => [
            'status' => 'Sukses', 'rc' => '00', 'price' => 50000, 'selling_price' => 52000,
            'customer_name' => 'Pelanggan Uji', 'message' => 'Inquiry berhasil',
        ]], 200)]);
        $created = $this->agentPost('/api/ppob/postpaid/inquiry', [
            'sku' => $product->buyer_sku_code,
            'customer_no' => '01012352',
            'request_id' => (string) Str::uuid(),
        ])->assertCreated()->json('data');
        TopupOrder::query()->update(['inquired_at' => now()->subDay(), 'created_at' => now()->subDay()]);

        $this->operatorPost('/api/ppob/operator/transactions/'.$created['ref_id'].'/approve', [
            'confirmation' => 'APPROVE '.$created['ref_id'],
        ])->assertUnprocessable()->assertJsonValidationErrors('transaction');
        Queue::assertNothingPushed();
    }

    public function test_agent_transaction_projection_masks_sensitive_fields(): void
    {
        $product = $this->product('TSEL25', 'Telkomsel 25.000', true);
        $requestId = (string) Str::uuid();
        $created = $this->agentPost('/api/ppob/prepaid/prepare', [
            'sku' => $product->buyer_sku_code,
            'customer_no' => '08219207240',
            'request_id' => $requestId,
        ])->json('data');

        $response = $this->agentGet('/api/ppob/transactions/'.$created['ref_id'])
            ->assertOk()
            ->assertJsonPath('data.customer_no_masked', '082*****240');

        $this->assertStringNotContainsString('08219207240', $response->getContent());
    }

    public function test_agent_can_check_balance_without_exposing_credentials(): void
    {
        config()->set('services.digiflazz.enabled', true);
        config()->set('services.digiflazz.username', 'buyer-test');
        config()->set('services.digiflazz.api_key', 'secret-test');
        Http::fake(['*' => Http::response(['data' => ['deposit' => 125000]], 200)]);

        $response = $this->agentGet('/api/ppob/balance')
            ->assertOk()
            ->assertJsonPath('data.deposit', 125000);

        $this->assertStringNotContainsString('secret-test', $response->getContent());
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.digiflazz.com/v1/cek-saldo'
            && $request['cmd'] === 'deposit'
            && $request['username'] === 'buyer-test'
            && $request['sign'] === md5('buyer-testsecret-testdepo'));
    }

    public function test_agent_can_inquire_postpaid_into_an_approval_draft(): void
    {
        $product = DigiflazzProduct::create([
            'transaction_type' => DigiflazzTransactionType::Postpaid,
            'buyer_sku_code' => 'PDAM-SAMPIT',
            'product_name' => 'PDAM Sampit',
            'category' => 'Pascabayar',
            'brand' => 'PDAM',
            'cost_price' => 0,
            'selling_price' => 0,
            'provider_admin' => 2500,
            'provider_commission' => 500,
            'buyer_product_status' => true,
            'seller_product_status' => true,
            'unlimited_stock' => true,
            'stock' => 0,
            'multi' => false,
        ]);
        config()->set('services.digiflazz.enabled', true);
        config()->set('services.digiflazz.username', 'buyer-test');
        config()->set('services.digiflazz.api_key', 'secret-test');
        config()->set('services.digiflazz.postpaid_admin_fee', 5500);
        Http::fake(['*' => Http::response(['data' => [
            'status' => 'Sukses', 'rc' => '00', 'price' => 50000, 'selling_price' => 52000,
            'customer_name' => 'Pelanggan Uji', 'message' => 'Inquiry berhasil', 'desc' => ['periode' => '202608'],
        ]], 200)]);
        $requestId = (string) Str::uuid();

        $this->agentPost('/api/ppob/postpaid/inquiry', [
            'sku' => $product->buyer_sku_code,
            'customer_no' => '01012352',
            'request_id' => $requestId,
        ])->assertCreated()
            ->assertJsonPath('data.customer_no_masked', '010***352')
            ->assertJsonPath('data.approval_required', true)
            ->assertJsonPath('data.payment_status', TopupPaymentStatus::Pending->value)
            ->assertJsonPath('data.fulfillment_status', TopupFulfillmentStatus::WaitingPayment->value);

        $order = TopupOrder::firstOrFail();
        $this->assertSame(DigiflazzTransactionType::Postpaid, $order->transaction_type);
        $this->assertSame('Pelanggan Uji', $order->provider_customer_name);
        $this->assertSame(57500, $order->total_amount);
        $this->assertNull($order->paid_at);
        $this->assertNull($order->provider_payment_requested_at);
    }

    public function test_daily_report_uses_existing_topup_orders(): void
    {
        $product = $this->product('TSEL25', 'Telkomsel 25.000', true);
        $this->agentPost('/api/ppob/prepaid/prepare', [
            'sku' => $product->buyer_sku_code,
            'customer_no' => '08219207240',
            'request_id' => (string) Str::uuid(),
        ])->assertCreated();

        $this->agentGet('/api/ppob/reports/daily')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.success', 0)
            ->assertJsonPath('data.omzet', 0);
    }

    public function test_success_notification_includes_latest_digiflazz_balance(): void
    {
        $product = $this->product('TSEL25', 'Telkomsel 25.000', true);
        $created = $this->agentPost('/api/ppob/prepaid/prepare', [
            'sku' => $product->buyer_sku_code,
            'customer_no' => '08219207240',
            'request_id' => (string) Str::uuid(),
        ])->assertCreated()->json('data');
        $order = TopupOrder::query()->where('order_number', $created['ref_id'])->firstOrFail();
        $order->update([
            'payment_status' => TopupPaymentStatus::Paid,
            'fulfillment_status' => TopupFulfillmentStatus::Success,
            'paid_at' => now(),
            'fulfilled_at' => now(),
        ]);

        $digiflazz = Mockery::mock(DigiflazzClient::class);
        $digiflazz->shouldReceive('balance')->once()->andReturn(['deposit' => 989665]);
        $notifier = Mockery::mock(DiscordNotifier::class);
        $notifier->shouldReceive('notify')->once()->withArgs(function (...$arguments): bool {
            $fields = $arguments[3] ?? [];

            return collect($fields)->contains(fn (array $field): bool =>
                $field['name'] === 'Sisa Saldo Digiflazz' && $field['value'] === 'Rp989.665'
            );
        })->andReturnTrue();

        (new SendTopupDiscordNotification($order->id, 'success'))->handle($notifier, $digiflazz);
    }

    public function test_agent_draft_is_visible_to_operator_as_agent_source(): void
    {
        $product = $this->product('TSEL25', 'Telkomsel 25.000', true);
        $this->agentPost('/api/ppob/prepaid/prepare', [
            'sku' => $product->buyer_sku_code,
            'customer_no' => '08219207240',
            'request_id' => (string) Str::uuid(),
        ])->assertCreated();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Sanctum::actingAs($admin, ['staff:digital']);

        $this->getJson('/api/v1/staff/digital-transactions')
            ->assertOk()
            ->assertJsonPath('data.topups.0.source', 'Agent PPOB')
            ->assertJsonPath('data.topups.0.source_code', 'agent')
            ->assertJsonPath('data.topups.0.destination', '082*****240');
    }

    private function product(string $sku, string $name, bool $active): DigiflazzProduct
    {
        return DigiflazzProduct::create([
            'transaction_type' => DigiflazzTransactionType::Prepaid,
            'buyer_sku_code' => $sku,
            'product_name' => $name,
            'category' => 'Pulsa',
            'brand' => 'TELKOMSEL',
            'type' => 'Umum',
            'cost_price' => 23000,
            'selling_price' => 25000,
            'buyer_product_status' => $active,
            'seller_product_status' => $active,
            'unlimited_stock' => true,
            'stock' => 0,
            'multi' => false,
        ]);
    }

    private function agentGet(string $uri)
    {
        return $this->getJson($uri, ['X-Younz-Agent-Token' => 'agent-test-token']);
    }

    private function agentPost(string $uri, array $payload)
    {
        return $this->postJson($uri, $payload, ['X-Younz-Agent-Token' => 'agent-test-token']);
    }

    private function operatorPost(string $uri, array $payload)
    {
        return $this->postJson($uri, $payload, ['X-Younz-Operator-Token' => 'operator-test-token']);
    }
}
