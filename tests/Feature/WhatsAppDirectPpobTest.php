<?php

namespace Tests\Feature;

use App\Enums\DigiflazzTransactionType;
use App\Enums\TopupFulfillmentStatus;
use App\Enums\TopupPaymentStatus;
use App\Jobs\ProcessTopupOrder;
use App\Jobs\ProcessWhatsAppInboundMessage;
use App\Models\DigiflazzProduct;
use App\Models\TopupOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsAppDirectPpobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'array');
        config()->set('services.whatsapp.gateway_url', 'http://whatsapp.test');
        config()->set('services.whatsapp.internal_token', 'test-internal-token-123456');
        config()->set('services.digiflazz.enabled', true);
        config()->set('services.digiflazz.username', 'test-user');
        config()->set('services.digiflazz.api_key', 'test-key');
        config()->set('services.digiflazz.endpoint', 'https://api.digiflazz.com/v1');
        config()->set('services.digiflazz.testing', true);
        config()->set('services.younz_ppob.whatsapp_confirmation_hash', Hash::make('24681357'));
        config()->set('services.younz_ppob.whatsapp_operator_numbers', ['6281234567890']);
    }

    public function test_direct_prepaid_purchase_requires_confirmation_before_queueing_provider_job(): void
    {
        Queue::fake();
        Http::fake([
            'http://whatsapp.test/v1/messages' => Http::response(['accepted' => true], 202),
            'https://api.digiflazz.com/v1/transaction' => Http::response(['data' => [
                'status' => 'sukses',
                'rc' => '00',
                'message' => 'Transaksi berhasil',
                'sn' => 'SN-TEST-1',
            ]]),
        ]);
        $product = $this->product('TELKOMSEL10', 'Pulsa Telkomsel 10.000', 'Pulsa', 'Telkomsel', 10000, 12000);

        $this->inbound('direct-prepaid-1', 'Beli pulsa Telkomsel 10.000 ke nomor 081234567890');

        $order = TopupOrder::query()->sole();
        $this->assertSame($product->id, $order->digiflazz_product_id);
        $this->assertSame(TopupPaymentStatus::Pending, $order->payment_status);
        $this->assertSame(TopupFulfillmentStatus::WaitingPayment, $order->fulfillment_status);
        Queue::assertNotPushed(ProcessTopupOrder::class);
        Http::assertSent(fn (Request $request) => $request->url() === 'http://whatsapp.test/v1/messages'
            && str_contains((string) $request['text'], 'sandi konfirmasi operator')
            && ! str_contains((string) $request['text'], '24681357'));

        $this->inbound('direct-prepaid-2', '24681356');
        $this->assertSame(TopupPaymentStatus::Pending, $order->fresh()->payment_status);
        Queue::assertNotPushed(ProcessTopupOrder::class);

        $this->inbound('direct-prepaid-3', '24681357');

        $order->refresh();
        $this->assertSame(TopupPaymentStatus::Paid, $order->payment_status);
        $this->assertSame(TopupFulfillmentStatus::Queued, $order->fulfillment_status);
        Queue::assertPushed(ProcessTopupOrder::class, fn (ProcessTopupOrder $job): bool => $job->topupOrderId === $order->id);
        Http::assertSent(fn (Request $request) => $request->url() === 'http://whatsapp.test/v1/messages'
            && str_contains((string) $request['text'], 'sudah masuk antrean'));
    }

    public function test_direct_postpaid_purchase_inquires_then_queues_only_after_confirmation(): void
    {
        Queue::fake();
        Http::fake(function (Request $request) {
            if ($request->url() === 'http://whatsapp.test/v1/messages') {
                return Http::response(['accepted' => true], 202);
            }

            if ($request->url() === 'https://api.digiflazz.com/v1/transaction'
                && $request['commands'] === 'inq-pasca'
            ) {
                return Http::response(['data' => [
                    'status' => 'sukses',
                    'rc' => '00',
                    'price' => 50000,
                    'selling_price' => 55000,
                    'customer_name' => 'Pelanggan PLN',
                    'desc' => ['periode' => '2026-09'],
                ]]);
            }

            return Http::response(['data' => ['status' => 'pending', 'rc' => '03']]);
        });
        $this->product('PLNPASCA', 'PLN Pascabayar', 'Pascabayar', 'PLN', 0, 0, DigiflazzTransactionType::Postpaid);
        config()->set('services.digiflazz.postpaid_admin_fee', 4000);

        $this->inbound('direct-postpaid-1', 'Beli PLN pascabayar ke nomor 12345678901');

        $order = TopupOrder::query()->sole();
        $this->assertSame(DigiflazzTransactionType::Postpaid, $order->transaction_type);
        $this->assertSame(59000, $order->total_amount);
        $this->assertNotNull($order->inquired_at);
        $this->assertSame(TopupPaymentStatus::Pending, $order->payment_status);
        Queue::assertNotPushed(ProcessTopupOrder::class);

        $this->inbound('direct-postpaid-2', 'konfirmasi: 24681357');

        $order->refresh();
        $this->assertSame(TopupPaymentStatus::Paid, $order->payment_status);
        $this->assertSame(TopupFulfillmentStatus::Queued, $order->fulfillment_status);
        Queue::assertPushed(ProcessTopupOrder::class, 1);
        Http::assertSentCount(3);
    }

    public function test_unlisted_whatsapp_number_cannot_create_direct_purchase(): void
    {
        Queue::fake();
        Http::fake(['http://whatsapp.test/v1/messages' => Http::response(['accepted' => true], 202)]);
        $this->product('TELKOMSEL10', 'Pulsa Telkomsel 10.000', 'Pulsa', 'Telkomsel', 10000, 12000);

        $this->inbound('direct-unauthorized-1', 'Beli pulsa Telkomsel 10.000 ke nomor 081234567890', '6289999999999');

        $this->assertDatabaseCount('topup_orders', 0);
        Queue::assertNotPushed(ProcessTopupOrder::class);
        Http::assertSent(fn (Request $request) => str_contains((string) $request['text'], 'nomor operator yang terdaftar'));
    }

    public function test_bill_inquiry_understands_customer_number_variants_without_ai_or_payment(): void
    {
        $this->fakeBillInquiry();
        $product = $this->product('PDAMSAMPIT', 'PDAM Sampit', 'Pascabayar', 'PDAM', 0, 0, DigiflazzTransactionType::Postpaid);
        config()->set('services.digiflazz.postpaid_admin_fee', 4000);

        foreach ([
            'Cek tagihan PDAM 001234567890',
            'Cek tagihan PDAM dengan nomor 001234567890',
            'Cek tagihan PDAM Sampit nomor 001234567890',
            'CEK TAGIHAN PDAM ke nomor 001234567890',
            'cek tagihan PDAM no. 001234567890',
            'cek tagihan PDAM ID pelanggan: 001234567890',
            'cek tagihan PDAM dengan nomor pelanggan 001234567890',
            "  cek   tagihan PDAM\nnomor 0012-3456-7890  ",
        ] as $index => $message) {
            Cache::flush(); // Test setup forces the isolated in-memory array cache.
            \Illuminate\Support\Facades\DB::table('whatsapp_pending_confirmations')->delete(); // Independent parser fixtures, not a production reset.
            $this->inbound('bill-variant-'.$index, $message);
            $order = TopupOrder::query()->latest('id')->firstOrFail();
            $this->assertSame($product->id, $order->digiflazz_product_id);
            $this->assertSame('001234567890', $order->destination);
            $this->assertSame(59000, $order->total_amount);
            $this->assertNotNull($order->inquired_at);
            $this->assertSame(TopupPaymentStatus::Pending, $order->payment_status);
            $this->assertSame(TopupFulfillmentStatus::WaitingPayment, $order->fulfillment_status);
            $this->assertNull($order->provider_payment_requested_at);
            $this->assertNull($order->midtrans_redirect_url);
        }

        $this->assertDatabaseCount('topup_orders', 8);
        $this->assertDatabaseCount('ai_usage_logs', 0);
        Queue::assertNotPushed(ProcessTopupOrder::class);
        Http::assertSentCount(16);
        Http::assertNotSent(fn (Request $request) => ($request->data()['commands'] ?? null) === 'pay-pasca');
        Http::assertSent(fn (Request $request) => $request->url() === 'http://whatsapp.test/v1/messages'
            && str_contains((string) $request['text'], 'Tagihan ditemukan')
            && str_contains((string) $request['text'], 'Belum dibayar')
            && str_contains((string) $request['text'], 'Rp59.000')
            && ! str_contains((string) $request['text'], '001234567890'));
    }

    public function test_bill_inquiry_selects_only_postpaid_and_queues_once_after_valid_operator_confirmation(): void
    {
        $this->fakeBillInquiry();
        $this->product('PLNTOKEN', 'PLN Token 20.000', 'PLN', 'PLN', 20000, 22000);
        $pasca = $this->product('PLNPASCA', 'PLN Pascabayar', 'Pascabayar', 'PLN', 0, 0, DigiflazzTransactionType::Postpaid);

        $this->inbound('bill-pln', 'Cek tagihan PLN nomor 001234567890');
        $order = TopupOrder::query()->sole();
        $this->assertSame($pasca->id, $order->digiflazz_product_id);
        Queue::assertNotPushed(ProcessTopupOrder::class);
        $this->inbound('bill-wrong-code', '24681356');
        $this->assertSame(TopupPaymentStatus::Pending, $order->fresh()->payment_status);
        Queue::assertNotPushed(ProcessTopupOrder::class);
        $this->inbound('bill-valid-code', 'konfirmasi: 24681357');
        $this->assertSame(TopupPaymentStatus::Paid, $order->fresh()->payment_status);
        Queue::assertPushed(ProcessTopupOrder::class, 1);
        Http::assertNotSent(fn (Request $request) => ($request->data()['commands'] ?? null) === 'pay-pasca');
    }

    public function test_unlisted_number_cannot_inquire_or_confirm_an_operator_bill(): void
    {
        $this->fakeBillInquiry();
        $this->product('PDAMSAMPIT', 'PDAM Sampit', 'PDAM', 'PDAM', 0, 0, DigiflazzTransactionType::Postpaid);
        $this->inbound('bill-unlisted', 'Cek tagihan PDAM 001234567890', '6289999999999');
        $this->assertDatabaseCount('topup_orders', 0);
        Http::assertSentCount(1);
        $this->inbound('bill-allowed', 'Cek tagihan PDAM 001234567890');
        // Revoking operator access also revokes the ability to confirm an existing draft.
        config()->set('services.younz_ppob.whatsapp_operator_numbers', []);
        $this->inbound('bill-revoked', '24681357');
        $this->assertSame(TopupPaymentStatus::Pending, TopupOrder::query()->sole()->payment_status);
        Queue::assertNotPushed(ProcessTopupOrder::class);
    }

    public function test_incomplete_bill_commands_do_not_fall_back_to_ai_or_contact_provider(): void
    {
        $this->fakeBillInquiry();
        foreach (['Cek tagihan', 'Cek tagihan PDAM', 'Cek tagihan PDAM 123',
            'Cek tagihan PDAM 1234567890123456789012345', 'Cek tagihan % 001234567890'] as $index => $message) {
            $this->inbound('bill-invalid-'.$index, $message);
        }
        $this->assertDatabaseCount('topup_orders', 0);
        $this->assertDatabaseCount('ai_usage_logs', 0);
        Http::assertSentCount(5);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'digiflazz'));
        Queue::assertNotPushed(ProcessTopupOrder::class);
    }

    public function test_ambiguous_unavailable_or_prepaid_only_bill_products_do_not_create_orders(): void
    {
        $this->fakeBillInquiry();
        $this->product('PDAM1', 'PDAM Sampit', 'PDAM', 'PDAM', 0, 0, DigiflazzTransactionType::Postpaid);
        $this->product('PDAM2', 'PDAM Kota Lain', 'PDAM', 'PDAM', 0, 0, DigiflazzTransactionType::Postpaid);
        $this->product('PLNTOKEN', 'PLN Token', 'PLN', 'PLN', 20000, 22000);
        $this->product('BPJS', 'BPJS Kesehatan', 'BPJS', 'BPJS', 0, 0, DigiflazzTransactionType::Postpaid)
            ->update(['seller_product_status' => false]);
        $this->inbound('bill-ambiguous', 'Cek tagihan PDAM 001234567890');
        $this->inbound('bill-prepaid', 'Cek tagihan PLN 001234567890');
        $this->inbound('bill-unavailable', 'Cek tagihan BPJS 001234567890');
        $this->assertDatabaseCount('topup_orders', 0);
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request) => str_contains((string) $request['text'], 'Ada beberapa layanan pascabayar'));
        Queue::assertNotPushed(ProcessTopupOrder::class);
    }

    public function test_failed_bill_inquiry_never_arms_payment_confirmation(): void
    {
        $this->fakeBillInquiry(['status' => 'Gagal', 'rc' => '14', 'message' => 'Nomor pelanggan tidak ditemukan']);
        $this->product('PDAM1', 'PDAM Sampit', 'PDAM', 'PDAM', 0, 0, DigiflazzTransactionType::Postpaid);
        $this->inbound('bill-provider-failure', 'Cek tagihan PDAM 001234567890');
        $order = TopupOrder::query()->sole();
        $this->assertNull($order->inquired_at);
        $this->assertSame(TopupPaymentStatus::Pending, $order->payment_status);
        $this->assertFalse(Cache::has('whatsapp:ppob:confirmation:'.hash('sha256', '6281234567890')));
        Http::assertSent(fn (Request $request) => str_contains((string) ($request->data()['text'] ?? ''), 'Nomor pelanggan tidak ditemukan'));
        Queue::assertNotPushed(ProcessTopupOrder::class);
    }

    public function test_new_bill_request_cannot_replace_pending_confirmation(): void
    {
        $this->fakeBillInquiry();
        $this->product('PDAM1', 'PDAM Sampit', 'PDAM', 'PDAM', 0, 0, DigiflazzTransactionType::Postpaid);
        $this->inbound('bill-first', 'Cek tagihan PDAM 001234567890');
        $this->inbound('bill-second', 'Cek tagihan PDAM 009876543210');
        $this->assertDatabaseCount('topup_orders', 1);
        $this->assertSame('001234567890', TopupOrder::query()->sole()->destination);
        Http::assertSentCount(3);
        Queue::assertNotPushed(ProcessTopupOrder::class);
    }

    public function test_guided_package_phone_and_explicit_confirmation(): void
    {
        Queue::fake();
        Http::fake(['http://whatsapp.test/v1/messages' => Http::response(['accepted' => true], 202)]);
        $product = $this->product('AXIS10', 'AXIS 10GB 7 hari', 'Data', 'AXIS', 10000, 12000);
        $this->inbound('guided-list', 'pembayaran apa yang tersedia?');
        $this->inbound('guided-group', '1');
        $this->inbound('guided-package', '1');
        $this->assertDatabaseCount('topup_orders', 0);
        $this->inbound('guided-invalid', '24681357');
        $this->assertDatabaseCount('topup_orders', 0);
        $this->inbound('guided-phone', '081234567890');
        $order = TopupOrder::query()->sole();
        $this->assertSame($product->id, $order->digiflazz_product_id);
        $this->assertSame('6281234567890', $order->destination);
        $this->inbound('guided-phone-repeat', '081234567890');
        $this->inbound('guided-bare-code', '24681357');
        $this->inbound('guided-wrong', 'KONFIRMASI 11111111');
        $this->assertSame(TopupPaymentStatus::Pending, $order->fresh()->payment_status);
        Queue::assertNotPushed(ProcessTopupOrder::class);
        $this->inbound('guided-confirm', 'KONFIRMASI 24681357');
        $this->inbound('guided-confirm-repeat', 'KONFIRMASI 24681357');
        $this->assertSame(TopupPaymentStatus::Paid, $order->fresh()->payment_status);
        Queue::assertPushed(ProcessTopupOrder::class, 1);
        $this->assertDatabaseCount('ai_usage_logs', 0);
        $this->assertDatabaseCount('topup_orders', 1);
        Http::assertNotSent(fn (Request $request) => ! str_starts_with($request->url(), 'http://whatsapp.test/'));
    }

    public function test_guided_cancel_and_expiry_do_not_purchase(): void
    {
        Queue::fake();
        Http::fake(['http://whatsapp.test/v1/messages' => Http::response(['accepted' => true], 202)]);
        $this->product('AXIS10', 'AXIS 10GB 7 hari', 'Data', 'AXIS', 10000, 12000);
        $this->inbound('cancel-list', 'pembayaran apa yang tersedia?');
        $this->inbound('cancel-group', '1');
        $this->inbound('cancel-package', '1');
        $this->inbound('cancel-phone', '081234567890');
        $this->inbound('cancel', 'BATAL');
        $this->inbound('cancel-confirm', 'KONFIRMASI 24681357');
        $this->assertTrue(TopupOrder::query()->sole()->expires_at->isPast());
        Queue::assertNotPushed(ProcessTopupOrder::class);
        $this->inbound('expiry-list', 'pembayaran apa yang tersedia?');
        $this->inbound('expiry-group', '1');
        $this->inbound('expiry-package', '1');
        $this->travel(16)->minutes();
        $this->inbound('expiry-phone', '081234567890');
        $this->assertDatabaseCount('topup_orders', 1);
        $this->assertDatabaseCount('ai_usage_logs', 0);
    }

    public function test_pending_confirmation_survives_cache_loss_without_replacement(): void
    {
        Queue::fake();
        Http::fake(['http://whatsapp.test/v1/messages' => Http::response(['accepted' => true], 202)]);
        $this->product('TELKOMSEL10', 'Pulsa Telkomsel 10.000', 'Pulsa', 'Telkomsel', 10000, 12000);
        $this->inbound('persistent-first', 'Beli pulsa Telkomsel 10.000 ke nomor 081234567890');
        Cache::flush();
        $this->inbound('persistent-second', 'Beli pulsa Telkomsel 10.000 ke nomor 081234567891');
        $this->assertDatabaseCount('topup_orders', 1);
        $this->inbound('persistent-confirm', 'KONFIRMASI 24681357');
        Queue::assertPushed(ProcessTopupOrder::class, 1);
        $this->assertDatabaseCount('topup_fulfillment_outbox', 1);
    }

    public function test_guided_change_number_invalidates_old_draft_and_can_change_package(): void
    {
        Queue::fake();
        Http::fake(['http://whatsapp.test/v1/messages' => Http::response(['accepted' => true], 202)]);
        $this->product('AXIS10', 'AXIS 10GB', 'Data', 'AXIS', 10000, 12000);
        $this->inbound('nav-list', 'pembayaran apa yang tersedia?');
        $this->inbound('nav-group', '1');
        $this->inbound('nav-package', '1');
        $this->inbound('nav-phone', '081234567890');
        $old = TopupOrder::query()->sole();
        Cache::flush();
        $this->inbound('nav-change', 'GANTI NOMOR');
        $this->assertTrue($old->fresh()->expires_at->isPast());
        $this->inbound('nav-phone-new', '081234567891');
        $this->assertDatabaseCount('topup_orders', 2);
        $this->assertSame('6281234567891', TopupOrder::query()->latest('id')->first()->destination);
        $this->inbound('nav-packages', 'GANTI PAKET');
        $this->inbound('nav-back', 'KEMBALI');
        $this->assertDatabaseCount('ai_usage_logs', 0);
        Queue::assertNotPushed(ProcessTopupOrder::class);
    }

    public function test_status_lookup_preserves_pending_and_never_calls_ai_or_provider(): void
    {
        Queue::fake();
        Http::fake(['http://whatsapp.test/v1/messages' => Http::response(['accepted' => true], 202)]);
        $this->product('STATUS10', 'Pulsa Telkomsel 10.000', 'Pulsa', 'Telkomsel', 10000, 12000);
        $this->inbound('status-create', 'Beli pulsa Telkomsel 10.000 ke nomor 089999999999');
        $order = TopupOrder::query()->sole();
        $order->update(['customer_phone' => '+62 812-3456-7890', 'provider_message' => 'PRIVATE PROVIDER DATA']);
        $before = $order->getRawOriginal();
        $pending = \Illuminate\Support\Facades\DB::table('whatsapp_pending_confirmations')->first();
        $this->inbound('status-read', '  CeK   transaksi '.strtolower($order->order_number).'  ');
        Http::assertSent(fn (Request $request) => str_contains((string) $request['text'], '*Status Transaksi '.$order->order_number)
            && str_contains((string) $request['text'], $order->payment_status->label())
            && ! str_contains((string) $request['text'], '089999999999')
            && ! str_contains((string) $request['text'], 'PRIVATE PROVIDER DATA'));
        $this->assertSame($before, $order->fresh()->getRawOriginal());
        $this->assertEquals($pending, \Illuminate\Support\Facades\DB::table('whatsapp_pending_confirmations')->first());
        $this->assertDatabaseCount('ai_usage_logs', 0);
        $this->assertDatabaseCount('topup_orders', 1);
        Http::assertSentCount(2);
        Queue::assertNothingPushed();
    }

    public function test_status_lookup_denies_other_senders_and_invalid_identities(): void
    {
        Queue::fake();
        Http::fake(['http://whatsapp.test/v1/messages' => Http::response(['accepted' => true], 202)]);
        $this->product('STATUS10', 'Pulsa Telkomsel 10.000', 'Pulsa', 'Telkomsel', 10000, 12000);
        $this->inbound('status-create', 'Beli pulsa Telkomsel 10.000 ke nomor 089999999999');
        $order = TopupOrder::query()->sole();
        $lookup = app(\App\Support\WhatsAppTopupStatus::class);
        $other = '6289999999999@s.whatsapp.net';
        $owner = '6281234567890@s.whatsapp.net';
        $denied = $lookup->answer('Cek transaksi TOP-19990101-0001', $other, $other);
        $this->assertSame($denied, $lookup->answer('Cek transaksi '.$order->order_number, $other, $other));
        foreach ([[$owner, $other], [$owner, '123@g.us'], ['123@lid', '123@lid']] as [$sender, $chat]) {
            $this->assertSame($denied, $lookup->answer('Cek transaksi '.$order->order_number, $sender, $chat));
        }
        $order->update(['customer_phone' => '']);
        $this->assertSame($denied, $lookup->answer('Cek transaksi '.$order->order_number, $owner, $owner));
        Queue::assertNothingPushed();
    }

    public function test_invalid_status_commands_are_deterministic_and_rate_limited(): void
    {
        Queue::fake();
        Http::fake(['http://whatsapp.test/v1/messages' => Http::response(['accepted' => true], 202)]);
        $stateKey = 'whatsapp:guided:v1:'.hash('sha256', '6281234567890');
        \App\Support\WhatsAppNavigationState::put($stateKey, ['product_id' => 999], now()->addMinutes(15));
        for ($i = 0; $i < 11; $i++) {
            $this->inbound('status-invalid-'.$i, 'Cek transaksi');
        }
        Http::assertSent(fn (Request $request) => str_contains((string) $request['text'], 'Gunakan: *Cek transaksi'));
        Http::assertSent(fn (Request $request) => str_contains((string) $request['text'], 'Terlalu banyak pengecekan'));
        $this->assertSame(['product_id' => 999], \App\Support\WhatsAppNavigationState::get($stateKey));
        $this->assertNull(app(\App\Support\WhatsAppTopupStatus::class)->answer('Cek tagihan PLN 1234567890', '6281234567890@s.whatsapp.net', '6281234567890@s.whatsapp.net'));
        $this->assertDatabaseCount('topup_orders', 0);
        $this->assertDatabaseCount('ai_usage_logs', 0);
        Http::assertSentCount(11);
        Queue::assertNothingPushed();
    }

    public function test_gopay_catalog_selection_requires_explicit_confirmation(): void
    {
        Queue::fake();
        Http::fake(['http://whatsapp.test/v1/messages' => Http::response(['accepted' => true], 202)]);
        $product = $this->product('GP25', 'Go Pay 25.000', 'E-Money', 'GO PAY', 25000, 28000);
        $this->inbound('gp-list', 'pembayaran apa yang tersedia?');
        $this->inbound('gp-group', '1');
        $this->inbound('gp-select', '1');
        $this->assertDatabaseCount('topup_orders', 0);
        $this->inbound('gp-invalid', '123456');
        $this->assertDatabaseCount('topup_orders', 0);
        $this->inbound('gp-phone', '081234567891');
        $order = TopupOrder::query()->sole();
        $this->assertSame($product->id, $order->digiflazz_product_id);
        $this->assertSame('6281234567891', $order->destination);
        $this->assertSame(28000, $order->total_amount);
        $this->inbound('gp-bare', '24681357');
        $this->inbound('gp-other', 'Beli gopay 25.000 ke nomor 081234567892');
        Queue::assertNothingPushed();
        $this->inbound('gp-confirm', 'KONFIRMASI 24681357');
        $this->inbound('gp-repeat', 'KONFIRMASI 24681357');
        Queue::assertPushed(ProcessTopupOrder::class, 1);
        $this->assertDatabaseCount('topup_orders', 1);
        $this->assertDatabaseCount('ai_usage_logs', 0);
        Http::assertNotSent(fn (Request $r) => ! str_starts_with($r->url(), 'http://whatsapp.test/'));
    }

    public function test_direct_gopay_aliases_and_denominations_match_exact_face_value(): void
    {
        Queue::fake();
        Http::fake(['http://whatsapp.test/v1/messages' => Http::response(['accepted' => true], 202)]);
        $product = $this->product('GP25', 'Go Pay 25.000', 'E-Money', 'GO PAY', 25000, 28000);
        $this->product('GP125', 'GoPay 125000', 'E-Money', 'GOPAY', 125000, 128000);
        $this->product('GP250', 'Go Pay 250.000', 'E-Money', 'GO PAY', 250000, 253000);
        foreach (['gopay 25000', 'Go Pay 25.000', 'GOPAY 25.000'] as $i => $search) {
            $this->inbound('gp-direct-'.$i, 'Beli '.$search.' ke nomor +62 812-3456-7890');
            $order = TopupOrder::query()->latest('id')->firstOrFail();
            $this->assertSame($product->id, $order->digiflazz_product_id);
            $this->assertSame('6281234567890', $order->destination);
            $this->inbound('gp-bare-'.$i, '24681357');
            $this->assertSame(TopupPaymentStatus::Pending, $order->fresh()->payment_status);
            $this->inbound('gp-cancel-'.$i, 'BATAL');
        }
        foreach (['gopay 28000', 'gopay 25.00', 'gopay 25000 driver', 'gopay 5000'] as $i => $search) {
            $this->inbound('gp-no-match-'.$i, 'Beli '.$search.' ke nomor 081234567890');
        }
        $this->inbound('gp-bad-phone', 'Beli gopay 25000 ke nomor 1234567890');
        $this->inbound('gp-unauthorized', 'Beli gopay 25000 ke nomor 081234567890', '6289999999999');
        $this->assertDatabaseCount('topup_orders', 3);
        $this->product('GP25B', 'GoPay 25000', 'E-Money', 'GOPAY', 25000, 27000);
        $this->inbound('gp-ambiguous', 'Beli gopay 25000 ke nomor 081234567890');
        $this->assertDatabaseCount('topup_orders', 3);
        Http::assertSent(fn (Request $r) => str_contains((string) $r['text'], 'beberapa produk'));
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('ai_usage_logs', 0);
        Http::assertNotSent(fn (Request $r) => ! str_starts_with($r->url(), 'http://whatsapp.test/'));
    }

    public function test_guided_wallet_eligibility_and_footer_are_scoped_to_gopay(): void
    {
        $gopay = $this->product('GP', 'Go Pay 25.000', 'E-Money', 'GO PAY', 25000, 28000);
        $ovo = $this->product('OVO', 'OVO 25.000', 'E-Money', 'OVO', 25000, 28000);
        $this->assertTrue($gopay->supportsGuidedPhonePurchase());
        $this->assertFalse($ovo->supportsGuidedPhonePurchase());
        $gopay->transaction_type = DigiflazzTransactionType::Postpaid;
        $this->assertFalse($gopay->supportsGuidedPhonePurchase());
        $catalog = app(\App\Support\DigiflazzCatalog::class);
        $context = null;
        $catalog->answer('pembayaran apa yang tersedia?', $context);
        $answer = $catalog->answer('1', $context);
        $this->assertStringContainsString('GoPay prabayar', $answer);
        $this->assertStringContainsString('KONFIRMASI', $answer);
        $context = null;
        $catalog->answer('pembayaran apa yang tersedia?', $context);
        $answer = $catalog->answer('2', $context);
        $this->assertStringContainsString('Pilihan angka untuk pembelian belum didukung', $answer);
    }

    /** @param array<string, mixed>|null $response */
    private function fakeBillInquiry(?array $response = null): void
    {
        Queue::fake();
        Http::fake([
            'http://whatsapp.test/v1/messages' => Http::response(['accepted' => true], 202),
            'https://api.digiflazz.com/v1/transaction' => function (Request $request) use ($response) {
                $this->assertSame('inq-pasca', $request['commands']);

                return Http::response(['data' => $response ?? [
                    'status' => 'sukses', 'rc' => '00', 'price' => 50000, 'selling_price' => 55000,
                    'customer_name' => 'Pelanggan Uji',
                ]]);
            },
        ]);
    }

    private function inbound(string $messageId, string $text, string $phone = '6281234567890'): void
    {
        $message = new ProcessWhatsAppInboundMessage(
            $messageId,
            $phone.'@s.whatsapp.net',
            $phone.'@s.whatsapp.net',
            $text,
        );

        app()->call([$message, 'handle']);
    }

    private function product(
        string $sku,
        string $name,
        string $category,
        string $brand,
        int $cost,
        int $selling,
        DigiflazzTransactionType $type = DigiflazzTransactionType::Prepaid,
    ): DigiflazzProduct {
        return DigiflazzProduct::query()->create([
            'transaction_type' => $type,
            'buyer_sku_code' => $sku,
            'product_name' => $name,
            'category' => $category,
            'brand' => $brand,
            'cost_price' => $cost,
            'selling_price' => $selling,
            'buyer_product_status' => true,
            'seller_product_status' => true,
            'unlimited_stock' => true,
            'stock' => 0,
            'multi' => false,
        ]);
    }
}
