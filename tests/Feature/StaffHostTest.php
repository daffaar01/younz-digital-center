<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffHostTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.staff_url', 'https://admin.example.test');
        config()->set('app.staff_host', 'admin.example.test');
        config()->set('app.trusted_hosts', ['example.test', 'www.example.test', 'admin.example.test']);
    }

    public function test_staff_navigation_is_hidden_on_public_and_www_hosts(): void
    {
        $this->get('https://example.test')
            ->assertOk()
            ->assertDontSee('data-staff-nav-link', false);

        $this->get('https://www.example.test')
            ->assertOk()
            ->assertDontSee('data-staff-nav-link', false);
    }

    public function test_public_layout_renders_the_accessible_animated_mobile_navigation(): void
    {
        $this->get('https://example.test')
            ->assertOk()
            ->assertSee('data-mobile-menu-toggle', false)
            ->assertSee('data-mobile-menu-label', false)
            ->assertSee('public-menu-toggle-icon', false)
            ->assertSee('data-mobile-menu class="public-mobile-menu lg:hidden"', false)
            ->assertSee('aria-hidden="true" inert', false);
    }

    public function test_staff_navigation_remains_visible_on_staff_host(): void
    {
        $this->get('https://admin.example.test/login')
            ->assertOk()
            ->assertSee('data-staff-nav-link="desktop"', false)
            ->assertSee('data-staff-nav-link="mobile"', false);
    }

    public function test_staff_login_redirects_from_public_host_to_staff_host(): void
    {
        $this->get('https://example.test/login?from=nav')
            ->assertRedirect('https://admin.example.test/login?from=nav');
    }

    public function test_staff_login_is_available_on_staff_host(): void
    {
        $this->get('https://admin.example.test/login')->assertOk();
    }

    public function test_staff_login_submission_is_not_accepted_on_public_host(): void
    {
        $this->post('https://example.test/login', [
            'email' => 'owner@example.test',
            'password' => 'not-a-real-password',
        ])->assertNotFound();
    }

    public function test_staff_dashboard_redirects_to_login_on_staff_host_before_authentication(): void
    {
        $this->get('https://example.test/dashboard')
            ->assertRedirect('https://admin.example.test/login');
    }
}
