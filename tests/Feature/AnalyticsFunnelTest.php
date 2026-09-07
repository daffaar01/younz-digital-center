<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalyticsFunnelTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_analytics_requires_consent_and_hashes_the_visitor_identifier(): void
    {
        $visitorId = (string) Str::uuid();

        $this->postJson('/api/v1/analytics/events', [
            'event' => 'page_view',
            'visitor_id' => $visitorId,
            'path' => '/',
        ])->assertUnprocessable()->assertJsonValidationErrors('analytics_consent');

        $this->postJson('/api/v1/analytics/events', [
            'event' => 'order_cta_clicked',
            'visitor_id' => $visitorId,
            'path' => '/',
            'source' => 'landing_hero',
            'analytics_consent' => '1',
        ])->assertAccepted();

        $event = AnalyticsEvent::query()->sole();
        $this->assertSame('order_cta_clicked', $event->event_name);
        $this->assertSame('/', $event->path);
        $this->assertSame('landing_hero', $event->source);
        $this->assertNotSame($visitorId, $event->visitor_hash);
        $this->assertSame(64, strlen($event->visitor_hash));
    }

    public function test_analytics_rejects_unknown_events(): void
    {
        $this->postJson('/api/v1/analytics/events', [
            'event' => 'steal_customer_data',
            'visitor_id' => (string) Str::uuid(),
            'path' => '/',
            'analytics_consent' => '1',
        ])->assertUnprocessable()->assertJsonValidationErrors('event');
    }

    public function test_analytics_rejects_dynamic_or_personal_paths(): void
    {
        $this->postJson('/api/v1/analytics/events', [
            'event' => 'page_view',
            'visitor_id' => (string) Str::uuid(),
            'path' => '/akun/pesanan/YDC-SECRET-123',
            'analytics_consent' => '1',
        ])->assertUnprocessable()->assertJsonValidationErrors('path');
    }
}
