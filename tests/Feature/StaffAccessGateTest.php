<?php

namespace Tests\Feature;

use App\Support\StaffAccessGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffAccessGateTest extends TestCase
{
    use RefreshDatabase;

    private const ACCESS_CODE = 'YDC-TEST-ACCESS';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.staff_url', 'https://admin.example.test');
        config()->set('app.staff_host', 'admin.example.test');
        config()->set('app.trusted_hosts', ['example.test', 'admin.example.test']);
        config()->set('auth.staff_access.code_hash', Hash::make(self::ACCESS_CODE));
        config()->set('auth.staff_access.ttl_minutes', 60);
    }

    public function test_public_host_remains_accessible_without_the_staff_gate(): void
    {
        $this->get('https://example.test')
            ->assertOk()
            ->assertDontSee('Gerbang akses admin Younz Digital Center');
    }

    public function test_admin_root_and_direct_login_require_the_access_gate(): void
    {
        $this->get('https://admin.example.test')
            ->assertRedirect('https://admin.example.test/akses-pegawai');

        $this->get('https://admin.example.test/login')
            ->assertRedirect('https://admin.example.test/akses-pegawai');
    }

    public function test_staff_access_form_is_only_served_on_the_admin_host(): void
    {
        $this->get('https://admin.example.test/akses-pegawai')
            ->assertOk()
            ->assertSee('Gerbang akses admin Younz Digital Center')
            ->assertSee('Masukkan kode akses')
            ->assertSee('name="access_code"', false)
            ->assertSee('noindex,nofollow,noarchive');

        $this->get('https://example.test/akses-pegawai')
            ->assertRedirect('https://admin.example.test/akses-pegawai');
    }

    public function test_invalid_access_code_is_rejected_audited_and_not_flashed(): void
    {
        $response = $this->from('https://admin.example.test/akses-pegawai')
            ->post('https://admin.example.test/akses-pegawai', ['access_code' => 'wrong-code']);

        $response->assertRedirect('https://admin.example.test/akses-pegawai')
            ->assertSessionHasErrors('access_code')
            ->assertSessionMissing(StaffAccessGate::SESSION_KEY)
            ->assertSessionMissing('_old_input.access_code');

        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.staff_access_failed']);
    }

    public function test_valid_access_code_opens_login_and_admin_root_targets_login(): void
    {
        $this->post('https://admin.example.test/akses-pegawai', ['access_code' => self::ACCESS_CODE])
            ->assertRedirect('https://admin.example.test/login')
            ->assertSessionHas(StaffAccessGate::SESSION_KEY);

        $this->get('https://admin.example.test/login')->assertOk();
        $this->get('https://admin.example.test')->assertRedirect('https://admin.example.test/login');
        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.staff_access_verified']);
    }

    public function test_expired_access_session_returns_to_the_gate(): void
    {
        $this->withSession([
            StaffAccessGate::SESSION_KEY => now()->subMinutes(61)->timestamp,
        ])->get('https://admin.example.test/login')
            ->assertRedirect('https://admin.example.test/akses-pegawai')
            ->assertSessionMissing(StaffAccessGate::SESSION_KEY);
    }

    public function test_unconfigured_gate_fails_closed_on_the_gate_form(): void
    {
        config()->set('auth.staff_access.code_hash', '');

        $this->get('https://admin.example.test/akses-pegawai')->assertServiceUnavailable();
    }
}
