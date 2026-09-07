<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_read_profile_and_revoke_sanctum_token(): void
    {
        $totp = app(Totp::class);
        $secret = $totp->generateSecret();
        $user = User::factory()->create([
            'role' => UserRole::Admin,
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'test-device',
            'two_factor_code' => $totp->currentCode($secret),
        ])->assertOk();

        $token = $login->json('data.token');
        $this->withToken($token)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_staff_api_login_requires_two_factor_authentication(): void
    {
        $user = User::factory()->create(['role' => UserRole::Cashier]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'unsafe-device',
        ])->assertUnprocessable()->assertJsonValidationErrors('two_factor_code');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
