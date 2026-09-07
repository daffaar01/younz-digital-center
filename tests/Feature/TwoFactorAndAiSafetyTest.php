<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\KnowledgeDocument;
use App\Models\Service;
use App\Models\User;
use App\Support\AiBudgetGuard;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TwoFactorAndAiSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_login_page_uses_the_customer_portal_visual_system_without_changing_the_auth_flow(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk()
            ->assertSee('staff-auth-shell', false)
            ->assertSee('Akses akun pegawai Younz Digital Center')
            ->assertSee('Logo Younz Digital Center')
            ->assertSee('Portal pegawai')
            ->assertSee('action="'.route('login').'"', false)
            ->assertSee('data-password-field', false)
            ->assertSee('data-password-toggle', false)
            ->assertSee('aria-controls="staff-password"', false)
            ->assertSee('Dilindungi 2FA')
            ->assertSee(route('customer.login'), false);

        $this->assertSame(1, substr_count($response->getContent(), 'data-password-toggle aria-controls'));
    }

    public function test_owner_is_sent_to_two_factor_setup_after_password_login(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);

        $this->post('/login', ['email' => $owner->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.setup.show'));
    }

    public function test_every_staff_role_is_sent_to_two_factor_setup_after_password_login(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.setup.show'));
    }

    public function test_configured_owner_must_complete_totp_challenge(): void
    {
        $totp = app(Totp::class);
        $secret = $totp->generateSecret();
        $owner = User::factory()->create([
            'role' => UserRole::Owner,
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        $this->post('/login', ['email' => $owner->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.challenge'));
        $this->post(route('two-factor.challenge.verify'), ['code' => $totp->currentCode($secret)])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($owner);
        $this->assertSame($owner->id, session('two_factor.confirmed_user_id'));
    }

    public function test_confirmed_owner_without_a_totp_session_proof_is_logged_out(): void
    {
        $owner = User::factory()->create([
            'role' => UserRole::Owner,
            'two_factor_secret' => app(Totp::class)->generateSecret(),
            'two_factor_confirmed_at' => now(),
        ]);

        $this->actingAs($owner)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_owner_remember_choice_is_not_carried_past_totp(): void
    {
        $totp = app(Totp::class);
        $secret = $totp->generateSecret();
        $owner = User::factory()->create([
            'role' => UserRole::Owner,
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'remember_token' => null,
        ]);

        $this->post('/login', [
            'email' => $owner->email,
            'password' => 'password',
            'remember' => '1',
        ])->assertRedirect(route('two-factor.challenge'));

        $this->post(route('two-factor.challenge.verify'), ['code' => $totp->currentCode($secret)])
            ->assertRedirect(route('dashboard'));

        $this->assertNull($owner->fresh()->remember_token);
    }

    public function test_ai_history_is_bounded_and_feedback_is_persisted(): void
    {
        config(['ai.providers.openai-compatible.key' => null]);
        KnowledgeDocument::create(['title' => 'Jam Operasional', 'type' => 'faq', 'content' => 'Buka Senin sampai Sabtu.', 'status' => 'active']);
        $history = collect(range(1, 13))->map(fn (int $index) => ['role' => $index % 2 ? 'user' : 'assistant', 'content' => "Pesan {$index}"])->all();

        $this->postAiChat(['message' => 'Jam buka?', 'history' => $history])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('history');

        $response = $this->postAiChat(['message' => 'Jam buka?'])->assertOk();
        $token = $response->json('data.interaction_token');
        $this->postJson('/api/v1/ai/feedback', ['interaction_token' => $token, 'feedback' => 'helpful'])->assertOk();
        $this->assertDatabaseHas('ai_usage_logs', ['interaction_token' => $token, 'feedback' => 'helpful']);
    }

    public function test_ai_returns_only_relevant_sources(): void
    {
        config(['ai.providers.openai-compatible.key' => null]);
        KnowledgeDocument::create(['title' => 'Jam Operasional', 'type' => 'faq', 'content' => 'Buka Senin sampai Sabtu.', 'status' => 'active']);
        Service::create(['name' => 'Website UMKM', 'slug' => 'website-umkm-test', 'type' => 'website', 'unit' => 'proyek', 'base_price' => 1000000, 'is_active' => true]);

        $this->postAiChat(['message' => 'Jam operasional buka kapan?'])
            ->assertOk()
            ->assertJsonFragment(['title' => 'Jam Operasional'])
            ->assertJsonMissing(['title' => 'Website UMKM']);
    }

    public function test_ai_session_history_can_be_deleted(): void
    {
        $this->withSession(['ai_chat_history' => [['role' => 'user', 'content' => 'test']]])
            ->deleteJson(route('public.ai.history.clear'))
            ->assertOk()
            ->assertSessionMissing('ai_chat_history');
    }

    public function test_ai_cost_budget_reserves_estimated_usage_before_provider_call(): void
    {
        config([
            'ai.limits.daily_cost_micros' => 1,
            'ai.limits.input_cost_per_million_micros' => 1_000_000,
            'ai.limits.output_cost_per_million_micros' => 4_000_000,
            'ai.limits.max_output_tokens' => 1200,
        ]);

        $this->expectException(ValidationException::class);
        app(AiBudgetGuard::class)->assertAvailable('faq_chat', 'Berapa harga print?', []);
    }
}
