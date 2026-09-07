<?php

namespace Tests\Feature;

use App\Support\WhatsAppOperatorLease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WhatsAppOperatorLeaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_cache_loss_cannot_acquire_an_active_operator_lease(): void
    {
        WhatsAppOperatorLease::run('operator', function (): void {
            Cache::flush();
            try {
                WhatsAppOperatorLease::run('operator', fn () => $this->fail('Overlapping owner acquired lease.'));
                $this->fail('Active lease must reject overlap.');
            } catch (\RuntimeException $error) {
                $this->assertStringContainsString('masih diproses', $error->getMessage());
            }
        });
        $this->assertNull(DB::table('whatsapp_operator_leases')->value('owner'));
    }

    public function test_replaced_owner_cannot_write_or_release_the_new_lease(): void
    {
        WhatsAppOperatorLease::run('operator', function (): void {
            DB::table('whatsapp_operator_leases')->update(['owner' => 'new-owner']);
            try {
                WhatsAppOperatorLease::transaction(fn () => $this->fail('Stale owner wrote state.'));
                $this->fail('Stale owner must be rejected.');
            } catch (\RuntimeException $error) {
                $this->assertStringContainsString('sudah berubah', $error->getMessage());
            }
        });
        $this->assertSame('new-owner', DB::table('whatsapp_operator_leases')->value('owner'));
    }
}
