<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientProjectReminderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_list_and_update_project_reminder_without_leaking_hosting_password(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->withToken($owner->createToken('project-reminder-test', ['staff:dashboard', 'project-reminders:reveal'])->plainTextToken);

        $create = $this->postJson('/api/v1/staff/project-reminders', [
            'project_name' => 'Web Koperasi Lapas Sampit',
            'customer_name' => 'Koperasi Lapas Sampit',
            'whatsapp' => '081234567890',
            'email' => 'koperasi@example.test',
            'address' => 'Jalan Rahadi Usman 42',
            'project_type' => 'web',
            'hosting_provider' => 'Hostinger',
            'active_from' => '2026-08-10',
            'active_until' => '2028-08-10',
            'hosting_login_email' => 'hosting@example.test',
            'hosting_login_password' => 'secret-hosting-password',
            'payment_status' => 'lunas',
            'amount' => 5000000,
            'transaction_date' => '2026-08-10',
            'contact_method' => 'whatsapp',
            'notes' => 'Catatan pelanggan privat',
        ])->assertCreated()
            ->assertJsonPath('data.project_name', 'Web Koperasi Lapas Sampit')
            ->assertJsonPath('data.has_hosting_password', true)
            ->assertJsonMissingPath('data.hosting_login_password');

        $id = $create->json('data.id');
        $stored = DB::table('client_project_reminders')->where('id', $id)->first();
        $this->assertNotSame('Web Koperasi Lapas Sampit', $stored->project_name);
        $this->assertNotSame('Koperasi Lapas Sampit', $stored->customer_name);
        $this->assertNotSame('Catatan pelanggan privat', $stored->notes);
        $this->assertNotSame('081234567890', $stored->whatsapp);
        $this->assertNotSame('koperasi@example.test', $stored->email);
        $this->assertNotSame('Jalan Rahadi Usman 42', $stored->address);
        $this->assertNotSame('hosting@example.test', $stored->hosting_login_email);
        $this->assertNotSame('secret-hosting-password', $stored->hosting_login_password);
        $audit = DB::table('activity_logs')->where('subject_id', $id)->where('action', 'client_project_reminder.created')->first();
        $auditJson = json_encode([$audit->before, $audit->after, $audit->metadata]);
        $this->assertStringNotContainsString('081234567890', $auditJson);
        $this->assertStringNotContainsString('koperasi@example.test', $auditJson);
        $this->assertStringNotContainsString('Jalan Rahadi Usman 42', $auditJson);
        $this->assertStringNotContainsString('hosting@example.test', $auditJson);
        $this->assertStringNotContainsString('secret-hosting-password', $auditJson);
        $this->assertStringNotContainsString('Web Koperasi Lapas Sampit', $auditJson);
        $this->assertStringNotContainsString('Koperasi Lapas Sampit', $auditJson);
        $this->assertStringNotContainsString('Catatan pelanggan privat', $auditJson);

        $this->getJson('/api/v1/staff/project-reminders')
            ->assertOk()
            ->assertJsonPath('data.items.0.customer_name', 'Koperasi Lapas Sampit')
            ->assertJsonPath('data.items.0.hosting_login_email_masked', 'h*****g@example.test')
            ->assertJsonMissingPath('data.items.0.hosting_login_password');

        $this->putJson("/api/v1/staff/project-reminders/{$id}", [
            'payment_status' => 'dp',
            'amount' => 2500000,
        ])->assertOk()->assertJsonPath('data.payment_status', 'dp');

        $this->postJson("/api/v1/staff/project-reminders/{$id}/reveal-credentials")
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.hosting_login_email', 'hosting@example.test')
            ->assertJsonPath('data.hosting_login_password', 'secret-hosting-password');

        $this->travel(16)->minutes();
        $this->postJson("/api/v1/staff/project-reminders/{$id}/reveal-credentials")
            ->assertForbidden()
            ->assertJsonPath('message', 'Verifikasi dua faktor perlu diperbarui. Silakan masuk kembali.');
    }

    public function test_project_reminders_require_manager_role_and_dashboard_ability(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        Sanctum::actingAs($cashier, ['staff:dashboard']);
        $this->getJson('/api/v1/staff/project-reminders')->assertForbidden();

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        Sanctum::actingAs($owner, ['staff:customers']);
        $this->getJson('/api/v1/staff/project-reminders')->assertForbidden();
    }

    public function test_reveal_requires_dedicated_recent_two_factor_token(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        Sanctum::actingAs($owner, ['staff:dashboard']);
        $id = $this->postJson('/api/v1/staff/project-reminders', [
            'project_name' => 'Proyek Rahasia',
            'customer_name' => 'Pelanggan',
            'project_type' => 'web',
            'payment_status' => 'belum_diisi',
            'contact_method' => 'belum_diisi',
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/staff/project-reminders/{$id}/reveal-credentials")->assertForbidden();
    }

    public function test_owner_can_record_known_project_facts_without_fabricating_unknown_details(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        Sanctum::actingAs($owner, ['staff:dashboard']);

        $this->postJson('/api/v1/staff/project-reminders', [
            'project_name' => 'Web Laravel 12 Koperasi Lapas Sampit',
            'customer_name' => 'Koperasi Lapas Sampit',
            'project_type' => 'web',
            'payment_status' => 'belum_diisi',
            'contact_method' => 'belum_diisi',
            'notes' => 'Masa layanan disepakati 2 tahun; tanggal mulai dan berakhir perlu dilengkapi.',
        ])->assertCreated()
            ->assertJsonPath('data.active_from', null)
            ->assertJsonPath('data.active_until', null)
            ->assertJsonPath('data.payment_status', 'belum_diisi')
            ->assertJsonPath('data.contact_method', 'belum_diisi');
    }

    public function test_partial_update_cannot_move_end_date_before_stored_start_date(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        Sanctum::actingAs($owner, ['staff:dashboard']);
        $id = $this->postJson('/api/v1/staff/project-reminders', [
            'project_name' => 'Proyek Tanggal',
            'customer_name' => 'Pelanggan',
            'project_type' => 'web',
            'active_from' => '2026-08-10',
            'active_until' => '2028-08-10',
            'payment_status' => 'belum_diisi',
            'contact_method' => 'belum_diisi',
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/v1/staff/project-reminders/{$id}", [
            'active_until' => '2025-08-10',
        ])->assertUnprocessable()->assertJsonValidationErrors('active_until');
    }

    public function test_project_reminder_validation_rejects_invalid_dates_and_enums(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        Sanctum::actingAs($owner, ['staff:dashboard']);

        $this->postJson('/api/v1/staff/project-reminders', [
            'project_name' => 'Proyek Salah',
            'customer_name' => 'Pelanggan',
            'project_type' => 'desktop',
            'active_from' => '2028-01-01',
            'active_until' => '2027-01-01',
            'payment_status' => 'utang',
            'transaction_date' => '2026-08-10',
            'contact_method' => 'telegram',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['project_type', 'active_until', 'payment_status', 'contact_method']);
    }
}
