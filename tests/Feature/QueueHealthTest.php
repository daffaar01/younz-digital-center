<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueueHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_is_not_reported_as_a_healthy_background_queue(): void
    {
        config(['queue.default' => 'sync']);
        $this->artisan('queue:health')->assertFailed();
    }

    public function test_short_visibility_is_rejected(): void
    {
        config(['queue.default' => 'database', 'queue.connections.database.retry_after' => 90]);
        $this->artisan('queue:health')->assertFailed();
    }

    public function test_database_diagnostics_expose_counts_without_job_payloads(): void
    {
        config(['queue.default' => 'database', 'queue.connections.database.retry_after' => 300]);
        $this->artisan('queue:health')
            ->expectsOutput('Pending: 0')
            ->expectsOutput('Failed: 0')
            ->expectsOutput('Unpublished fulfillment intents: 0')
            ->expectsOutput('Stale paid fulfillment: 0')
            ->assertSuccessful();
    }
}
