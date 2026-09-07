<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_customer_cannot_open_internal_dashboard(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);

        $this->actingAs($customer)->get('/dashboard')->assertForbidden();
    }

    public function test_cashier_can_open_dashboard_but_not_product_management(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);

        $this->actingAs($cashier)->get('/dashboard')
            ->assertOk()
            ->assertSee('Panel Perhatian')
            ->assertSee('Kasir / POS')
            ->assertDontSee('Produk &amp; Stok', false);
        $this->actingAs($cashier)->get('/products')->assertForbidden();
    }

    public function test_owner_dashboard_renders_management_workspace(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($owner)->get('/dashboard')
            ->assertOk()
            ->assertSee('Ringkasan hari ini')
            ->assertSee('Panel Perhatian')
            ->assertSee('Produk &amp; Stok', false)
            ->assertSee('Persetujuan')
            ->assertSee('Satu panel untuk seluruh operasional.');
    }

    public function test_only_owner_can_create_employee_and_role_is_server_validated(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $payload = [
            'name' => 'Operator Baru',
            'email' => 'operator@example.test',
            'role' => UserRole::PrintOperator->value,
            'password' => 'PasswordKuat!2026',
            'password_confirmation' => 'PasswordKuat!2026',
        ];

        $this->actingAs($cashier)->post('/employees', $payload)->assertForbidden();
        $this->actingAs($owner)->post('/employees', $payload)->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'operator@example.test', 'role' => UserRole::PrintOperator->value]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'employee.created']);
    }
}
