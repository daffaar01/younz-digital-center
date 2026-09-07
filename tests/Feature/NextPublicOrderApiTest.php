<?php

namespace Tests\Feature;

use App\Actions\ApplyServiceOrderMidtransStatus;
use App\Enums\ServiceOrderStatus;
use App\Models\DigitalProduct;
use App\Models\ServiceOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class NextPublicOrderApiTest extends TestCase
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
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'default-service-snap-token',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/default-service-snap-token',
            ]),
            'api.sandbox.midtrans.com/v2/SVC-ORD-20260810-9096/status' => Http::response([
                'status_code' => '201', 'order_id' => 'SVC-ORD-20260810-9096',
                'gross_amount' => '10000.00', 'transaction_status' => 'pending',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/existing-service-snap-token',
            ]),
            'api.sandbox.midtrans.com/v2/SVC-ORD-20260810-9097/status' => Http::response([
                'status_code' => '201', 'order_id' => 'SVC-ORD-20260810-9097',
                'gross_amount' => '10000.00', 'transaction_status' => 'pending',
            ]),
            'api.sandbox.midtrans.com/v2/*/status' => Http::response(['status_code' => '404'], 404),
            '*' => Http::response(['ok' => true]),
        ]);
    }

    public function test_legacy_public_web_order_rejects_digital_checkout_bypass(): void
    {
        $product = DigitalProduct::query()->where('is_active', true)->firstOrFail();
        $product->update(['stock' => 3, 'price' => 10000]);
        $beforeOrders = ServiceOrder::query()->count();

        $this->from('/pesan')->post('/pesan', [
            'customer_name' => 'Pelanggan Web Legacy', 'customer_phone' => '081234567890',
            'type' => 'digital', 'source' => 'digital-product-detail',
            'product_id' => $product->id, 'quantity' => 1, 'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect('/pesan')->assertSessionHasErrors('type');

        $this->assertSame($beforeOrders, ServiceOrder::query()->count());
        $this->assertSame(3, $product->fresh()->stock);
    }

    public function test_public_next_frontend_can_create_and_track_an_order(): void
    {
        Storage::fake('local');

        $created = $this->post('/api/v1/public/orders', [
            'customer_name' => 'Pelanggan Next',
            'customer_phone' => '081234567890',
            'type' => 'print',
            'source' => 'direct',
            'specifications' => ['paper_size' => 'A4'],
            'notes' => 'Dua rangkap.',
            'file' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
        ])->assertCreated()
            ->assertJsonPath('data.status.code', ServiceOrderStatus::AwaitingReview->value)
            ->assertJsonMissingPath('data.customer_phone')
            ->assertJsonMissingPath('data.public_token');

        $order = ServiceOrder::firstOrFail();
        $created->assertJsonPath('tracking_token', $order->public_token);
        Storage::disk('local')->assertExists($order->files()->firstOrFail()->path);

        $this->postJson('/api/v1/public/orders/track', [
            'order_number' => $order->order_number,
            'phone' => '081234567890',
        ])->assertOk()
            ->assertJsonPath('data.order_number', $order->order_number)
            ->assertJsonPath('data.status.code', ServiceOrderStatus::AwaitingReview->value);

        $this->getJson('/api/v1/public/orders/'.$order->public_token)
            ->assertOk()
            ->assertJsonPath('data.order_number', $order->order_number);
    }

    public function test_public_can_submit_a_validated_digital_product_order_from_product_detail(): void
    {
        $product = DigitalProduct::query()->where('is_active', true)->firstOrFail();
        $product->update(['stock' => 5, 'price' => 125000]);

        $this->post('/api/v1/public/orders', [
            'customer_name' => 'Pelanggan Digital',
            'customer_phone' => '081234567890',
            'type' => 'digital',
            'source' => 'digital-product-detail',
            'idempotency_key' => (string) Str::uuid(),
            'product_id' => $product->id,
            'quantity' => 3,
            'notes' => 'Mohon konfirmasi paket yang tersedia.',
        ])->assertCreated();

        $order = ServiceOrder::query()->where('customer_name', 'Pelanggan Digital')->firstOrFail();
        $this->assertSame('digital', $order->type);
        $this->assertSame('digital-product-detail', $order->specifications['acquisition_source'] ?? null);
        $this->assertSame($product->id, $order->specifications['digital_product_id'] ?? null);
        $this->assertSame($product->name, $order->specifications['digital_product_name'] ?? null);
        $this->assertSame(3, $order->specifications['quantity'] ?? null);
        $this->assertSame(125000, $order->specifications['unit_price_snapshot'] ?? null);
    }

    public function test_priced_digital_product_creates_one_midtrans_payment_from_server_price(): void
    {
        $product = DigitalProduct::query()->where('is_active', true)->firstOrFail();
        $product->update(['stock' => 5, 'price' => 125000]);
        $idempotencyKey = (string) Str::uuid();
        Http::fake([
            'app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'service-snap-token',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/default-service-snap-token',
            ]),
        ]);
        $payload = [
            'customer_name' => 'Pelanggan Bayar',
            'customer_phone' => '081234567890',
            'type' => 'digital',
            'source' => 'digital-product-detail',
            'product_id' => $product->id,
            'quantity' => 2,
            'idempotency_key' => $idempotencyKey,
        ];

        $first = $this->postJson('/api/v1/public/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('payment.required', true)
            ->assertJsonPath('payment.amount', 250000)
            ->assertJsonPath('payment.redirect_url', 'https://app.sandbox.midtrans.com/snap/v4/redirection/default-service-snap-token');
        $this->postJson('/api/v1/public/orders', $payload)
            ->assertOk()
            ->assertJsonPath('tracking_token', $first->json('tracking_token'));

        $this->assertDatabaseCount('service_orders', 1);
        $order = ServiceOrder::firstOrFail();
        $this->assertSame(250000, $order->estimated_price);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('ready', $order->snap_creation_state);
        $this->assertSame($idempotencyKey, $order->checkout_idempotency_key);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'midtrans.com/snap/')
            && $request['transaction_details']['gross_amount'] === 250000
            && $request['item_details'][0]['price'] === 125000
            && $request['item_details'][0]['quantity'] === 2
            && $request['transaction_details']['order_id'] === $order->midtrans_order_id);
        $this->assertCount(1, Http::recorded(fn (Request $request): bool => str_contains($request->url(), 'midtrans.com/snap/')));
    }

    public function test_duration_variant_is_required_and_controls_server_price_stock_and_midtrans_amount(): void
    {
        $product = DigitalProduct::query()->where('is_active', true)->firstOrFail();
        $product->update(['stock' => 99, 'price' => 999999]);
        $variant = $product->variants()->create(['label' => '1 Bulan', 'price' => 75000, 'stock' => 4, 'is_active' => true, 'sort_order' => 1]);

        $base = [
            'customer_name' => 'Pelanggan Varian', 'customer_phone' => '081234567890',
            'type' => 'digital', 'source' => 'digital-product-detail', 'product_id' => $product->id,
            'quantity' => 2, 'idempotency_key' => (string) Str::uuid(), 'price' => 1,
        ];
        $this->postJson('/api/v1/public/orders', $base)
            ->assertUnprocessable()->assertJsonValidationErrors(['variant_id']);

        $response = $this->postJson('/api/v1/public/orders', [...$base, 'idempotency_key' => (string) Str::uuid(), 'variant_id' => $variant->id])
            ->assertCreated()->assertJsonPath('payment.amount', 150000);
        $order = ServiceOrder::query()->where('customer_name', 'Pelanggan Varian')->firstOrFail();
        $this->assertSame($variant->id, $order->specifications['digital_product_variant_id']);
        $this->assertSame('1 Bulan', $order->specifications['digital_product_variant_label']);
        $this->assertSame(75000, $order->specifications['unit_price_snapshot']);
        $this->assertSame(2, $variant->fresh()->stock);
        $this->assertSame(99, $product->fresh()->stock);
        app(ApplyServiceOrderMidtransStatus::class)->handle($order, [
            'transaction_status' => 'expire', 'status_code' => '202', 'transaction_id' => 'variant-transaction-1',
        ]);
        $this->assertSame(4, $variant->fresh()->stock);
        app(ApplyServiceOrderMidtransStatus::class)->handle($order, [
            'transaction_status' => 'settlement', 'status_code' => '200', 'fraud_status' => 'accept', 'transaction_id' => 'variant-transaction-1',
        ]);
        $this->assertSame(2, $variant->fresh()->stock);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'midtrans.com/snap/')
            && $request['transaction_details']['gross_amount'] === 150000
            && $request['item_details'][0]['price'] === 75000
            && $request['item_details'][0]['name'] === mb_substr($product->name.' - 1 Bulan', 0, 50));

        $unpriced = $product->variants()->create(['label' => '7 Hari', 'price' => null, 'stock' => 2, 'is_active' => true, 'sort_order' => 2]);
        $this->postJson('/api/v1/public/orders', [...$base, 'customer_name' => 'Pelanggan Tanpa Harga', 'quantity' => 1, 'idempotency_key' => (string) Str::uuid(), 'variant_id' => $unpriced->id])
            ->assertCreated()->assertJsonPath('payment.required', false)->assertJsonPath('payment.amount', null);
        $unpricedOrder = ServiceOrder::query()->where('customer_name', 'Pelanggan Tanpa Harga')->firstOrFail();
        $this->assertNull($unpricedOrder->specifications['unit_price_snapshot']);
        $this->assertSame('unpaid', $unpricedOrder->payment_status);
        $this->assertSame(2, $unpriced->fresh()->stock);

        $inactive = $product->variants()->create(['label' => '1 Tahun', 'price' => 500000, 'stock' => 1, 'is_active' => false, 'sort_order' => 3]);
        $this->postJson('/api/v1/public/orders', [...$base, 'idempotency_key' => (string) Str::uuid(), 'variant_id' => $inactive->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['variant_id']);

        $otherProduct = DigitalProduct::query()->where('is_active', true)->whereKeyNot($product->id)->firstOrFail();
        $foreignVariant = $otherProduct->variants()->create(['label' => '1 Bulan', 'price' => 60000, 'stock' => 2, 'is_active' => true, 'sort_order' => 1]);
        $this->postJson('/api/v1/public/orders', [...$base, 'idempotency_key' => (string) Str::uuid(), 'variant_id' => $foreignVariant->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['variant_id']);
    }

    public function test_priced_product_stock_is_reserved_and_released_idempotently(): void
    {
        $product = DigitalProduct::query()->where('is_active', true)->firstOrFail();
        $product->update(['stock' => 5, 'price' => 125000]);
        $payload = [
            'customer_name' => 'Pelanggan Reservasi',
            'customer_phone' => '081234567890',
            'type' => 'digital',
            'source' => 'digital-product-detail',
            'product_id' => $product->id,
            'quantity' => 2,
            'idempotency_key' => (string) Str::uuid(),
        ];

        $this->postJson('/api/v1/public/orders', $payload)->assertCreated();
        $this->postJson('/api/v1/public/orders', $payload)->assertOk();
        $this->assertSame(3, $product->fresh()->stock);
        $order = ServiceOrder::firstOrFail();
        $this->assertTrue($order->specifications['stock_reserved']);

        $action = app(ApplyServiceOrderMidtransStatus::class);
        $action->handle($order, [
            'transaction_status' => 'expire',
            'status_code' => '202',
            'transaction_id' => 'stock-transaction-1',
        ]);
        $action->handle($order, [
            'transaction_status' => 'expire',
            'status_code' => '202',
            'transaction_id' => 'stock-transaction-1',
        ]);
        $this->assertSame(5, $product->fresh()->stock);

        $action->handle($order, [
            'transaction_status' => 'settlement',
            'status_code' => '200',
            'fraud_status' => 'accept',
            'transaction_id' => 'stock-transaction-1',
        ]);
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(ServiceOrderStatus::Queued, $order->fresh()->status);

        $action->handle($order, [
            'transaction_status' => 'refund',
            'status_code' => '200',
            'transaction_id' => 'stock-transaction-1',
        ]);
        $action->handle($order, [
            'transaction_status' => 'refund',
            'status_code' => '200',
            'transaction_id' => 'stock-transaction-1',
        ]);
        $this->assertSame(5, $product->fresh()->stock);

        $action->handle($order, [
            'transaction_status' => 'settlement',
            'status_code' => '200',
            'fraud_status' => 'accept',
            'transaction_id' => 'stock-transaction-1',
        ]);
        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_expiration_command_releases_stale_stock_reservation(): void
    {
        $product = DigitalProduct::query()->where('is_active', true)->firstOrFail();
        $product->update(['stock' => 3, 'price' => 10000]);
        $order = ServiceOrder::query()->create([
            'order_number' => 'ORD-20260810-9092',
            'public_token' => (string) Str::uuid(),
            'customer_name' => 'Pelanggan Expire',
            'customer_phone' => '081234567890',
            'type' => 'digital',
            'status' => ServiceOrderStatus::AwaitingPayment,
            'specifications' => [
                'digital_product_id' => $product->id,
                'quantity' => 2,
                'unit_price_snapshot' => 10000,
                'stock_reserved' => true,
                'stock_released' => false,
            ],
            'estimated_price' => 20000,
            'payment_status' => 'pending',
            'midtrans_order_id' => 'SVC-ORD-20260810-9092',
            'payment_expires_at' => now()->subMinute(),
        ]);

        $this->artisan('service-orders:expire-payments')->assertSuccessful();

        $this->assertSame('expired', $order->fresh()->payment_status);
        $this->assertTrue($order->fresh()->specifications['stock_released']);
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_unpriced_digital_product_stays_operator_review_without_midtrans_request(): void
    {
        Http::fake();
        $product = DigitalProduct::query()->where('is_active', true)->firstOrFail();
        $product->update(['stock' => null, 'price' => null]);

        $this->postJson('/api/v1/public/orders', [
            'customer_name' => 'Pelanggan Konfirmasi',
            'customer_phone' => '081234567890',
            'type' => 'digital',
            'source' => 'digital-product-detail',
            'product_id' => $product->id,
            'quantity' => 1,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated()
            ->assertJsonPath('payment.required', false)
            ->assertJsonPath('payment.redirect_url', null);

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'midtrans.com/snap/'));
        $this->assertSame(ServiceOrderStatus::AwaitingReview, ServiceOrder::firstOrFail()->status);
    }

    public function test_digital_product_order_rejects_inactive_out_of_stock_and_excess_quantity(): void
    {
        $product = DigitalProduct::query()->where('is_active', true)->firstOrFail();
        $payload = [
            'customer_name' => 'Pelanggan Digital',
            'customer_phone' => '081234567890',
            'type' => 'digital',
            'source' => 'digital-product-detail',
            'idempotency_key' => (string) Str::uuid(),
            'product_id' => $product->id,
            'quantity' => 1,
        ];

        $product->update(['stock' => 0]);
        $this->post('/api/v1/public/orders', $payload)->assertUnprocessable()->assertJsonValidationErrors('quantity');

        $product->update(['stock' => 2]);
        $this->post('/api/v1/public/orders', [...$payload, 'quantity' => 3])->assertUnprocessable()->assertJsonValidationErrors('quantity');

        $product->update(['is_active' => false]);
        $this->post('/api/v1/public/orders', $payload)->assertUnprocessable()->assertJsonValidationErrors('product_id');

        $this->post('/api/v1/public/orders', [...$payload, 'type' => 'print'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id', 'quantity']);
    }

    public function test_midtrans_webhook_marks_service_order_paid_once_and_rejects_bad_payload(): void
    {
        $product = DigitalProduct::query()->where('is_active', true)->firstOrFail();
        $product->update(['stock' => 5, 'price' => 125000]);
        $order = ServiceOrder::query()->create([
            'order_number' => 'ORD-20260810-9090',
            'public_token' => (string) Str::uuid(),
            'customer_name' => 'Pelanggan Webhook',
            'customer_phone' => '081234567890',
            'type' => 'digital',
            'status' => ServiceOrderStatus::AwaitingPayment,
            'specifications' => [
                'digital_product_id' => $product->id,
                'digital_product_name' => $product->name,
                'quantity' => 2,
                'unit_price_snapshot' => 125000,
            ],
            'estimated_price' => 250000,
            'payment_status' => 'pending',
            'midtrans_order_id' => 'SVC-ORD-20260810-9090',
        ]);
        $payload = [
            'order_id' => $order->midtrans_order_id,
            'status_code' => '200',
            'gross_amount' => '250000.00',
            'transaction_status' => 'settlement',
            'transaction_id' => 'service-midtrans-transaction-1',
            'payment_type' => 'qris',
            'fraud_status' => 'accept',
        ];
        $payload['signature_key'] = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'midtrans-service-test-key');

        $this->postJson(route('webhooks.midtrans'), [...$payload, 'signature_key' => 'invalid'])->assertUnauthorized();
        $this->postJson(route('webhooks.midtrans'), [...$payload, 'gross_amount' => '250001.00', 'signature_key' => hash('sha512', $payload['order_id'].$payload['status_code'].'250001.00'.'midtrans-service-test-key')])->assertNotFound();
        $this->postJson(route('webhooks.midtrans'), $payload)->assertOk();
        $this->postJson(route('webhooks.midtrans'), $payload)->assertOk();

        $fresh = $order->fresh();
        $this->assertSame('paid', $fresh->payment_status);
        $this->assertSame(250000, $fresh->paid_amount);
        $this->assertSame(ServiceOrderStatus::Queued, $fresh->status);
        $this->assertSame('qris', $fresh->payment_confirmation_method);
        $this->assertSame('service-midtrans-transaction-1', $fresh->payment_confirmation_reference);
        $this->assertDatabaseCount('service_order_status_histories', 1);
    }

    public function test_service_order_midtrans_statuses_are_mapped_without_regressing_paid_orders(): void
    {
        $order = ServiceOrder::query()->create([
            'order_number' => 'ORD-20260810-9091',
            'public_token' => (string) Str::uuid(),
            'customer_name' => 'Pelanggan Status',
            'customer_phone' => '081234567890',
            'type' => 'digital',
            'status' => ServiceOrderStatus::AwaitingPayment,
            'estimated_price' => 10000,
            'payment_status' => 'pending',
            'midtrans_order_id' => 'SVC-ORD-20260810-9091',
        ]);

        foreach (['pending' => 'pending', 'expire' => 'expired', 'cancel' => 'cancelled', 'deny' => 'failed'] as $provider => $expected) {
            $order->update(['payment_status' => 'pending']);
            app(ApplyServiceOrderMidtransStatus::class)->handle($order, [
                'transaction_status' => $provider,
                'status_code' => '201',
                'transaction_id' => 'status-transaction-1',
            ]);
            $this->assertSame($expected, $order->fresh()->payment_status);
        }

        $order->update(['payment_status' => 'paid', 'status' => ServiceOrderStatus::Queued]);
        app(ApplyServiceOrderMidtransStatus::class)->handle($order, [
            'transaction_status' => 'cancel',
            'status_code' => '202',
            'transaction_id' => 'status-transaction-1',
        ]);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(ServiceOrderStatus::Queued, $order->fresh()->status);

        app(ApplyServiceOrderMidtransStatus::class)->handle($order, [
            'transaction_status' => 'refund',
            'status_code' => '200',
            'transaction_id' => 'status-transaction-1',
        ]);
        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->assertSame(ServiceOrderStatus::Queued, $order->fresh()->status);
    }

    public function test_partial_refund_does_not_release_all_reserved_stock(): void
    {
        $product = DigitalProduct::query()->where('is_active', true)->firstOrFail();
        $product->update(['stock' => 4, 'price' => 10000]);
        $order = ServiceOrder::query()->create([
            'order_number' => 'ORD-20260810-9095', 'public_token' => (string) Str::uuid(),
            'customer_name' => 'Pelanggan Partial', 'customer_phone' => '081234567890', 'type' => 'digital',
            'status' => ServiceOrderStatus::Queued, 'estimated_price' => 20000,
            'paid_amount' => 20000, 'payment_status' => 'paid', 'midtrans_order_id' => 'SVC-ORD-20260810-9095',
            'specifications' => ['digital_product_id' => $product->id, 'quantity' => 2, 'unit_price_snapshot' => 10000, 'stock_reserved' => true, 'stock_released' => false],
        ]);

        app(ApplyServiceOrderMidtransStatus::class)->handle($order, [
            'transaction_status' => 'partial_refund', 'status_code' => '200',
            'transaction_id' => 'partial-1', 'refund_amount' => 5000,
        ]);
        $fresh = $order->fresh();
        $this->assertSame('partial_refunded', $fresh->payment_status);
        $this->assertSame(15000, $fresh->paid_amount);
        $this->assertSame(5000, $fresh->refunded_amount);
        $this->assertSame(ServiceOrderStatus::Queued, $fresh->status);
        $this->assertFalse($fresh->specifications['stock_released']);
        $this->assertSame(4, $product->fresh()->stock);

        app(ApplyServiceOrderMidtransStatus::class)->handle($order, [
            'transaction_status' => 'partial_refund', 'status_code' => '200',
            'transaction_id' => 'partial-1', 'refund_amount' => 12000,
        ]);
        $this->assertSame(8000, $order->fresh()->paid_amount);
        $this->assertSame(12000, $order->fresh()->refunded_amount);
        $this->assertSame(4, $product->fresh()->stock);

        app(ApplyServiceOrderMidtransStatus::class)->handle($order, [
            'transaction_status' => 'refund', 'status_code' => '200',
            'transaction_id' => 'partial-1',
        ]);
        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->assertSame(6, $product->fresh()->stock);
    }

    public function test_failed_snap_reconciles_existing_midtrans_order_without_duplicate_create(): void
    {
        $product = DigitalProduct::query()->where('is_active', true)->firstOrFail();
        $product->update(['stock' => 5, 'price' => 10000]);
        $key = (string) Str::uuid();
        $fingerprint = hash('sha256', json_encode(['customer_name' => 'Pelanggan Reconcile', 'customer_phone' => '081234567890', 'product_id' => $product->id, 'quantity' => 1, 'notes' => ''], JSON_THROW_ON_ERROR));
        $order = ServiceOrder::query()->create([
            'order_number' => 'ORD-20260810-9096', 'public_token' => (string) Str::uuid(),
            'checkout_idempotency_key' => $key, 'checkout_request_fingerprint' => $fingerprint,
            'customer_name' => 'Pelanggan Reconcile', 'customer_phone' => '081234567890', 'type' => 'digital',
            'status' => ServiceOrderStatus::AwaitingPayment, 'estimated_price' => 10000,
            'payment_status' => 'pending', 'midtrans_order_id' => 'SVC-ORD-20260810-9096',
            'snap_creation_state' => 'failed', 'snap_creation_started_at' => now()->subMinutes(3),
            'specifications' => ['digital_product_id' => $product->id, 'digital_product_name' => $product->name, 'quantity' => 1, 'unit_price_snapshot' => 10000],
        ]);

        $this->postJson('/api/v1/public/orders', [
            'customer_name' => 'Pelanggan Reconcile', 'customer_phone' => '081234567890', 'type' => 'digital',
            'source' => 'digital-product-detail', 'product_id' => $product->id, 'quantity' => 1, 'idempotency_key' => $key,
        ])->assertOk()->assertJsonPath('payment.redirect_url', 'https://app.sandbox.midtrans.com/snap/v4/redirection/existing-service-snap-token');

        $this->assertSame('ready', $order->fresh()->snap_creation_state);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET' && str_contains($request->url(), '/SVC-ORD-20260810-9096/status'));
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), '/snap/v1/transactions'));
    }

    public function test_reconciled_midtrans_order_without_redirect_is_never_created_again(): void
    {
        $product = DigitalProduct::query()->where('is_active', true)->firstOrFail();
        $product->update(['stock' => 5, 'price' => 10000]);
        $key = (string) Str::uuid();
        $fingerprint = hash('sha256', json_encode(['customer_name' => 'Pelanggan No Redirect', 'customer_phone' => '081234567890', 'product_id' => $product->id, 'quantity' => 1, 'notes' => ''], JSON_THROW_ON_ERROR));
        $order = ServiceOrder::query()->create([
            'order_number' => 'ORD-20260810-9097', 'public_token' => (string) Str::uuid(),
            'checkout_idempotency_key' => $key, 'checkout_request_fingerprint' => $fingerprint,
            'customer_name' => 'Pelanggan No Redirect', 'customer_phone' => '081234567890', 'type' => 'digital',
            'status' => ServiceOrderStatus::AwaitingPayment, 'estimated_price' => 10000,
            'payment_status' => 'pending', 'midtrans_order_id' => 'SVC-ORD-20260810-9097',
            'snap_creation_state' => 'failed', 'snap_creation_started_at' => now()->subMinutes(3),
            'specifications' => ['digital_product_id' => $product->id, 'digital_product_name' => $product->name, 'quantity' => 1, 'unit_price_snapshot' => 10000],
        ]);
        $payload = [
            'customer_name' => 'Pelanggan No Redirect', 'customer_phone' => '081234567890', 'type' => 'digital',
            'source' => 'digital-product-detail', 'product_id' => $product->id, 'quantity' => 1, 'idempotency_key' => $key,
        ];

        $this->postJson('/api/v1/public/orders', $payload)->assertStatus(502)->assertJsonPath('partial_success', true);
        $this->assertSame('reconciled', $order->fresh()->snap_creation_state);
        $this->postJson('/api/v1/public/orders', $payload)->assertStatus(502)->assertJsonPath('partial_success', true);
        Http::assertSentCount(2);
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), '/snap/v1/transactions'));
    }

    public function test_reviewed_midtrans_races_and_late_statuses_fail_closed(): void
    {
        Http::fake();
        $product = DigitalProduct::query()->where('is_active', true)->firstOrFail();
        $product->update(['stock' => 5, 'price' => 10000]);
        $key = (string) Str::uuid();
        $fingerprint = hash('sha256', json_encode(['customer_name' => 'Pelanggan Creating', 'customer_phone' => '081234567890', 'product_id' => $product->id, 'quantity' => 1, 'notes' => ''], JSON_THROW_ON_ERROR));
        $creating = ServiceOrder::query()->create([
            'order_number' => 'ORD-20260810-9093', 'public_token' => (string) Str::uuid(),
            'checkout_idempotency_key' => $key, 'checkout_request_fingerprint' => $fingerprint,
            'customer_name' => 'Pelanggan Creating', 'customer_phone' => '081234567890', 'type' => 'digital',
            'status' => ServiceOrderStatus::AwaitingPayment,
            'specifications' => ['digital_product_id' => $product->id, 'digital_product_name' => $product->name, 'quantity' => 1, 'unit_price_snapshot' => 10000],
            'estimated_price' => 10000, 'payment_status' => 'pending', 'midtrans_order_id' => 'SVC-ORD-20260810-9093',
            'snap_creation_state' => 'creating', 'snap_creation_started_at' => now(),
        ]);
        $this->postJson('/api/v1/public/orders', [
            'customer_name' => 'Pelanggan Creating', 'customer_phone' => '081234567890', 'type' => 'digital',
            'source' => 'digital-product-detail', 'product_id' => $product->id, 'quantity' => 1, 'idempotency_key' => $key,
        ])->assertStatus(202)->assertJsonPath('partial_success', true)->assertJsonPath('tracking_token', $creating->public_token);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'midtrans.com/snap/'));

        $creating->update(['snap_creation_state' => 'failed']);
        $this->postJson('/api/v1/public/orders', [
            'customer_name' => 'Pelanggan Creating', 'customer_phone' => '081234567890', 'type' => 'digital',
            'source' => 'digital-product-detail', 'product_id' => $product->id, 'quantity' => 1, 'idempotency_key' => $key,
        ])->assertOk()->assertJsonPath('payment.redirect_url', 'https://app.sandbox.midtrans.com/snap/v4/redirection/default-service-snap-token');
        $this->assertSame('ready', $creating->fresh()->snap_creation_state);
        Http::assertSentCount(2);

        $late = ServiceOrder::query()->create([
            'order_number' => 'ORD-20260810-9094', 'public_token' => (string) Str::uuid(),
            'customer_name' => 'Pelanggan Late', 'customer_phone' => '081234567890', 'type' => 'digital',
            'status' => ServiceOrderStatus::Cancelled, 'estimated_price' => 10000, 'payment_status' => 'cancelled',
            'midtrans_order_id' => 'SVC-ORD-20260810-9094',
        ]);
        app(ApplyServiceOrderMidtransStatus::class)->handle($late, [
            'transaction_status' => 'settlement', 'status_code' => '200', 'fraud_status' => 'accept', 'transaction_id' => 'late-1',
        ]);
        $this->assertSame('paid', $late->fresh()->payment_status);
        $this->assertSame(ServiceOrderStatus::AwaitingReview, $late->fresh()->status);
        $this->assertDatabaseHas('service_order_status_histories', ['service_order_id' => $late->id, 'from_status' => ServiceOrderStatus::Cancelled->value, 'to_status' => ServiceOrderStatus::AwaitingReview->value]);
        $late->update(['payment_status' => 'refunded', 'paid_amount' => 10000, 'status' => ServiceOrderStatus::Ready]);
        $this->assertFalse($late->fresh()->canReleaseResults());
    }

    public function test_tracking_requires_matching_phone(): void
    {
        ServiceOrder::create([
            'order_number' => 'ORD-20260729-0001',
            'public_token' => fake()->uuid(),
            'customer_name' => 'Pelanggan',
            'customer_phone' => '081234567890',
            'type' => 'print',
            'status' => ServiceOrderStatus::AwaitingReview,
        ]);

        $this->postJson('/api/v1/public/orders/track', [
            'order_number' => 'ORD-20260729-0001',
            'phone' => '089999999999',
        ])->assertNotFound();
    }
}
