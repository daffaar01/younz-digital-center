<?php

namespace App\Support;

use App\Models\AiUsageLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class AiBudgetGuard
{
    public function assertAvailable(string $feature, string $message, array $history, int $additionalInputTokens = 0): void
    {
        $estimatedTokens = (int) ceil((strlen($message) + collect($history)->sum(fn (array $item) => strlen((string) ($item['content'] ?? '')))) / 4);
        $estimatedTokens += max(0, $additionalInputTokens);
        $maxOutputTokens = (int) config('ai.limits.max_output_tokens', 1200);
        $reservedTokens = $estimatedTokens + $maxOutputTokens;
        $inputRate = (int) config('ai.limits.input_cost_per_million_micros', 1_000_000);
        $outputRate = (int) config('ai.limits.output_cost_per_million_micros', 4_000_000);
        $reservedCost = (int) ceil((($estimatedTokens * $inputRate) + ($maxOutputTokens * $outputRate)) / 1_000_000);
        $date = today()->toDateString();
        $stateKey = "ai-budget-state:{$date}";

        Cache::lock("ai-budget-lock:{$date}", 5)->block(3, function () use ($feature, $reservedTokens, $reservedCost, $stateKey): void {
            $state = Cache::get($stateKey);
            if (! is_array($state)) {
                $today = AiUsageLog::query()->whereDate('created_at', today());
                $state = [
                    'requests' => (clone $today)
                        ->selectRaw('feature, count(*) as aggregate')
                        ->groupBy('feature')
                        ->pluck('aggregate', 'feature')
                        ->map(fn ($value) => (int) $value)
                        ->all(),
                    'tokens' => (int) (clone $today)->sum('input_tokens') + (int) (clone $today)->sum('output_tokens'),
                    'cost' => (int) (clone $today)->sum('cost_micros'),
                ];
            }

            $requestLimit = (int) config('ai.limits.daily_requests', 500);
            $tokenLimit = (int) config('ai.limits.daily_tokens', 200_000);
            $costLimit = (int) config('ai.limits.daily_cost_micros', 5_000_000);

            if ((int) data_get($state, "requests.{$feature}", 0) >= $requestLimit) {
                throw ValidationException::withMessages(['message' => 'Batas permintaan AI harian tercapai. Silakan hubungi operator.']);
            }
            if ((int) $state['tokens'] + $reservedTokens > $tokenLimit) {
                throw ValidationException::withMessages(['message' => 'Batas token AI harian tercapai. Silakan hubungi operator.']);
            }
            if ($costLimit > 0 && (int) $state['cost'] + $reservedCost > $costLimit) {
                throw ValidationException::withMessages(['message' => 'Batas biaya AI harian tercapai. Silakan hubungi operator.']);
            }

            data_set($state, "requests.{$feature}", (int) data_get($state, "requests.{$feature}", 0) + 1);
            $state['tokens'] += $reservedTokens;
            $state['cost'] += $reservedCost;
            Cache::put($stateKey, $state, now()->endOfDay()->addMinute());
        });
    }
}
