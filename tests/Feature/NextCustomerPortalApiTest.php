<?php

namespace Tests\Feature;

use App\Enums\ServiceOrderStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NextCustomerPortalApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_customer_can_login_and_read_own_portal(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Customer,
            'email_verified_at' => now(),
            'password' => 'password',
        ]);
        $customer = Customer::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '081234567890',
            'type' => 'umum',
        ]);
        ServiceOrder::create([
            'order_number' => 'ORD-20260729-0001',
            'public_token' => fake()->uuid(),
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'type' => 'print',
            'status' => ServiceOrderStatus::AwaitingReview,
        ]);

        $login = $this->postJson('/api/v1/auth/customer-login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'next-test',
        ])->assertOk();

        $token = $login->json('data.token');
        $this->withToken($token)->getJson('/api/v1/customer/portal')
            ->assertOk()
            ->assertJsonPath('data.summary.total', 1)
            ->assertJsonPath('data.summary.active', 1)
            ->assertJsonPath('data.orders.0.order_number', 'ORD-20260729-0001')
            ->assertJsonMissingPath('data.orders.0.customer_phone')
            ->assertJsonMissingPath('data.user.password');

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_unverified_customer_cannot_login_to_next_portal(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Customer,
            'email_verified_at' => null,
            'password' => 'password',
        ]);
        Customer::create(['user_id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'phone' => '081234567890', 'type' => 'umum']);

        $this->postJson('/api/v1/auth/customer-login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'next-test',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }
}