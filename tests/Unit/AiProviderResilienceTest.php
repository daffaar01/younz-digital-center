<?php

namespace Tests\Unit;

use App\Support\AiProviderResilience;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

class AiProviderResilienceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'ai.providers.openai-compatible.url' => 'http://9router.test/v1',
            'ai.resilience.attempts' => 2,
            'ai.resilience.retry_delay_ms' => 0,
            'ai.resilience.failure_threshold' => 2,
            'ai.resilience.cooldown_seconds' => 30,
        ]);
        Cache::flush();
    }

    public function test_it_retries_transient_provider_failures(): void
    {
        $calls = 0;

        $result = app(AiProviderResilience::class)->execute('openai-compatible', function () use (&$calls): string {
            $calls++;

            if ($calls === 1) {
                throw new RuntimeException('9Router belum siap.');
            }

            return 'ok';
        });

        $this->assertSame('ok', $result);
        $this->assertSame(2, $calls);
    }

    public function test_it_opens_circuit_after_repeated_failed_requests(): void
    {
        $resilience = app(AiProviderResilience::class);
        config(['ai.resilience.attempts' => 1]);

        foreach (range(1, 2) as $_) {
            try {
                $resilience->execute('openai-compatible', fn () => throw new RuntimeException('down'));
            } catch (RuntimeException) {
                //
            }
        }

        $this->assertTrue($resilience->isOpen('openai-compatible'));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('circuit is temporarily open');
        $resilience->execute('openai-compatible', fn () => 'tidak dijalankan');
    }

    public function test_retry_can_be_disabled_after_a_stream_has_started(): void
    {
        $calls = 0;

        $this->expectException(RuntimeException::class);

        try {
            app(AiProviderResilience::class)->execute(
                'openai-compatible',
                function () use (&$calls): never {
                    $calls++;
                    throw new RuntimeException('stream terputus');
                },
                fn (): bool => false,
            );
        } finally {
            $this->assertSame(1, $calls);
        }
    }
}
