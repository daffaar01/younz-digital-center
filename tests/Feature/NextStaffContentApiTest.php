<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AiUsageLog;
use App\Models\AnalyticsEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NextStaffContentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_manage_content_and_view_ai_and_funnel_metrics(): void
    {
        $owner = User::factory()->create([
            'role' => UserRole::Owner,
            'is_active' => true,
        ]);
        $token = $owner->createToken('content-test', ['staff:content'])->plainTextToken;

        AnalyticsEvent::create([
            'event_name' => 'page_view',
            'visitor_hash' => str_repeat('a', 64),
            'path' => '/',
        ]);
        AnalyticsEvent::create([
            'event_name' => 'order_cta_clicked',
            'visitor_hash' => str_repeat('a', 64),
            'path' => '/',
            'source' => 'landing_hero',
        ]);
        AnalyticsEvent::create([
            'event_name' => 'order_submitted',
            'visitor_hash' => str_repeat('a', 64),
            'path' => '/pesan',
            'source' => 'landing_hero',
        ]);
        AiUsageLog::create([
            'feature' => 'faq_chat',
            'provider' => 'local',
            'model' => 'deterministic',
            'status' => 'success',
            'feedback' => 'helpful',
            'feedback_at' => now(),
            'metadata' => ['grounding' => 'verified', 'sources' => [['id' => 1]]],
        ]);

        $headers = ['Authorization' => 'Bearer '.$token];

        $service = $this->withHeaders($headers)->postJson('/api/v1/staff/content/services', [
            'name' => 'Print A4 Hitam Putih',
            'type' => 'print',
            'base_price' => 500,
            'unit' => 'lembar',
            'description' => 'Print dokumen A4 hitam putih.',
            'is_active' => true,
        ])->assertCreated()->json('data');

        $this->withHeaders($headers)->putJson('/api/v1/staff/content/services/'.$service['id'], [
            'name' => 'Print A4 Hitam Putih',
            'type' => 'print',
            'base_price' => 600,
            'unit' => 'lembar',
            'description' => 'Harga terbaru.',
            'is_active' => true,
        ])->assertOk()->assertJsonPath('data.base_price', 600);

        $this->withHeaders($headers)->postJson('/api/v1/staff/content/knowledge', [
            'title' => 'Harga Print A4',
            'type' => 'service',
            'content' => 'Harga print A4 hitam putih mulai Rp600 per lembar.',
            'status' => 'active',
        ])->assertCreated();

        $this->withHeaders($headers)->postJson('/api/v1/staff/content/testimonials', [
            'customer_name' => 'Pelanggan Uji',
            'customer_role' => 'UMKM',
            'quote' => 'Pengerjaan jelas dan tepat waktu.',
            'rating' => 5,
            'source_label' => 'WhatsApp',
            'is_published' => true,
            'consent_at' => now()->subMinute()->toIso8601String(),
            'display_order' => 1,
        ])->assertCreated();

        $this->withHeaders($headers)->getJson('/api/v1/staff/content?days=30')
            ->assertOk()
            ->assertJsonPath('data.services.0.name', 'Print A4 Hitam Putih')
            ->assertJsonPath('data.knowledge_documents.0.title', 'Harga Print A4')
            ->assertJsonPath('data.testimonials.0.customer_name', 'Pelanggan Uji')
            ->assertJsonPath('data.analytics.landing_views', 1)
            ->assertJsonPath('data.analytics.order_cta_clicks', 1)
            ->assertJsonPath('data.analytics.orders_submitted', 1)
            ->assertJsonPath('data.analytics.landing_to_order_rate', 100)
            ->assertJsonPath('data.ai.helpful', 1)
            ->assertJsonPath('data.ai.helpful_rate', 100);
    }

    public function test_cashier_cannot_access_content_management_even_with_a_forged_ability(): void
    {
        $cashier = User::factory()->create([
            'role' => UserRole::Cashier,
            'is_active' => true,
        ]);
        $token = $cashier->createToken('forged-content-test', ['staff:content'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/staff/content')->assertForbidden();
    }

    public function test_existing_owner_dashboard_token_remains_compatible(): void
    {
        $owner = User::factory()->create([
            'role' => UserRole::Owner,
            'is_active' => true,
        ]);
        $token = $owner->createToken('existing-dashboard-token', ['staff:dashboard'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/staff/content')->assertOk();
    }
}
