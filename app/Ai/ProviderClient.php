<?php

namespace App\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class ProviderClient
{
    public function prompt(
        object $agent,
        string $prompt,
        array $attachments = [],
        ?string $provider = null,
        ?string $model = null,
        ?int $timeout = null,
    ): AgentResponse {
        $provider ??= (string) config('ai.default', 'openai-compatible');
        $providerConfig = config("ai.providers.{$provider}", []);
        if ($attachments !== [] && (filled(config('ai.vision.url')) || filled(config('ai.vision.key')))) {
            // Keep credentials paired with their endpoint; never borrow the text key.
            if (! filled(config('ai.vision.url')) || ! filled(config('ai.vision.key'))) {
                throw new RuntimeException('URL dan API key vision khusus harus diisi bersamaan.');
            }
            $providerConfig = [
                'driver' => 'openai-compatible',
                'url' => config('ai.vision.url'),
                'key' => config('ai.vision.key'),
            ];
        }
        $driver = (string) data_get($providerConfig, 'driver', $provider);

        if (! in_array($driver, ['openai', 'openai-compatible'], true)) {
            throw new RuntimeException("AI provider driver [{$driver}] is not supported by the PHP 8.2 adapter.");
        }

        $url = trim((string) data_get($providerConfig, 'url'));
        $key = trim((string) data_get($providerConfig, 'key'));
        if ($url === '' || $key === '') {
            throw new RuntimeException('AI provider URL and key are required.');
        }

        $model ??= (string) data_get($providerConfig, 'models.text.default', 'cx/gpt-5.6-sol');
        $timeout ??= (int) data_get($providerConfig, 'timeout', 60);
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => (string) $agent->instructions()],
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => method_exists($agent, 'maxTokens')
                ? $agent->maxTokens()
                : (int) config('ai.limits.max_output_tokens', 1200),
        ];

        if ($attachments !== []) {
            if (! config('ai.vision.enabled') || ! filled(config('ai.vision.model')) || count($attachments) !== 1 || ! $attachments[0] instanceof VisionImage) {
                throw new RuntimeException('Vision belum dikonfigurasi atau lampiran tidak valid.');
            }
            $payload['model'] = (string) config('ai.vision.model');
            $payload['messages'][1]['content'] = [
                ['type' => 'text', 'text' => $prompt],
                $attachments[0]->content(),
            ];
        }

        if (method_exists($agent, 'structuredOutput') && $agent->structuredOutput()) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::withToken($key)
            ->acceptJson()
            ->asJson()
            ->timeout(max(1, $timeout))
            ->post($this->endpoint($url), $payload)
            ->throw();
        $body = $response->json();
        $text = $this->content(data_get($body, 'choices.0.message.content', data_get($body, 'choices.0.text')));
        $usage = new Usage(
            (int) data_get($body, 'usage.prompt_tokens', 0),
            (int) data_get($body, 'usage.completion_tokens', 0),
        );

        if (method_exists($agent, 'structuredOutput') && $agent->structuredOutput()) {
            $structured = json_decode($text, true);
            if (! is_array($structured)) {
                throw new RuntimeException('AI provider returned invalid structured output.');
            }

            return new StructuredAgentResponse($structured, $text, $usage);
        }

        return new AgentResponse($text, $usage);
    }

    public function stream(
        object $agent,
        string $prompt,
        array $attachments = [],
        ?string $provider = null,
        ?string $model = null,
        ?int $timeout = null,
    ): AgentStreamResponse {
        return AgentFakeGateway::streamResponse($this->prompt($agent, $prompt, $attachments, $provider, $model, $timeout));
    }

    private function endpoint(string $url): string
    {
        return str_ends_with($url, '/chat/completions')
            ? $url
            : rtrim($url, '/').'/chat/completions';
    }

    private function content(mixed $content): string
    {
        if (is_string($content)) {
            return $content;
        }

        if (! is_array($content)) {
            return '';
        }

        return collect($content)->map(function (mixed $part): string {
            if (is_string($part)) {
                return $part;
            }

            return (string) data_get($part, 'text', '');
        })->implode('');
    }
}
