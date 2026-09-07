<?php

namespace Tests\Feature;

use App\Actions\Topup\SyncDigiflazzProducts;
use App\Enums\DigiflazzTransactionType;
use App\Enums\TopupFulfillmentStatus;
use App\Enums\TopupPaymentStatus;
use App\Enums\UserRole;
use App\Integrations\Digiflazz\DigiflazzClient;
use App\Jobs\ProcessTopupOrder;
use App\Models\DigiflazzProduct;
use App\Models\TopupOrder;
use App\Models\User;
use App\Support\DigiflazzPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TopupIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'services.digiflazz.enabled' => true,
            'services.digiflazz.username' => 'buyer-test',
            'services.digiflazz.api_key' => 'digiflazz-test-key',
            'services.digiflazz.testing' => true,
            'services.digiflazz.endpoint' => 'https://api.digiflazz.com/v1',
            'services.digiflazz.webhook_secret' => 'webhook-test-secret',
            'services.digiflazz.prepaid_markup' => 2000,
            'services.digiflazz.prepaid_high_value_threshold' => 200000,
            'services.digiflazz.prepaid_high_value_markup' => 5000,
            'services.digiflazz.prepaid_rounding_unit' => 1000,
            'services.digiflazz.postpaid_admin_fee' => 5500,
            'services.midtrans.enabled' => true,
            'services.midtrans.server_key' => 'midtrans-test-server-key',
            'services.midtrans.production' => false,
            'services.midtrans.expiry_minutes' => 60,
            'services.midtrans.notification_url' => 'https://example.test/webhooks/midtrans',
            'services.discord.enabled' => false,
        ]);
    }

    public function test_topup_menu_and_catalog_are_publicly_visible(): void
    {
        $product = $this->product();

        $this->get(route('home'))->assertOk()->assertSee(route('topup.index'), escape: false);
        $this->get(route('topup.index'))
            ->assertOk()
            ->assertSee('Top Up Digital')
            ->assertSee($product->product_name)
            ->assertSee('Harga total')
            ->assertSee('Rp 11.000')
            ->assertDontSee('Biaya admin');
    }

    public function test_pricing_rules_follow_requested_rounding_and_postpaid_fee(): void
    {
        $pricing = app(DigiflazzPricing::class);

        $this->assertSame(7000, $pricing->prepaidSellingPrice(5000));
        $this->assertSame(13000, $pricing->prepaidSellingPrice(11697));
        $this->assertSame(7000, $pricing->prepaidSellingPrice(6805, 'Pulsa', 'Indosat 5.000'));
        $this->assertSame(12000, $pricing->prepaidSellingPrice(11855, 'Pulsa', 'Indosat 10.000'));
        $this->assertSame(7000, $pricing->prepaidSellingPrice(6705, 'PLN', 'PLN 5.000'));
        $this->assertSame(101000, $pricing->prepaidSellingPrice(97000, 'PLN', 'PLN 99.999'));
        $this->assertSame(105000, $pricing->prepaidSellingPrice(97000, 'PLN', 'PLN 100.000'));
        $this->assertSame(504000, $pricing->prepaidSellingPrice(497000, 'PLN', 'PLN 499.000'));
        $this->assertSame(506000, $pricing->prepaidSellingPrice(497000, 'PLN', 'PLN 500.000'));
        $this->assertSame(201000, $pricing->prepaidSellingPrice(199999));
        $this->assertSame(205000, $pricing->prepaidSellingPrice(200000));
        $this->assertSame(205000, $pricing->prepaidSellingPrice(200499));
        config(['services.digiflazz.prepaid_price_overrides' => 'Tel35:36000, OtherSku:99000']);
        $this->assertSame(36000, $pricing->prepaidSellingPrice(34495, 'Pulsa', 'Telkomsel 35.000', 'Tel35'));
        $this->assertSame(36500, $pricing->prepaidSellingPrice(36500, 'Pulsa', 'Telkomsel 35.000', 'tel35'));
        $this->assertSame(5500, $pricing->postpaidAdminFee());
    }

    public function test_sensitive_topup_data_is_encrypted_at_rest(): void
    {
        $order = $this->order([
            'bill_details' => ['lembar_tagihan' => 1],
            'midtrans_snap_token' => 'MIDTRANS-SNAP-SECRET',
            'midtrans_redirect_url' => 'https://app.sandbox.midtrans.com/private-link',
            'provider_customer_name' => 'DAFFA YOUNZ',
            'serial_number' => 'TOKEN-PLN-SECRET',
            'provider_message' => 'Transaksi berhasil',
        ]);
        $raw = DB::table('topup_orders')->where('id', $order->id)->first();

        $this->assertNotSame('08219207240', $raw->destination);
        $this->assertNotSame('daffa@example.com', $raw->customer_email);
        $this->assertNotSame('MIDTRANS-SNAP-SECRET', $raw->midtrans_snap_token);
        $this->assertNotSame('TOKEN-PLN-SECRET', $raw->serial_number);
        $this->assertNotSame('https://app.sandbox.midtrans.com/private-link', $raw->midtrans_redirect_url);

        $fresh = $order->fresh();
        $this->assertSame('08219207240', $fresh->destination);
        $this->assertSame('daffa@example.com', $fresh->customer_email);
        $this->assertSame('MIDTRANS-SNAP-SECRET', $fresh->midtrans_snap_token);
        $this->assertSame('TOKEN-PLN-SECRET', $fresh->serial_number);
        $this->assertSame(['lembar_tagihan' => 1], $fresh->bill_details);
    }

    public function test_legacy_plaintext_midtrans_snap_tokens_are_encrypted_by_remediation_migration(): void
    {
        $order = $this->order();
        DB::table('topup_orders')->where('id', $order->id)->update([
            'midtrans_snap_token' => 'LEGACY-SNAP-TOKEN',
        ]);

        $migration = require database_path('migrations/2026_07_23_120000_encrypt_legacy_midtrans_snap_tokens.php');
        $migration->up();
        $migration->up();

        $rawToken = DB::table('topup_orders')->where('id', $order->id)->value('midtrans_snap_token');
        $this->assertIsString($rawToken);
        $this->assertNotSame('LEGACY-SNAP-TOKEN', $rawToken);
        $this->assertSame('LEGACY-SNAP-TOKEN', $order->fresh()->midtrans_snap_token);
    }

    public function test_digiflazz_catalog_sync_uses_signed_request_and_server_side_markup(): void
    {
        Http::fake([
            'api.digiflazz.com/v1/price-list' => Http::response(['data' => [[
                'product_name' => 'Data 10 GB',
                'category' => 'Data',
                'brand' => 'Telkomsel',
                'type' => 'Umum',
                'price' => 11697,
                'buyer_sku_code' => 'DATA10',
                'buyer_product_status' => true,
                'seller_product_status' => true,
                'unlimited_stock' => true,
                'stock' => 0,
                'multi' => false,
                'desc' => 'Paket data',
            ]]], 200),
        ]);

        $count = app(SyncDigiflazzProducts::class)->handle();

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('digiflazz_products', [
            'buyer_sku_code' => 'DATA10',
            'cost_price' => 11697,
            'selling_price' => 13000,
        ]);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.digiflazz.com/v1/price-list'
                && $request['sign'] === md5('buyer-testdigiflazz-test-keypricelist');
        });
    }

    public function test_postpaid_catalog_sync_uses_pasca_command_without_static_price(): void
    {
        Http::fake([
            'api.digiflazz.com/v1/price-list' => Http::response(['data' => [[
                'product_name' => 'PLN Pascabayar',
                'category' => 'Pascabayar',
                'brand' => 'PLN',
                'buyer_sku_code' => 'PLNPOST',
                'admin' => 2500,
                'commission' => 500,
                'buyer_product_status' => true,
                'seller_product_status' => true,
                'desc' => 'Pembayaran tagihan PLN',
            ]]], 200),
        ]);

        $count = app(SyncDigiflazzProducts::class)->handle(DigiflazzTransactionType::Postpaid);

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('digiflazz_products', [
            'transaction_type' => DigiflazzTransactionType::Postpaid->value,
            'buyer_sku_code' => 'PLNPOST',
            'cost_price' => 0,
            'selling_price' => 0,
            'provider_admin' => 2500,
            'provider_commission' => 500,
        ]);
        $this->assertTrue(DigiflazzProduct::query()->where('buyer_sku_code', 'PLNPOST')->available()->exists());
        Http::assertSent(fn (Request $request): bool => $request['cmd'] === 'pasca');
    }

    public function test_checkout_creates_immutable_price_snapshot_and_redirects_to_midtrans(): void
    {
        $product = $this->product();
        Http::fake([
            'app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'snap-token-test',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/test',
            ], 201),
        ]);

        $response = $this->post(route('topup.store'), [
            'idempotency_key' => (string) Str::uuid(),
            'product_id' => $product->id,
            'destination' => '08219207240',
            'destination_confirmation' => '08219207240',
            'customer_name' => 'Daffa',
            'customer_email' => 'daffa@example.com',
            'customer_phone' => '08219207240',
            'terms' => '1',
        ]);

        $response->assertRedirect('https://app.sandbox.midtrans.com/snap/v4/redirection/test');
        $order = TopupOrder::query()->sole();
        $this->assertSame(11000, $order->total_amount);
        $this->assertSame(0, $order->admin_fee);
        $this->assertSame(TopupPaymentStatus::Pending, $order->payment_status);
        $this->assertSame('snap-token-test', $order->midtrans_snap_token);
        $this->get($order->temporarySignedUrl('topup.show'))
            ->assertOk()
            ->assertSee('Total pembayaran')
            ->assertSee('Rp 11.000')
            ->assertSee('Buka pembayaran Midtrans')
            ->assertSee('<a href="https://app.sandbox.midtrans.com/snap/v4/redirection/test" class="public-btn-primary', escape: false)
            ->assertDontSee('Buat pembayaran Midtrans')
            ->assertDontSee('Biaya admin');

        Http::assertSent(function (Request $request) use ($order): bool {
            return $request->url() === 'https://app.sandbox.midtrans.com/snap/v1/transactions'
                && $request->hasHeader('X-Override-Notification', 'https://example.test/webhooks/midtrans')
                && $request['transaction_details']['order_id'] === $order->order_number
                && $request['transaction_details']['gross_amount'] === 11000;
        });
    }

    public function test_reconciliation_recovers_a_settled_payment_and_queues_digiflazz(): void
    {
        Queue::fake();
        $order = $this->order([
            'midtrans_redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/reconcile',
        ]);
        Http::fake([
            'api.sandbox.midtrans.com/v2/*/status' => Http::response([
                'order_id' => $order->order_number,
                'transaction_id' => 'midtrans-reconciled-transaction',
                'transaction_status' => 'settlement',
                'status_code' => '200',
                'fraud_status' => 'accept',
                'gross_amount' => '11000.00',
                'payment_type' => 'qris',
            ], 200),
        ]);

        $this->artisan('midtrans:reconcile-payments', ['--order' => $order->order_number])
            ->assertSuccessful();

        $order->refresh();
        $this->assertSame(TopupPaymentStatus::Paid, $order->payment_status);
        $this->assertSame(TopupFulfillmentStatus::Queued, $order->fulfillment_status);
        $this->assertSame('settlement', $order->midtrans_status);
        $this->assertSame('qris', $order->midtrans_payment_type);
        Queue::assertPushed(ProcessTopupOrder::class, 1);
    }

    public function test_reconciliation_expires_stale_payment_without_calling_midtrans(): void
    {
        Http::fake();
        $order = $this->order([
            'midtrans_redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/expired',
            'expires_at' => now()->subMinute(),
        ]);

        $this->artisan('midtrans:reconcile-payments', ['--order' => $order->order_number])
            ->expectsOutput("{$order->order_number}: kedaluwarsa secara lokal.")
            ->assertSuccessful();

        $order->refresh();
        $this->assertSame(TopupPaymentStatus::Expired, $order->payment_status);
        $this->assertSame('expire', $order->midtrans_status);
        Http::assertNothingSent();
    }

    public function test_reconciliation_rechecks_provider_pending_topup(): void
    {
        Queue::fake();
        $order = $this->order([
            'payment_status' => TopupPaymentStatus::Paid,
            'fulfillment_status' => TopupFulfillmentStatus::ProviderPending,
            'provider_rc' => '03',
            'provider_message' => 'Transaksi Pending',
            'paid_at' => now()->subMinutes(10),
        ]);

        $this->artisan('midtrans:reconcile-payments', ['--order' => $order->order_number])
            ->expectsOutput("{$order->order_number}: status Digiflazz diperiksa kembali.")
            ->assertSuccessful();

        Queue::assertPushed(ProcessTopupOrder::class, fn (ProcessTopupOrder $job): bool => $job->topupOrderId === $order->id);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'topup.digiflazz_reconciliation_dispatched',
            'subject_id' => $order->id,
        ]);
    }

    public function test_paid_topup_exposes_printable_receipt_and_invoice(): void
    {
        $order = $this->order([
            'payment_status' => TopupPaymentStatus::Paid,
            'fulfillment_status' => TopupFulfillmentStatus::Success,
            'midtrans_payment_type' => 'qris',
            'serial_number' => 'SN-PRINT-123456',
            'paid_at' => now(),
            'fulfilled_at' => now(),
        ]);

        $this->get($order->temporarySignedUrl('topup.show'))
            ->assertOk()
            ->assertSee('Cetak struk')
            ->assertSee('Cetak invoice');

        $this->get($order->temporarySignedUrl('topup.receipt'))
            ->assertOk()
            ->assertSee('STRUK PEMBAYARAN')
            ->assertSee($order->order_number)
            ->assertSee('Rp 11.000')
            ->assertSee('SN-PRINT-123456');

        $this->get($order->receiptPdfUrl())
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="'.$order->order_number.'.pdf"')
            ->assertSee('%PDF-', false)
            ->assertSee($order->order_number, false);

        $this->get($order->temporarySignedUrl('topup.invoice'))
            ->assertOk()
            ->assertSee('INVOICE')
            ->assertSee($order->order_number)
            ->assertSee('Rp 11.000')
            ->assertSee('SN-PRINT-123456');

        $this->getJson($order->temporarySignedUrl('topup.receipt'))
            ->assertOk()
            ->assertJsonPath('data.customer_name', 'Daffa')
            ->assertJsonPath('data.customer_email', 'da***@example.com')
            ->assertJsonPath('data.customer_phone', '082*****240')
            ->assertJsonPath('data.payment_method', 'QRIS')
            ->assertJsonPath('data.sku', 'TEL10')
            ->assertJsonPath('data.merchant.name', 'Younz Digital Center');

        $status = $this->getJson($order->temporarySignedUrl('topup.show'));
        $receiptPdfUrl = $status->json('data.receipt_pdf_url');
        $this->assertIsString($receiptPdfUrl);
        $this->assertStringContainsString('/struk.pdf', $receiptPdfUrl);
        $this->assertStringContainsString('signature=', $receiptPdfUrl);

        $this->getJson($order->temporarySignedUrl('topup.show'))
            ->assertOk()
            ->assertJsonMissingPath('data.customer_name')
            ->assertJsonMissingPath('data.customer_email');
    }

    public function test_unpaid_topup_cannot_open_receipt_or_invoice(): void
    {
        $order = $this->order();

        $this->get($order->temporarySignedUrl('topup.receipt'))->assertNotFound();
        $this->get($order->temporarySignedUrl('topup.invoice'))->assertNotFound();
        $this->get($order->receiptPdfUrl())->assertNotFound();
    }

    public function test_topup_documents_reject_unsigned_urls(): void
    {
        $order = $this->order([
            'payment_status' => TopupPaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        $this->get(route('topup.show', $order))->assertForbidden();
        $this->get(route('topup.receipt', $order))->assertForbidden();
        $this->get(route('topup.receipt.pdf', $order))->assertForbidden();
        $this->get(route('topup.invoice', $order))->assertForbidden();
    }

    public function test_midtrans_finish_parameters_do_not_invalidate_the_signed_status_url(): void
    {
        $order = $this->order();
        $finishUrl = $order->temporarySignedUrl('topup.show')
            .'&'.http_build_query([
                'order_id' => $order->midtrans_order_id,
                'status_code' => '200',
                'transaction_status' => 'settlement',
            ]);

        $this->get($finishUrl)
            ->assertOk()
            ->assertSee($order->order_number);
    }

    public function test_customer_can_regenerate_a_signed_topup_link_with_matching_checkout_data(): void
    {
        $order = $this->order();

        $response = $this->post(route('topup.access.resolve'), [
            'order_number' => strtolower($order->order_number),
            'customer_email' => strtoupper($order->customer_email),
            'customer_phone' => '0821-920-7240',
        ])->assertRedirect();
        $location = (string) $response->headers->get('Location');

        $this->assertStringContainsString('/topup/status/'.$order->public_token, $location);
        $this->assertStringContainsString('signature=', $location);
        $this->get($location)->assertOk();
    }

    public function test_topup_link_recovery_returns_a_generic_error_for_mismatched_data(): void
    {
        $order = $this->order();

        $this->from(route('topup.access'))->post(route('topup.access.resolve'), [
            'order_number' => $order->order_number,
            'customer_email' => 'wrong@example.com',
            'customer_phone' => $order->customer_phone,
        ])->assertRedirect(route('topup.access'))->assertSessionHasErrors('order_number');
    }

    public function test_postpaid_checkout_inquires_bill_before_creating_midtrans_payment(): void
    {
        $product = $this->postpaidProduct();
        Http::fake(function (Request $request) {
            if ($request->url() === 'https://api.digiflazz.com/v1/transaction') {
                return Http::response(['data' => [
                    'ref_id' => $request['ref_id'],
                    'customer_no' => $request['customer_no'],
                    'customer_name' => 'DAFFA YOUNZ',
                    'price' => 24700,
                    'selling_price' => 25000,
                    'status' => 'Sukses',
                    'message' => 'Inquiry berhasil',
                    'rc' => '00',
                    'desc' => ['lembar_tagihan' => 1],
                ]], 200);
            }

            return Http::response([
                'token' => 'snap-postpaid-token',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/postpaid',
            ], 201);
        });

        $response = $this->post(route('topup.store'), [
            'idempotency_key' => (string) Str::uuid(),
            'product_id' => $product->id,
            'destination' => '12345678901',
            'destination_confirmation' => '12345678901',
            'customer_name' => 'Daffa',
            'customer_email' => 'daffa@example.com',
            'customer_phone' => '08219207240',
            'terms' => '1',
        ]);

        $order = TopupOrder::query()->sole();
        $response->assertRedirectContains('/topup/status/'.$order->public_token);
        $this->assertSame(DigiflazzTransactionType::Postpaid, $order->transaction_type);
        $this->assertSame('DAFFA YOUNZ', $order->provider_customer_name);
        $this->assertSame(24700, $order->cost_price);
        $this->assertSame(5500, $order->admin_fee);
        $this->assertSame(30500, $order->total_amount);
        $this->assertNotNull($order->inquired_at);
        $this->assertNull($order->midtrans_redirect_url);
        $this->get($order->temporarySignedUrl('topup.show'))
            ->assertOk()
            ->assertSee('Nominal tagihan provider')
            ->assertSee('Biaya admin')
            ->assertSee('Buat pembayaran Midtrans')
            ->assertDontSee('Buka pembayaran Midtrans')
            ->assertSee('Rp 30.500');

        Http::assertSent(function (Request $request) use ($order): bool {
            return $request->url() === 'https://api.digiflazz.com/v1/transaction'
                && $request['commands'] === 'inq-pasca'
                && $request['ref_id'] === $order->order_number
                && $request['sign'] === md5('buyer-testdigiflazz-test-key'.$order->order_number);
        });
        Http::assertSentCount(1);

        $paymentResponse = $this->post($order->temporarySignedUrl('topup.pay'));
        $paymentResponse->assertRedirect('https://app.sandbox.midtrans.com/snap/v4/redirection/postpaid');
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://app.sandbox.midtrans.com/snap/v1/transactions'
                && $request['transaction_details']['gross_amount'] === 30500;
        });
    }

    public function test_postpaid_checkout_stores_encrypted_bill_details_after_a_duplicate_reference_retry(): void
    {
        $product = $this->postpaidProduct();
        $references = [];
        Http::fake(function (Request $request) use (&$references) {
            $references[] = $request['ref_id'];

            if (count($references) === 1) {
                return Http::response(['data' => [
                    'ref_id' => $request['ref_id'],
                    'status' => 'Gagal',
                    'message' => 'Ref ID tidak unik',
                    'rc' => '49',
                ]], 200);
            }

            return Http::response(['data' => [
                'ref_id' => $request['ref_id'],
                'customer_name' => 'DAFFA YOUNZ',
                'price' => 24700,
                'selling_price' => 25000,
                'status' => 'Sukses',
                'message' => 'Inquiry berhasil',
                'rc' => '00',
                'desc' => ['lembar_tagihan' => 1, 'daya' => 2200],
            ]], 200);
        });

        $response = $this->postJson('/api/v1/topup/checkout', [
            'idempotency_key' => (string) Str::uuid(),
            'product_id' => $product->id,
            'destination' => '12345678901',
            'destination_confirmation' => '12345678901',
            'customer_name' => 'Daffa',
            'customer_email' => 'daffa@example.com',
            'customer_phone' => '08219207240',
            'terms' => '1',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.transaction_type', 'postpaid')
            ->assertJsonPath('data.total_amount', 30500);
        $order = TopupOrder::query()->sole();
        $rawDetails = DB::table('topup_orders')->where('id', $order->id)->value('bill_details');

        $this->assertCount(2, $references);
        $this->assertNotSame($references[0], $references[1]);
        $this->assertSame(['lembar_tagihan' => 1, 'daya' => 2200], $order->fresh()->bill_details);
        $this->assertIsString($rawDetails);
        $this->assertNotSame(json_encode(['lembar_tagihan' => 1, 'daya' => 2200]), $rawDetails);
    }

    public function test_offline_topup_is_created_without_midtrans_and_verified_by_admin(): void
    {
        Queue::fake();
        Http::fake();
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $product = $this->product();
        Sanctum::actingAs($cashier, ['staff:digital']);

        $this->postJson('/api/v1/staff/digital-transactions/offline-topups', [
            'idempotency_key' => (string) Str::uuid(),
            'product_id' => $product->id,
            'destination' => '08219207240',
            'destination_confirmation' => '08219207240',
            'customer_name' => 'Pelanggan Toko',
            'customer_phone' => '08219207240',
        ])->assertCreated()
            ->assertJsonPath('data.source.code', 'offline')
            ->assertJsonPath('data.payment_status.code', 'pending');

        $order = TopupOrder::query()->sole();
        $this->assertNull($order->midtrans_redirect_url);
        $this->assertStringStartsWith('offline:', (string) $order->source_reference);
        $this->getJson('/api/v1/staff/digital-transactions')
            ->assertOk()
            ->assertJsonPath('data.topups.0.receipt_url', null)
            ->assertJsonPath('data.topups.0.invoice_url', null);
        Http::assertNothingSent();

        Sanctum::actingAs($admin, ['staff:digital']);
        $this->postJson('/api/v1/staff/digital-transactions/'.$order->order_number.'/verify-payment')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('confirmed');
        $this->postJson('/api/v1/staff/digital-transactions/'.$order->order_number.'/verify-payment', ['confirmed' => true])
            ->assertOk()
            ->assertJsonPath('data.payment_status.code', 'paid')
            ->assertJsonPath('data.fulfillment_status.code', 'queued');

        $this->assertSame(TopupPaymentStatus::Paid, $order->fresh()->payment_status);
        $this->assertSame(TopupFulfillmentStatus::Queued, $order->fresh()->fulfillment_status);

        $this->getJson($order->temporarySignedUrl('topup.invoice'))
            ->assertOk()
            ->assertJsonPath('data.payment_method', 'Tunai')
            ->assertJsonPath('data.source.code', 'offline')
            ->assertJsonPath('data.customer_email', null);
        $documents = $this->getJson('/api/v1/staff/digital-transactions')->assertOk();
        $receiptUrl = $documents->json('data.topups.0.receipt_url');
        $receiptPdfUrl = $documents->json('data.topups.0.receipt_pdf_url');
        $invoiceUrl = $documents->json('data.topups.0.invoice_url');
        $this->assertIsString($receiptUrl);
        $this->assertIsString($receiptPdfUrl);
        $this->assertIsString($invoiceUrl);
        $this->assertStringContainsString('/struk?', $receiptUrl);
        $this->assertStringContainsString('/struk.pdf?', $receiptPdfUrl);
        $this->assertStringContainsString('/invoice?', $invoiceUrl);
        $this->get($receiptUrl)->assertOk();
        $this->get($receiptPdfUrl)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get($invoiceUrl)->assertOk();
        Queue::assertPushed(ProcessTopupOrder::class, 1);
        Http::assertNothingSent();
    }

    public function test_offline_postpaid_inquires_bill_before_cash_verification(): void
    {
        Queue::fake();
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $product = $this->postpaidProduct();
        Http::fake([
            'api.digiflazz.com/v1/transaction' => Http::response(['data' => [
                'ref_id' => 'TOP-POSTPAID-OFFLINE',
                'customer_name' => 'DAFFA YOUNZ',
                'price' => 24700,
                'selling_price' => 25000,
                'status' => 'Sukses',
                'message' => 'Inquiry berhasil',
                'rc' => '00',
                'desc' => ['lembar_tagihan' => 1, 'daya' => 2200],
            ]], 200),
        ]);
        Sanctum::actingAs($cashier, ['staff:digital']);

        $this->getJson('/api/v1/staff/digital-transactions')
            ->assertOk()
            ->assertJsonFragment(['id' => $product->id, 'transaction_type' => 'postpaid']);

        $response = $this->postJson('/api/v1/staff/digital-transactions/offline-topups', [
            'idempotency_key' => (string) Str::uuid(),
            'product_id' => $product->id,
            'destination' => '12345678901',
            'destination_confirmation' => '12345678901',
            'customer_name' => 'Pelanggan Toko',
            'customer_phone' => '08219207240',
        ])->assertCreated()
            ->assertJsonPath('data.transaction_type', 'postpaid')
            ->assertJsonPath('data.provider_customer_name', 'DAFFA YOUNZ')
            ->assertJsonPath('data.total_amount', 30500);

        $this->assertStringContainsString('Total Rp 30.500', $response->json('message'));
        $order = TopupOrder::query()->sole();
        $this->assertSame(DigiflazzTransactionType::Postpaid, $order->transaction_type);
        $this->assertSame(24700, $order->cost_price);
        $this->assertSame(25000, $order->selling_price);
        $this->assertSame(5500, $order->admin_fee);
        $this->assertSame(30500, $order->total_amount);
        $this->assertNotNull($order->inquired_at);
        $this->assertNull($order->midtrans_redirect_url);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request['commands'] === 'inq-pasca');

        Sanctum::actingAs($admin, ['staff:digital']);
        $this->postJson('/api/v1/staff/digital-transactions/'.$order->order_number.'/verify-payment', ['confirmed' => true])
            ->assertOk()
            ->assertJsonPath('data.payment_status.code', 'paid')
            ->assertJsonPath('data.fulfillment_status.code', 'queued');

        Queue::assertPushed(ProcessTopupOrder::class, 1);
    }

    public function test_failed_offline_postpaid_inquiry_does_not_leave_an_unpayable_order(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $product = $this->postpaidProduct();
        Http::fake([
            'api.digiflazz.com/v1/transaction' => Http::response(['data' => [
                'status' => 'Gagal',
                'message' => 'Tagihan tidak ditemukan',
                'rc' => '01',
            ]], 200),
        ]);
        Sanctum::actingAs($cashier, ['staff:digital']);

        $this->postJson('/api/v1/staff/digital-transactions/offline-topups', [
            'idempotency_key' => (string) Str::uuid(),
            'product_id' => $product->id,
            'destination' => '12345678901',
            'destination_confirmation' => '12345678901',
            'customer_name' => 'Pelanggan Toko',
            'customer_phone' => '08219207240',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('destination');

        $this->assertDatabaseCount('topup_orders', 0);
    }

    public function test_cashier_cannot_verify_offline_topup_payment(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $order = $this->order(['source_reference' => 'offline:1:'.Str::uuid()]);
        Sanctum::actingAs($cashier, ['staff:digital']);

        $this->postJson('/api/v1/staff/digital-transactions/'.$order->order_number.'/verify-payment', ['confirmed' => true])
            ->assertForbidden();
    }

    public function test_owner_can_reverify_topup_payment_from_midtrans(): void
    {
        Queue::fake();
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $order = $this->order();
        Sanctum::actingAs($owner, ['staff:digital']);
        Http::fake([
            'api.sandbox.midtrans.com/v2/'.$order->midtrans_order_id.'/status' => Http::response([
                'order_id' => $order->midtrans_order_id,
                'status_code' => '200',
                'gross_amount' => '11000.00',
                'transaction_status' => 'settlement',
                'transaction_id' => 'midtrans-reverified-transaction',
                'payment_type' => 'qris',
                'fraud_status' => 'accept',
            ], 200),
        ]);

        $this->postJson('/api/v1/staff/digital-transactions/'.$order->order_number.'/verify-payment')
            ->assertOk()
            ->assertJsonPath('data.payment_status.code', 'paid')
            ->assertJsonPath('data.fulfillment_status.code', 'queued');

        $this->assertSame(TopupPaymentStatus::Paid, $order->fresh()->payment_status);
        $this->assertSame(TopupFulfillmentStatus::Queued, $order->fresh()->fulfillment_status);
        Queue::assertPushed(ProcessTopupOrder::class, 1);
    }

    public function test_admin_can_manually_verify_a_web_topup(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $order = $this->order();
        Sanctum::actingAs($admin, ['staff:digital']);

        $this->postJson('/api/v1/staff/digital-transactions/'.$order->order_number.'/verify-payment', [
            'manual_override' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('confirmed');

        $this->postJson('/api/v1/staff/digital-transactions/'.$order->order_number.'/verify-payment', [
            'confirmed' => true,
            'manual_override' => true,
        ])->assertOk()
            ->assertJsonPath('data.payment_status.code', 'paid')
            ->assertJsonPath('data.fulfillment_status.code', 'queued');

        $this->assertSame(TopupPaymentStatus::Paid, $order->fresh()->payment_status);
        Queue::assertPushed(ProcessTopupOrder::class, 1);
    }

    public function test_owner_can_recheck_a_provider_pending_topup_with_the_same_reference(): void
    {
        Queue::fake();
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $order = $this->order([
            'payment_status' => TopupPaymentStatus::Paid,
            'fulfillment_status' => TopupFulfillmentStatus::ProviderPending,
            'paid_at' => now()->subMinutes(10),
            'provider_rc' => '03',
            'provider_message' => 'Transaksi Pending',
        ]);
        Sanctum::actingAs($owner, ['staff:digital']);

        $this->postJson('/api/v1/staff/digital-transactions/'.$order->order_number.'/verify-payment')
            ->assertOk()
            ->assertJsonPath('message', 'Status provider sudah diperiksa dan masih menunggu proses.');

        Queue::assertPushed(ProcessTopupOrder::class, fn (ProcessTopupOrder $job): bool => $job->topupOrderId === $order->id);
    }

    public function test_cashier_cannot_reverify_topup_payment(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $order = $this->order();
        Sanctum::actingAs($cashier, ['staff:digital']);

        $this->postJson('/api/v1/staff/digital-transactions/'.$order->order_number.'/verify-payment')
            ->assertForbidden();
    }

    public function test_reverification_keeps_payment_unpaid_when_midtrans_is_pending(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $order = $this->order();
        Sanctum::actingAs($admin, ['staff:digital']);
        Http::fake([
            'api.sandbox.midtrans.com/v2/'.$order->midtrans_order_id.'/status' => Http::response([
                'order_id' => $order->midtrans_order_id,
                'status_code' => '201',
                'gross_amount' => '11000.00',
                'transaction_status' => 'pending',
                'transaction_id' => 'midtrans-pending-transaction',
                'payment_type' => 'qris',
            ], 200),
        ]);

        $this->postJson('/api/v1/staff/digital-transactions/'.$order->order_number.'/verify-payment')
            ->assertOk()
            ->assertJsonPath('data.payment_status.code', 'pending');

        $this->assertSame(TopupPaymentStatus::Pending, $order->fresh()->payment_status);
    }

    public function test_reverification_returns_a_safe_actionable_error_for_invalid_midtrans_status(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $order = $this->order();
        Sanctum::actingAs($admin, ['staff:digital']);
        Http::fake([
            'api.sandbox.midtrans.com/v2/'.$order->midtrans_order_id.'/status' => Http::response([
                'status_code' => '200',
                'status_message' => 'Success, transaction is found',
                'order_id' => 'DIFFERENT-ORDER',
                'gross_amount' => '11000.00',
                'transaction_status' => 'pending',
            ], 200),
        ]);

        $this->postJson('/api/v1/staff/digital-transactions/'.$order->order_number.'/verify-payment')
            ->assertStatus(502)
            ->assertJsonPath('error_code', 'MIDTRANS_STATUS_INVALID')
            ->assertJsonPath(
                'message',
                'Data status dari Midtrans tidak cocok dengan pesanan ini. Pastikan mode dan Server Key Midtrans sesuai, lalu coba lagi.',
            );

        $this->assertSame(TopupPaymentStatus::Pending, $order->fresh()->payment_status);
    }

    public function test_reverification_explains_when_midtrans_transaction_does_not_exist(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $order = $this->order();
        Sanctum::actingAs($admin, ['staff:digital']);
        Http::fake([
            'api.sandbox.midtrans.com/v2/'.$order->midtrans_order_id.'/status' => Http::response([
                'status_code' => '404',
                'status_message' => "Transaction doesn't exist.",
            ], 200),
        ]);

        $this->postJson('/api/v1/staff/digital-transactions/'.$order->order_number.'/verify-payment')
            ->assertStatus(502)
            ->assertJsonPath('error_code', 'MIDTRANS_TRANSACTION_MISSING')
            ->assertJsonPath(
                'message',
                'Transaksi pembayaran belum terbentuk di Midtrans. Gunakan tombol Buat pembayaran terlebih dahulu.',
            );

        $this->assertSame(TopupPaymentStatus::Pending, $order->fresh()->payment_status);
    }

    public function test_midtrans_webhook_verifies_signature_and_dispatches_fulfillment_only_once(): void
    {
        Queue::fake();
        $order = $this->order();
        $payload = [
            'order_id' => $order->midtrans_order_id,
            'status_code' => '200',
            'gross_amount' => '11000.00',
            'transaction_status' => 'settlement',
            'transaction_id' => 'midtrans-transaction-1',
            'payment_type' => 'qris',
            'fraud_status' => 'accept',
        ];
        $payload['signature_key'] = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'midtrans-test-server-key');

        $this->postJson(route('webhooks.midtrans'), array_replace($payload, ['signature_key' => 'invalid']))->assertUnauthorized();
        $this->assertSame(TopupPaymentStatus::Pending, $order->fresh()->payment_status);

        $this->postJson(route('webhooks.midtrans'), $payload)->assertOk();
        $this->postJson(route('webhooks.midtrans'), $payload)->assertOk();

        $this->assertSame(TopupPaymentStatus::Paid, $order->fresh()->payment_status);
        $this->assertSame(TopupFulfillmentStatus::Queued, $order->fresh()->fulfillment_status);
        Queue::assertPushed(ProcessTopupOrder::class, 1);
    }

    public function test_fulfillment_job_signs_digiflazz_request_and_is_idempotent_after_success(): void
    {
        $order = $this->order([
            'payment_status' => TopupPaymentStatus::Paid,
            'fulfillment_status' => TopupFulfillmentStatus::Queued,
            'paid_at' => now(),
        ]);
        Http::fake([
            'api.digiflazz.com/v1/transaction' => Http::response(['data' => [
                'ref_id' => $order->order_number,
                'status' => 'Sukses',
                'message' => 'Transaksi berhasil',
                'rc' => '00',
                'sn' => 'SN-123456',
            ]], 200),
        ]);

        $job = new ProcessTopupOrder($order->id);
        $job->handle(app(DigiflazzClient::class));
        $job->handle(app(DigiflazzClient::class));

        $fresh = $order->fresh();
        $this->assertSame(TopupFulfillmentStatus::Success, $fresh->fulfillment_status);
        $this->assertSame('SN-123456', $fresh->serial_number);
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($order): bool {
            return $request['ref_id'] === $order->order_number
                && $request['sign'] === md5('buyer-testdigiflazz-test-key'.$order->order_number)
                && $request['max_price'] === 10000;
        });
    }

    public function test_fulfillment_recovery_does_not_require_midtrans(): void
    {
        Queue::fake();
        config()->set('services.midtrans.enabled', false);
        config()->set('services.digiflazz.enabled', true);
        $order = $this->order([
            'payment_status' => TopupPaymentStatus::Paid,
            'fulfillment_status' => TopupFulfillmentStatus::Queued,
            'paid_at' => now(),
        ]);
        $this->travel(11)->minutes();
        $this->artisan('topup:recover-fulfillment')->assertSuccessful();
        Queue::assertPushed(ProcessTopupOrder::class, fn ($job) => $job->topupOrderId === $order->id);
        Http::assertNothingSent();
    }

    public function test_whatsapp_replay_cannot_change_destination_or_rearm_an_expired_draft(): void
    {
        $product = $this->product();
        $action = app(\App\Actions\Topup\CreateWhatsAppTopupOrder::class);
        $order = $action->handleDirect($product->id, '6281234567890', '6281234567890', 'replay-test');
        $this->assertSame($order->id, $action->handleDirect($product->id, '6281234567890', '6281234567890', 'replay-test')->id);
        try {
            $action->handleDirect($product->id, '6289999999999', '6281234567890', 'replay-test');
            $this->fail('Mismatched replay must be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Referensi pesan sudah digunakan untuk permintaan berbeda.', $exception->getMessage());
        }
        $order->update(['expires_at' => now()->subMinute()]);
        try {
            $action->handleDirect($product->id, '6281234567890', '6281234567890', 'replay-test');
            $this->fail('Expired draft must not be rearmed.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Transaksi tidak lagi menunggu konfirmasi. Buat permintaan baru.', $exception->getMessage());
        }
        $this->assertDatabaseCount('topup_orders', 1);
        Http::assertNothingSent();
    }

    public function test_draft_and_pending_claim_roll_back_together(): void
    {
        $product = $this->product();
        try {
            app(\App\Actions\Topup\CreateWhatsAppTopupOrder::class)->handleDirect(
                $product->id, '6281234567890', '6281234567890', 'claim-failure',
                function (): void { throw new \RuntimeException('Injected pending failure'); },
            );
            $this->fail('Claim failure must abort draft creation.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Injected pending failure', $error->getMessage());
        }
        $this->assertDatabaseCount('topup_orders', 0);
        Http::assertNothingSent();
    }

    public function test_payment_and_fulfillment_intent_roll_back_together(): void
    {
        $order = $this->order();
        DB::statement("CREATE TRIGGER reject_outbox BEFORE INSERT ON topup_fulfillment_outbox BEGIN SELECT RAISE(ABORT, 'injected outbox failure'); END");
        try {
            $order->update([
                'payment_status' => TopupPaymentStatus::Paid,
                'fulfillment_status' => TopupFulfillmentStatus::Queued,
            ]);
            $this->fail('Outbox failure must abort the payment transition.');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertSame(TopupPaymentStatus::Pending, $order->fresh()->payment_status);
            $this->assertDatabaseCount('topup_fulfillment_outbox', 0);
        } finally {
            DB::statement('DROP TRIGGER reject_outbox');
        }
        Http::assertNothingSent();
    }

    public function test_failed_queue_publish_leaves_fulfillment_intent_retryable(): void
    {
        $order = $this->order([
            'payment_status' => TopupPaymentStatus::Paid,
            'fulfillment_status' => TopupFulfillmentStatus::Queued,
        ]);
        Queue::shouldReceive('connection')->andThrow(new \RuntimeException('Injected queue outage'));
        $this->artisan('topup:recover-fulfillment')->assertFailed();
        $this->assertDatabaseHas('topup_fulfillment_outbox', [
            'topup_order_id' => $order->id, 'published_at' => null,
        ]);
        $this->assertSame(TopupPaymentStatus::Paid, $order->fresh()->payment_status);
        Http::assertNothingSent();
    }

    public function test_publisher_does_not_acknowledge_a_concurrently_rearmed_generation(): void
    {
        $order = $this->order([
            'payment_status' => TopupPaymentStatus::Paid,
            'fulfillment_status' => TopupFulfillmentStatus::Queued,
        ]);
        \Illuminate\Support\Facades\Bus::shouldReceive('dispatch')->once()->andReturnUsing(function () use ($order): void {
            $order->fresh()->save();
        });
        $this->artisan('topup:recover-fulfillment')->assertSuccessful();
        $this->assertDatabaseHas('topup_fulfillment_outbox', ['topup_order_id' => $order->id, 'published_at' => null]);
        Http::assertNothingSent();
    }

    public function test_requeued_order_rearms_existing_fulfillment_intent(): void
    {
        $order = $this->order([
            'payment_status' => TopupPaymentStatus::Paid,
            'fulfillment_status' => TopupFulfillmentStatus::Queued,
        ]);
        DB::table('topup_fulfillment_outbox')->update(['published_at' => now()]);
        $order->update(['fulfillment_status' => TopupFulfillmentStatus::Processing]);
        $order->update(['fulfillment_status' => TopupFulfillmentStatus::Queued]);
        $this->assertDatabaseCount('topup_fulfillment_outbox', 1);
        $this->assertDatabaseHas('topup_fulfillment_outbox', [
            'topup_order_id' => $order->id, 'published_at' => null,
        ]);
    }

    public function test_cache_loss_does_not_allow_a_second_active_provider_execution(): void
    {
        $order = $this->order([
            'payment_status' => TopupPaymentStatus::Paid,
            'fulfillment_status' => TopupFulfillmentStatus::Queued,
        ]);
        $calls = 0;
        Http::fake(['api.digiflazz.com/v1/transaction' => function () use ($order, &$calls) {
            $calls++;
            \Illuminate\Support\Facades\Cache::flush();
            if ($calls === 1) (new ProcessTopupOrder($order->id))->handle(app(DigiflazzClient::class));
            return Http::response(['data' => ['status' => 'Pending', 'rc' => '03']], 200);
        }]);
        (new ProcessTopupOrder($order->id))->handle(app(DigiflazzClient::class));
        $this->assertSame(1, $calls);
        $this->assertSame(TopupFulfillmentStatus::ProviderPending, $order->fresh()->fulfillment_status);
    }

    public function test_stale_provider_attempt_cannot_overwrite_a_newer_attempt(): void
    {
        $order = $this->order([
            'payment_status' => TopupPaymentStatus::Paid,
            'fulfillment_status' => TopupFulfillmentStatus::Queued,
        ]);
        Http::fake(['api.digiflazz.com/v1/transaction' => function () use ($order) {
            $order->fresh()->update(['execution_token' => (string) Str::uuid(), 'provider_rc' => 'NEWER']);
            return Http::response(['data' => ['status' => 'Gagal', 'rc' => 'OLD']], 200);
        }]);
        (new ProcessTopupOrder($order->id))->handle(app(DigiflazzClient::class));
        $this->assertSame('NEWER', $order->fresh()->provider_rc);
        $this->assertSame(TopupFulfillmentStatus::Processing, $order->fresh()->fulfillment_status);
    }

    public function test_late_pending_http_response_cannot_overwrite_webhook_success(): void
    {
        $order = $this->order([
            'payment_status' => TopupPaymentStatus::Paid,
            'fulfillment_status' => TopupFulfillmentStatus::Queued,
            'paid_at' => now(),
        ]);
        Http::fake(['api.digiflazz.com/v1/transaction' => function () use ($order) {
            $order->fresh()->update([
                'fulfillment_status' => TopupFulfillmentStatus::Success,
                'serial_number' => 'WEBHOOK-SUCCESS',
                'fulfilled_at' => now(),
            ]);
            return Http::response(['data' => ['status' => 'Pending', 'rc' => '03']], 200);
        }]);
        (new ProcessTopupOrder($order->id))->handle(app(DigiflazzClient::class));
        $this->assertSame(TopupFulfillmentStatus::Success, $order->fresh()->fulfillment_status);
        $this->assertSame('WEBHOOK-SUCCESS', $order->fresh()->serial_number);
        Http::assertSentCount(1);
    }

    public function test_postpaid_fulfillment_pays_bill_with_same_inquiry_reference(): void
    {
        $order = $this->order([
            'transaction_type' => DigiflazzTransactionType::Postpaid,
            'payment_status' => TopupPaymentStatus::Paid,
            'fulfillment_status' => TopupFulfillmentStatus::Queued,
            'paid_at' => now(),
            'inquired_at' => now()->subMinute(),
            'cost_price' => 24700,
            'selling_price' => 25000,
            'total_amount' => 25000,
        ]);
        Http::fake([
            'api.digiflazz.com/v1/transaction' => Http::response(['data' => [
                'ref_id' => $order->order_number,
                'status' => 'Sukses',
                'message' => 'Pembayaran berhasil',
                'rc' => '00',
                'sn' => 'PLN/PAID/123',
            ]], 200),
        ]);

        (new ProcessTopupOrder($order->id))->handle(app(DigiflazzClient::class));

        $fresh = $order->fresh();
        $this->assertSame(TopupFulfillmentStatus::Success, $fresh->fulfillment_status);
        $this->assertNotNull($fresh->provider_payment_requested_at);
        Http::assertSent(function (Request $request) use ($order): bool {
            return $request['commands'] === 'pay-pasca'
                && $request['ref_id'] === $order->order_number
                && $request['customer_no'] === $order->destination;
        });
    }

    public function test_postpaid_fulfillment_retries_payment_after_a_definitive_provider_rejection(): void
    {
        $order = $this->order([
            'transaction_type' => DigiflazzTransactionType::Postpaid,
            'payment_status' => TopupPaymentStatus::Paid,
            'fulfillment_status' => TopupFulfillmentStatus::Queued,
            'paid_at' => now(),
            'inquired_at' => now()->subMinute(),
            'cost_price' => 24700,
            'selling_price' => 25000,
            'total_amount' => 25000,
        ]);
        Http::fake([
            'api.digiflazz.com/v1/transaction' => Http::response([
                'data' => ['message' => 'IP Anda tidak kami kenali'],
            ], 400),
        ]);

        try {
            (new ProcessTopupOrder($order->id))->handle(app(DigiflazzClient::class));
            $this->fail('RequestException was not thrown.');
        } catch (\Illuminate\Http\Client\RequestException) {
            // Expected: the provider definitively rejected the request before payment.
        }

        $fresh = $order->fresh();
        $this->assertSame(TopupFulfillmentStatus::Queued, $fresh->fulfillment_status);
        $this->assertNull($fresh->provider_payment_requested_at);
    }

    public function test_digiflazz_webhook_requires_hmac_and_updates_provider_status_idempotently(): void
    {
        $order = $this->order([
            'payment_status' => TopupPaymentStatus::Paid,
            'fulfillment_status' => TopupFulfillmentStatus::ProviderPending,
        ]);
        $body = json_encode(['data' => [
            'ref_id' => $order->order_number,
            'status' => 'Sukses',
            'message' => 'Berhasil',
            'rc' => '00',
            'sn' => 'TOKEN-9876',
        ]], JSON_THROW_ON_ERROR);

        $this->call('POST', route('webhooks.digiflazz'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE' => 'sha1=invalid',
        ], $body)->assertUnauthorized();

        $signature = 'sha1='.hash_hmac('sha1', $body, 'webhook-test-secret');
        $this->call('POST', route('webhooks.digiflazz'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE' => $signature,
        ], $body)->assertOk();

        $this->assertSame(TopupFulfillmentStatus::Success, $order->fresh()->fulfillment_status);
        $this->assertSame('TOKEN-9876', $order->fresh()->serial_number);
    }

    public function test_successful_automatic_topup_is_visible_in_digital_transactions_and_daily_report(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $order = $this->order([
            'source_reference' => 'whatsapp:'.hash('sha256', 'report-topup'),
            'payment_status' => TopupPaymentStatus::Paid,
            'fulfillment_status' => TopupFulfillmentStatus::Success,
            'provider_rc' => '00',
            'midtrans_status' => 'settlement',
            'paid_at' => now(),
            'fulfilled_at' => now(),
        ]);

        $this->actingAs($owner)->get(route('digital.index'))
            ->assertOk()
            ->assertSee('Top up otomatis')
            ->assertSee($order->order_number)
            ->assertSee($order->product_name)
            ->assertSee('082*****240')
            ->assertSee('WhatsApp')
            ->assertSee('Rp 11.000')
            ->assertSee('Rp 1.000')
            ->assertSee('Top up berhasil');

        $this->actingAs($owner)->get(route('reports.daily', ['date' => now()->toDateString()]))
            ->assertOk()
            ->assertSee('Top up otomatis berhasil (1)')
            ->assertSee($order->order_number)
            ->assertSee('Rp 11.000')
            ->assertSee('Rp 1.000');
    }

    private function product(): DigiflazzProduct
    {
        return DigiflazzProduct::create([
            'buyer_sku_code' => 'TEL10',
            'product_name' => 'Pulsa Telkomsel 10.000',
            'category' => 'Pulsa',
            'brand' => 'Telkomsel',
            'type' => 'Umum',
            'cost_price' => 10000,
            'selling_price' => 11000,
            'buyer_product_status' => true,
            'seller_product_status' => true,
            'unlimited_stock' => true,
            'stock' => 0,
            'multi' => false,
            'synced_at' => now(),
        ]);
    }

    private function postpaidProduct(): DigiflazzProduct
    {
        return DigiflazzProduct::create([
            'transaction_type' => DigiflazzTransactionType::Postpaid,
            'buyer_sku_code' => 'PLNPOST',
            'product_name' => 'PLN Pascabayar',
            'category' => 'Pascabayar',
            'brand' => 'PLN',
            'cost_price' => 0,
            'selling_price' => 0,
            'provider_admin' => 2500,
            'provider_commission' => 500,
            'buyer_product_status' => true,
            'seller_product_status' => true,
            'unlimited_stock' => true,
            'stock' => 0,
            'multi' => false,
            'synced_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function order(array $overrides = []): TopupOrder
    {
        $product = $this->product();
        $number = 'TOP-20260721-0001';

        return TopupOrder::create(array_replace([
            'transaction_type' => DigiflazzTransactionType::Prepaid,
            'order_number' => $number,
            'public_token' => (string) Str::uuid(),
            'digiflazz_product_id' => $product->id,
            'idempotency_key' => (string) Str::uuid(),
            'request_fingerprint' => hash('sha256', 'request'),
            'sku' => $product->buyer_sku_code,
            'product_name' => $product->product_name,
            'category' => $product->category,
            'brand' => $product->brand,
            'destination' => '08219207240',
            'customer_name' => 'Daffa',
            'customer_email' => 'daffa@example.com',
            'customer_phone' => '08219207240',
            'cost_price' => 10000,
            'selling_price' => 11000,
            'admin_fee' => 0,
            'total_amount' => 11000,
            'payment_status' => TopupPaymentStatus::Pending,
            'fulfillment_status' => TopupFulfillmentStatus::WaitingPayment,
            'midtrans_order_id' => $number,
            'digiflazz_reference' => $number,
            'expires_at' => now()->addHour(),
        ], $overrides));
    }
}
