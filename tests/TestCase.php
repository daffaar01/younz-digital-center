<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
use Tests\Support\TestEnvironment;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        // Also protect runners that instantiate this class without phpunit.xml.
        TestEnvironment::prepare();
        $app = require dirname(__DIR__).'/bootstrap/app.php';
        $this->traitsUsedByTest = array_flip(class_uses_recursive(static::class));
        TestEnvironment::configureApplication($app);
        $app->make(Kernel::class)->bootstrap();
        TestEnvironment::assertSafeApplication($app);

        return $app;
    }

    /** @param array<string, mixed> $data @param array<string, string> $headers */
    protected function postAiChat(array $data, array $headers = []): TestResponse
    {
        return $this->postJson('/api/v1/ai/chat', ['ai_consent' => '1'] + $data, $headers);
    }
}
