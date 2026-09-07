<?php

namespace App\Ai;

use Closure;
use RuntimeException;

class AgentFakeGateway
{
    /** @var array<class-string, self> */
    private static array $gateways = [];

    /** @var array<int, mixed> */
    private array $responses;

    private int $currentResponseIndex = 0;

    private bool $preventStrayPrompts = false;

    /** @var array<int, AgentPrompt> */
    private array $prompts = [];

    /** @param Closure|array<int, mixed> $responses */
    private function __construct(private readonly string $agentClass, Closure|array $responses)
    {
        $this->responses = is_array($responses) ? $responses : [$responses];
    }

    /** @param Closure|array<int, mixed> $responses */
    public static function fake(string $agentClass, Closure|array $responses = []): self
    {
        return self::$gateways[$agentClass] = new self($agentClass, $responses);
    }

    public static function isFaked(string $agentClass): bool
    {
        return isset(self::$gateways[$agentClass]);
    }

    public static function preventStrayPromptsFor(string $agentClass, bool $prevent = true): self
    {
        $gateway = self::$gateways[$agentClass] ?? self::fake($agentClass);
        $gateway->preventStrayPrompts = $prevent;

        return $gateway;
    }

    public static function promptFor(
        string $agentClass,
        string $prompt,
        array $attachments,
        ?string $provider,
        ?string $model,
        ?int $timeout,
        bool $structured,
    ): AgentResponse {
        $gateway = self::$gateways[$agentClass] ?? null;
        if (! $gateway) {
            throw new RuntimeException('No fake gateway registered for '.$agentClass.'.');
        }

        $gateway->prompts[] = new AgentPrompt($prompt, $attachments, $provider, $model, $timeout);
        $response = $gateway->nextResponse($prompt, $attachments, $provider, $model);

        if ($response instanceof AgentResponse) {
            return $response;
        }

        if ($response === null) {
            if ($gateway->preventStrayPrompts) {
                throw new RuntimeException('Attempted prompt without a fake '.$agentClass.' response.');
            }

            $response = $structured ? [] : 'Fake response for prompt: '.str($prompt)->words(10);
        }

        if (is_array($response)) {
            return new StructuredAgentResponse(
                $response,
                (string) json_encode($response, JSON_UNESCAPED_UNICODE),
                new Usage,
            );
        }

        return new AgentResponse((string) $response, new Usage);
    }

    public static function streamFor(
        string $agentClass,
        string $prompt,
        array $attachments,
        ?string $provider,
        ?string $model,
        ?int $timeout,
    ): AgentStreamResponse {
        $response = self::promptFor($agentClass, $prompt, $attachments, $provider, $model, $timeout, false);

        return self::streamResponse($response);
    }

    public static function streamResponse(AgentResponse $response): AgentStreamResponse
    {
        $deltas = [];
        foreach (explode(' ', $response->text) as $index => $word) {
            if ($word === '') {
                continue;
            }

            $deltas[] = new TextDelta($index === 0 ? $word : ' '.$word);
        }

        return new AgentStreamResponse($response->text, $response->usage, $deltas);
    }

    public function preventStrayPrompts(bool $prevent = true): self
    {
        $this->preventStrayPrompts = $prevent;

        return $this;
    }

    public static function assertPrompted(string $agentClass, Closure|string $callback): void
    {
        $prompts = self::$gateways[$agentClass]->prompts ?? [];
        $matched = collect($prompts)->contains(function (AgentPrompt $prompt) use ($callback): bool {
            return is_string($callback) ? str_contains($prompt->prompt, $callback) : (bool) $callback($prompt);
        });

        if (! $matched) {
            throw new RuntimeException('Expected '.$agentClass.' to receive a matching prompt.');
        }
    }

    public static function assertNotPrompted(string $agentClass, Closure|string $callback): void
    {
        $prompts = self::$gateways[$agentClass]->prompts ?? [];
        $matched = collect($prompts)->contains(function (AgentPrompt $prompt) use ($callback): bool {
            return is_string($callback) ? str_contains($prompt->prompt, $callback) : (bool) $callback($prompt);
        });

        if ($matched) {
            throw new RuntimeException('Expected '.$agentClass.' not to receive a matching prompt.');
        }
    }

    public static function assertNeverPrompted(string $agentClass): void
    {
        if (count(self::$gateways[$agentClass]->prompts ?? []) > 0) {
            throw new RuntimeException('Expected '.$agentClass.' not to receive any prompts.');
        }
    }

    private function nextResponse(string $prompt, array $attachments, ?string $provider, ?string $model): mixed
    {
        $response = $this->responses[$this->currentResponseIndex] ?? null;
        $this->currentResponseIndex++;

        return $response instanceof Closure
            ? $response($prompt, $attachments, $provider, $model)
            : $response;
    }
}
