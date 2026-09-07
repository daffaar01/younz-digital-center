<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FirebaseAuthProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.firebase.enabled' => true,
            'services.firebase.project_id' => 'younz-test',
            'services.firebase.web.authDomain' => 'younzdigitalcenter.my.id',
        ]);
    }

    public function test_it_transparently_proxies_firebase_auth_helpers(): void
    {
        Http::fake([
            'https://younz-test.firebaseapp.com/__/auth/iframe?*' => Http::response(
                '<script nonce="firebase-auth-helper">window.helperReady = true;</script>',
                200,
                ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'max-age=1800'],
            ),
        ]);

        $response = $this->get('/__/auth/iframe?apiKey=public-key&v=12.16.0');

        $response->assertOk()
            ->assertSee('window.helperReady', false)
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertHeaderMissing('X-Frame-Options')
            ->assertHeaderMissing('Content-Security-Policy')
            ->assertCookieMissing('XSRF-TOKEN');
        $this->assertStringContainsString(
            'max-age=1800',
            (string) $response->headers->get('Cache-Control'),
        );
        $this->assertStringContainsString(
            'no-transform',
            (string) $response->headers->get('Cache-Control'),
        );
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->url() === 'https://younz-test.firebaseapp.com/__/auth/iframe?apiKey=public-key&v=12.16.0');
    }

    public function test_it_forwards_post_callbacks_without_csrf_and_rewrites_upstream_locations(): void
    {
        Http::fake([
            'https://younz-test.firebaseapp.com/__/auth/handler?*' => Http::response('', 302, [
                'Location' => 'https://younz-test.firebaseapp.com/__/auth/handler?state=complete',
            ]),
        ]);

        $response = $this->post('/__/auth/handler?state=callback', ['code' => 'oauth-code']);

        $response->assertRedirect('https://younzdigitalcenter.my.id/__/auth/handler?state=complete');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://younz-test.firebaseapp.com/__/auth/handler?state=callback'
            && str_contains($request->body(), 'code=oauth-code'));
    }

    public function test_it_rejects_unsafe_helper_paths_without_contacting_upstream(): void
    {
        Http::fake();

        $this->get('/__/auth/%2e%2e/secret')->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_stateless_helper_exemption_does_not_disable_csrf_on_customer_routes(): void
    {
        // Exercise real CSRF enforcement, which Laravel normally bypasses in tests.
        // Only the environment label changes; the guarded in-memory database remains.
        $this->app->detectEnvironment(fn (): string => 'local');
        Http::fake([
            'https://younz-test.firebaseapp.com/__/auth/handler?*' => Http::response('', 302, [
                'Location' => 'https://younz-test.firebaseapp.com/__/auth/handler?state=complete',
            ]),
        ]);

        $this->post('/__/auth/handler?state=callback', ['code' => 'oauth-code'])
            ->assertRedirect('https://younzdigitalcenter.my.id/__/auth/handler?state=complete')
            ->assertCookieMissing('XSRF-TOKEN')
            ->assertCookieMissing((string) config('session.cookie'));

        $this->post(route('customer.login.store'), [])->assertStatus(419);
        $this->post(route('customer.firebase'), ['id_token' => 'not-a-real-token'])->assertStatus(419);
        Http::assertSentCount(1);
    }
}
