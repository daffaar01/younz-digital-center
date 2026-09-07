<?php

namespace Tests\Feature;

use Tests\TestCase;

class BackendOnlyBoundaryTest extends TestCase
{
    public function test_backend_only_mode_never_serves_the_blade_frontend(): void
    {
        config(['app.backend_only' => true]);

        $this->get('/')
            ->assertNotFound()
            ->assertHeader('X-Younz-Frontend', 'nextjs')
            ->assertJsonPath('service', 'younz-laravel-backend')
            ->assertDontSee('<html', false);

        $this->get('/login')
            ->assertNotFound()
            ->assertHeader('X-Younz-Frontend', 'nextjs')
            ->assertDontSee('<html', false);
    }

    public function test_backend_only_mode_keeps_backend_health_endpoint_available(): void
    {
        config(['app.backend_only' => true]);

        $this->get('/up')->assertOk();
    }
}
