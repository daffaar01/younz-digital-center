<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NextCustomerPasswordResetApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_reset_password_through_next_api_without_account_enumeration(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'next-reset@example.test',
            'role' => UserRole::Customer,
        ]);
        Customer::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '081234567890',
            'type' => 'umum',
        ]);
        $user->createToken('old-next-session', ['profile:read']);
        $token = null;

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('message', 'Jika email terdaftar sebagai pelanggan, tautan reset password telah dikirim.');
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertOk()->assertJsonPath('message', 'Password berhasil diubah. Silakan masuk.');

        $this->assertTrue(Hash::check('NewPassword123', $user->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'tidak-ada@example.test'])
            ->assertOk()
            ->assertJsonPath('message', 'Jika email terdaftar sebagai pelanggan, tautan reset password telah dikirim.');
    }
}