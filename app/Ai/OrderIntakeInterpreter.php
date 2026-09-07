<?php

namespace App\Ai;

use App\Ai\Agents\OrderIntakeAgent;
use App\Support\AiProviderResilience;
use Illuminate\Support\Facades\Log;
use App\Ai\StructuredAgentResponse;
use Throwable;

class OrderIntakeInterpreter
{
    public function __construct(
        private readonly OrderIntakeParser $fallback,
        private readonly AiProviderResilience $resilience,
    ) {}

    /**
     * @return array{data: array<string, mixed>, provider: string, model: string, fallback: bool}
     */
    public function interpret(string $message): array
    {
        $provider = (string) config('ai.default', 'openai-compatible');
        $providerConfig = config("ai.providers.{$provider}", []);
        $model = (string) data_get($providerConfig, 'models.text.default', 'cx/gpt-5.6-sol');

        if (blank(data_get($providerConfig, 'url')) || blank(data_get($providerConfig, 'key'))) {
            return $this->fallbackResult($message);
        }

        try {
            $response = $this->resilience->execute(
                $provider,
                fn () => (new OrderIntakeAgent)->prompt(
                    $message,
                    provider: $provider,
                    model: $model,
                    timeout: (int) data_get($providerConfig, 'timeout', 60),
                ),
            );

            if (! $response instanceof StructuredAgentResponse) {
                Log::warning('AI order-intake returned a non-structured response; deterministic fallback used.', [
                    'provider' => $provider,
                    'model' => $model,
                ]);

                return $this->fallbackResult($message);
            }

            return [
                'data' => $this->normalize($response->toArray(), $message),
                'provider' => $provider,
                'model' => $model,
                'fallback' => false,
            ];
        } catch (Throwable $exception) {
            Log::warning('AI order-intake provider failed; deterministic fallback used.', [
                'provider' => $provider,
                'model' => $model,
                'exception' => $exception::class,
            ]);

            return $this->fallbackResult($message);
        }
    }

    /** @param array<string, mixed> $generated */
    private function normalize(array $generated, string $message): array
    {
        $baseline = $this->fallback->parse($message);
        $service = str((string) ($generated['service'] ?? ''))->lower()->trim()->toString();
        $validServices = ['print', 'fotokopi', 'scan', 'ketik', 'desain', 'website', 'aplikasi'];
        $service = in_array($service, $validServices, true) ? $service : $baseline['service'];
        $copies = filter_var($generated['copies'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: $baseline['copies'];

        $data = [
            'service' => $service,
            'paper_size' => $this->nullableString($generated['paper_size'] ?? null, uppercase: true) ?? $baseline['paper_size'],
            'color_mode' => $this->nullableString($generated['color_mode'] ?? null) ?? $baseline['color_mode'],
            'sides' => $this->nullableString($generated['sides'] ?? null) ?? $baseline['sides'],
            'copies' => $copies,
            'finishing' => $this->nullableString($generated['finishing'] ?? null) ?? $baseline['finishing'],
            'pickup_time' => $this->nullableString($generated['pickup_time'] ?? null) ?? $baseline['pickup_time'],
            'notes' => $message,
        ];

        $uncertain = collect($generated['uncertain_fields'] ?? [])
            ->filter(fn (mixed $field) => is_string($field) && array_key_exists($field, $data) && blank($data[$field]))
            ->merge(collect(['service', 'copies'])->filter(fn (string $field) => blank($data[$field])))
            ->unique()
            ->values()
            ->all();

        $data['uncertain_fields'] = $uncertain;
        $data['requires_follow_up'] = $uncertain !== [];

        return $data;
    }

    private function nullableString(mixed $value, bool $uppercase = false): ?string
    {
        if (! is_string($value) || blank($value)) {
            return null;
        }

        $value = trim($value);

        return $uppercase ? mb_strtoupper($value) : $value;
    }

    /** @return array{data: array<string, mixed>, provider: string, model: string, fallback: bool} */
    private function fallbackResult(string $message): array
    {
        return [
            'data' => $this->fallback->parse($message),
            'provider' => 'local',
            'model' => 'deterministic_fallback',
            'fallback' => true,
        ];
    }
}
