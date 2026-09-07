<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NextStaffDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_staff_with_access_code_and_totp_can_open_next_dashboard(): void
    {
        config(['auth.staff_access.code_hash' => Hash::make('YDC-ACCESS')]);
        $totp = app(Totp::class);
        $secret = $totp->generateSecret();
        $user = User::factory()->create([
            'name' => 'Owner Next',
            'email' => 'owner-next@example.test',
            'password' => 'password',
            'role' => UserRole::Owner,
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);
        Product::create([
            'category_id' => $this->categoryId(),
            'name' => 'Kertas Uji',
            'slug' => 'kertas-uji',
            'sku' => 'PPR-TEST',
            'selling_price' => 10000,
            'stock' => 1,
            'minimum_stock' => 5,
            'is_active' => true,
        ]);
        $expenseCategory = ExpenseCategory::create(['name' => 'Operasional Uji']);
        Expense::create([
            'expense_number' => 'EXP-NEXT-001',
            'expense_category_id' => $expenseCategory->id,
            'user_id' => $user->id,
            'amount' => 25000,
            'description' => 'Pengeluaran uji',
            'expense_date' => today(),
        ]);

        $login = $this->postJson('/api/v1/staff/login', [
            'email' => $user->email,
            'password' => 'password',
            'access_code' => 'YDC-ACCESS',
            'two_factor_code' => $totp->currentCode($secret),
            'device_name' => 'next-admin-test',
        ])->assertOk()
            ->assertJsonPath('data.user.role.code', 'owner')
            ->assertJsonMissingPath('data.user.two_factor_secret');

        $token = $login->json('data.token');
        $this->withToken($token)->getJson('/api/v1/staff/dashboard')
            ->assertOk()
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonPath('data.permissions.manage', true)
            ->assertJsonPath('data.summary.today_expenses', 25000)
            ->assertJsonPath('data.low_stock_products.0.sku', 'PPR-TEST');
    }

    public function test_staff_login_rejects_customer_bad_access_code_and_bad_totp(): void
    {
        config(['auth.staff_access.code_hash' => Hash::make('YDC-ACCESS')]);
        $totp = app(Totp::class);
        $secret = $totp->generateSecret();
        $staff = User::factory()->create([
            'email' => 'staff-next@example.test',
            'password' => 'password',
            'role' => UserRole::Admin,
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        $this->postJson('/api/v1/staff/login', [
            'email' => $staff->email, 'password' => 'password', 'access_code' => 'SALAH',
            'two_factor_code' => $totp->currentCode($secret), 'device_name' => 'next-admin-test',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/v1/staff/login', [
            'email' => $staff->email, 'password' => 'password', 'access_code' => 'YDC-ACCESS',
            'two_factor_code' => '000000', 'device_name' => 'next-admin-test',
        ])->assertUnprocessable()->assertJsonValidationErrors('two_factor_code');

        $customer = User::factory()->create(['email' => 'customer-next@example.test', 'password' => 'password', 'role' => UserRole::Customer]);
        $this->postJson('/api/v1/staff/login', [
            'email' => $customer->email, 'password' => 'password', 'access_code' => 'YDC-ACCESS',
            'two_factor_code' => '000000', 'device_name' => 'next-admin-test',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_staff_can_set_up_two_factor_on_first_next_login(): void
    {
        config(['auth.staff_access.code_hash' => Hash::make('YDC-ACCESS')]);
        $user = User::factory()->create([
            'email' => 'new-staff@example.test',
            'password' => 'password',
            'role' => UserRole::Admin,
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ]);

        $login = $this->postJson('/api/v1/staff/login', [
            'email' => $user->email,
            'password' => 'password',
            'access_code' => 'YDC-ACCESS',
            'two_factor_code' => null,
            'device_name' => 'next-admin-setup-test',
        ])->assertOk()
            ->assertJsonPath('data.requires_two_factor_setup', true)
            ->assertJsonStructure(['data' => ['setup_token', 'secret', 'uri']])
            ->assertJsonMissingPath('data.token');

        $response = $this->postJson('/api/v1/staff/two-factor/setup', [
            'setup_token' => $login->json('data.setup_token'),
            'code' => app(Totp::class)->currentCode($login->json('data.secret')),
        ])->assertOk()->assertJsonStructure(['data' => ['token']]);

        $this->assertTrue($user->fresh()->hasConfirmedTwoFactor());
        $this->withToken($response->json('data.token'))
            ->getJson('/api/v1/staff/dashboard')
            ->assertOk();
    }

    public function test_legacy_api_login_remains_compatible_for_customers(): void
    {
        $customer = User::factory()->create([
            'email' => 'legacy-api-customer@example.test',
            'password' => 'password',
            'role' => UserRole::Customer,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $customer->email,
            'password' => 'password',
            'device_name' => 'legacy-api-test',
        ])->assertOk()->assertJsonPath('data.user.email', $customer->email);
    }

    private function categoryId(): int
    {
        return \App\Models\Category::create(['name' => 'Uji', 'slug' => 'uji', 'is_active' => true])->id;
    }
}
