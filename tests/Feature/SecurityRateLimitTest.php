<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_login_is_limited_by_identity_and_ip(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post('/login', [
                'email' => 'target@example.test',
                'password' => 'password-salah',
            ])->assertSessionHasErrors('email');
        }

        $this->post('/login', [
            'email' => 'target@example.test',
            'password' => 'password-salah',
        ])->assertStatus(429);
    }

    public function test_password_reset_requests_are_limited_per_identity_for_ten_minutes(): void
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->post(route('password.email'), [
                'email' => 'reset-target@example.test',
            ])->assertSessionHas('status');
        }

        $this->post(route('password.email'), [
            'email' => 'reset-target@example.test',
        ])->assertStatus(429);
    }
}
