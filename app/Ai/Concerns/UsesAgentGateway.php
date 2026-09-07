<?php

namespace App\Ai\Concerns;

use App\Ai\AgentFakeGateway;
use App\Ai\AgentResponse;
use App\Ai\AgentStreamResponse;
use App\Ai\ProviderClient;
use Closure;

trait UsesAgentGateway
{
    public function prompt(
        string $prompt,
        array $attachments = [],
        ?string $provider = null,
        ?string $model = null,
        ?int $timeout = null,
    ): AgentResponse {
        if (AgentFakeGateway::isFaked(static::class)) {
            return AgentFakeGateway::promptFor(
                static::class,
                $prompt,
                $attachments,
                $provider,
                $model,
                $timeout,
                method_exists($this, 'structuredOutput') && $this->structuredOutput(),
            );
        }

        return app(ProviderClient::class)->prompt($this, $prompt, $attachments, $provider, $model, $timeout);
    }

    public function stream(
        string $prompt,
        array $attachments = [],
        ?string $provider = null,
        ?string $model = null,
        ?int $timeout = null,
    ): AgentStreamResponse {
        if (AgentFakeGateway::isFaked(static::class)) {
            return AgentFakeGateway::streamFor(static::class, $prompt, $attachments, $provider, $model, $timeout);
        }

        return app(ProviderClient::class)->stream($this, $prompt, $attachments, $provider, $model, $timeout);
    }

    /** @param Closure|array<int, mixed> $responses */
    public static function fake(Closure|array $responses = []): AgentFakeGateway
    {
        return AgentFakeGateway::fake(static::class, $responses);
    }

    public static function assertPrompted(Closure|string $callback): void
    {
        AgentFakeGateway::assertPrompted(static::class, $callback);
    }

    public static function assertNotPrompted(Closure|string $callback): void
    {
        AgentFakeGateway::assertNotPrompted(static::class, $callback);
    }

    public static function assertNeverPrompted(): void
    {
        AgentFakeGateway::assertNeverPrompted(static::class);
    }
}
