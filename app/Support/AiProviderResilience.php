<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class AiProviderResilience
{
    public function execute(string $provider, Closure $operation, ?Closure $retryWhen = null): mixed
    {
        if ($this->isOpen($provider)) {
            throw new RuntimeException('AI provider circuit is temporarily open.');
        }

        $attempts = max(1, (int) config('ai.resilience.attempts', 2));
        $delayMs = max(0, (int) config('ai.resilience.retry_delay_ms', 250));

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $result = $operation();
                $this->recordSuccess($provider);

                return $result;
            } catch (Throwable $exception) {
                $mayRetry = $attempt < $attempts
                    && ($retryWhen === null || $retryWhen($exception));

                if (! $mayRetry) {
                    $this->recordFailure($provider);

                    throw $exception;
                }

                if ($delayMs > 0) {
                    usleep($delayMs * $attempt * 1000);
                }
            }
        }

        throw new RuntimeException('AI provider request failed.');
    }

    public function isOpen(string $provider): bool
    {
        return (bool) Cache::get($this->key($provider, 'open'), false);
    }

    private function recordSuccess(string $provider): void
    {
        Cache::forget($this->key($provider, 'failures'));
        Cache::forget($this->key($provider, 'open'));
    }

    private function recordFailure(string $provider): void
    {
        $threshold = max(1, (int) config('ai.resilience.failure_threshold', 3));
        $cooldown = max(1, (int) config('ai.resilience.cooldown_seconds', 30));
        $failureKey = $this->key($provider, 'failures');
        $failures = (int) Cache::get($failureKey, 0) + 1;

        Cache::put($failureKey, $failures, now()->addSeconds($cooldown));

        if ($failures >= $threshold) {
            Cache::put($this->key($provider, 'open'), true, now()->addSeconds($cooldown));
        }
    }

    private function key(string $provider, string $suffix): string
    {
        $url = (string) config("ai.providers.{$provider}.url", '');

        return 'ai-provider:'.hash('sha256', $provider.'|'.$url).':'.$suffix;
    }
}
