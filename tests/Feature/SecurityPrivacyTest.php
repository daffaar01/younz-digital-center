<?php

namespace Tests\Feature;

use App\Enums\ServiceOrderStatus;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Support\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use LogicException;
use Tests\TestCase;

class SecurityPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_order_ignores_internal_business_fields_and_derives_service_type(): void
    {
        $customer = Customer::create(['name' => 'Korban', 'phone' => '089999']);
        $service = Service::create([
            'name' => 'Print Warna', 'slug' => 'print-warna', 'type' => 'print', 'unit' => 'lembar',
            'base_price' => 2000, 'is_active' => true,
        ]);

        $this->post('/pesan', [
            'customer_id' => $customer->id,
            'customer_name' => 'Pelanggan Publik',
            'customer_phone' => '0812-345-678',
            'service_id' => $service->id,
            'type' => 'website',
            'estimated_price' => 1,
            'deadline_at' => now()->addDay()->toDateTimeString(),
            'source' => 'catalog',
        ])->assertRedirect();

        $order = ServiceOrder::firstOrFail();
        $this->assertNull($order->customer_id);
        $this->assertNull($order->estimated_price);
        $this->assertNull($order->deadline_at);
        $this->assertSame('print', $order->type);
        $this->assertSame('0812345678', $order->customer_phone);
        $this->assertSame('catalog', $order->specifications['acquisition_source']);
    }

    public function test_customer_portal_requires_order_number_and_phone_and_never_lists_sibling_orders(): void
    {
        $first = $this->order('ORD-PRIVACY-0001', '0812345');
        $second = $this->order('ORD-PRIVACY-0002', '0812345');

        $this->get('/pesanan-saya')->assertOk();
        $this->get('/pesanan-saya?order_number='.$first->order_number.'&phone=0812345')
            ->assertOk()
            ->assertDontSee($first->order_number);
        $this->post('/pesanan-saya', ['phone' => '0812345'])->assertSessionHasErrors('order_number');
        $this->post('/pesanan-saya', ['order_number' => $first->order_number, 'phone' => '0812345'])
            ->assertOk()
            ->assertSee($first->order_number)
            ->assertDontSee($second->order_number);
    }

    public function test_security_headers_are_present(): void
    {
        config()->set('services.midtrans.production', false);

        $response = $this->get('/')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy');

        $this->assertStringContainsString(
            'frame-src \'self\' https://www.google.com',
            (string) $response->headers->get('Content-Security-Policy'),
        );
        $this->assertStringContainsString(
            'https://static.cloudflareinsights.com',
            (string) $response->headers->get('Content-Security-Policy'),
        );
        $this->assertStringContainsString(
            "script-src 'self' 'wasm-unsafe-eval'",
            (string) $response->headers->get('Content-Security-Policy'),
        );
        $this->assertStringContainsString(
            'https://cloudflareinsights.com',
            (string) $response->headers->get('Content-Security-Policy'),
        );
        $this->assertStringContainsString(
            "form-action 'self' https://app.sandbox.midtrans.com",
            (string) $response->headers->get('Content-Security-Policy'),
        );
    }

    public function test_security_headers_allow_only_the_configured_midtrans_payment_host(): void
    {
        config()->set('services.midtrans.production', true);

        $policy = (string) $this->get('/')->assertOk()->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("form-action 'self' https://app.midtrans.com", $policy);
        $this->assertStringNotContainsString('https://app.sandbox.midtrans.com', $policy);
    }

    public function test_https_forwarded_by_the_local_tunnel_receives_hsts(): void
    {
        $response = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeader('X-Forwarded-Proto', 'https')
            ->get('/')
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        $this->assertStringContainsString(
            'upgrade-insecure-requests',
            (string) $response->headers->get('Content-Security-Policy'),
        );
    }

    public function test_public_tracking_link_does_not_reveal_customer_contact_details(): void
    {
        $order = $this->order('ORD-PRIVATE-0001', '081299988877');
        $order->update(['customer_name' => 'Nama Rahasia Pelanggan']);

        $this->get(route('public.track.show', $order->public_token))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee($order->order_number)
            ->assertDontSee('Nama Rahasia Pelanggan')
            ->assertDontSee('081299988877');
    }

    public function test_public_pages_show_current_store_information_and_working_whatsapp_link(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Satu tempat.')
            ->assertSee('Semua beres.')
            ->assertSee('Younz Digital Center')
            ->assertSee('Konsultasi dulu.')
            ->assertSee('Senin - Sabtu 09:00 - 17:00')
            ->assertSee('JL. Kapten Mulyono No. 60C')
            ->assertSee('https://wa.me/628219207240', false)
            ->assertSee('08219207240');
    }

    public function test_activity_log_is_immutable(): void
    {
        $log = ActivityLog::create(['action' => 'test.created']);

        $this->expectException(LogicException::class);
        $log->update(['action' => 'test.changed']);
    }

    public function test_audit_logger_redacts_credentials_and_hmacs_identifiers(): void
    {
        $audit = app(AuditLogger::class);
        $log = $audit->log('security.redaction_test', before: [
            'password' => 'rahasia',
            'nested' => ['api_key' => 'kunci'],
        ], metadata: [
            'email_hash' => $audit->identifierFingerprint('User@Example.Test'),
            'authorization' => 'Bearer rahasia',
        ]);

        $this->assertSame('[REDACTED]', $log->before['password']);
        $this->assertSame('[REDACTED]', $log->before['nested']['api_key']);
        $this->assertSame('[REDACTED]', $log->metadata['authorization']);
        $this->assertNotSame(hash('sha256', 'user@example.test'), $log->metadata['email_hash']);
        $this->assertSame(64, strlen($log->metadata['email_hash']));
    }

    public function test_upload_fails_closed_when_antivirus_is_required_but_unavailable(): void
    {
        config([
            'services.file_security.antivirus_required' => true,
            'services.file_security.antivirus_binary' => null,
        ]);

        $this->post(route('public.order.store'), [
            'customer_name' => 'Pelanggan Aman',
            'customer_phone' => '081234567890',
            'type' => 'print',
            'file' => UploadedFile::fake()->create('dokumen.pdf', 20, 'application/pdf'),
        ])->assertSessionHasErrors('file');

        $this->assertDatabaseCount('service_orders', 0);
        $this->assertDatabaseCount('service_files', 0);
    }

    public function test_public_ai_requires_explicit_processing_consent(): void
    {
        $this->postJson(route('public.ai.chat'), ['message' => 'Berapa harga print?'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ai_consent');

        $this->postJson('/api/v1/ai/chat', ['message' => 'Berapa harga print?'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ai_consent');
    }

    private function order(string $number, string $phone): ServiceOrder
    {
        return ServiceOrder::create([
            'order_number' => $number,
            'public_token' => fake()->uuid(),
            'customer_name' => 'Pelanggan',
            'customer_phone' => $phone,
            'type' => 'print',
            'status' => ServiceOrderStatus::AwaitingReview,
        ]);
    }
}
