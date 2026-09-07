<?php

namespace App\Http\Controllers;

use App\Ai\KnowledgeBaseResponder;
use App\Ai\OrderIntakeInterpreter;
use App\Models\AiUsageLog;
use App\Models\ServiceOrder;
use App\Support\AiBudgetGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AiController extends Controller
{
    public function chat(Request $request, KnowledgeBaseResponder $knowledge, AiBudgetGuard $budget): JsonResponse
    {
        $answer = $this->executeChat($request, $knowledge, $budget);

        return response()->json(['data' => collect($answer)->only(['answer', 'sources', 'ai_generated', 'grounding', 'interaction_token'])->all()]);
    }

    public function chatStream(Request $request, KnowledgeBaseResponder $knowledge, AiBudgetGuard $budget): StreamedResponse
    {
        [$message, $history] = $this->prepareChat($request, $budget);

        return response()->stream(function () use ($request, $knowledge, $message, $history): void {
            $streamedText = '';
            $this->emit('status', ['status' => 'connected']);
            $this->flushStream();

            try {
                $answer = $knowledge->stream($message, $history, function (string $delta) use (&$streamedText): void {
                    $streamedText .= $delta;
                    $this->emit('text_delta', ['delta' => $delta]);
                    $this->flushStream();
                });

                if ($streamedText === '') {
                    $this->emit('text_delta', ['delta' => $answer['answer']]);
                    $this->flushStream();
                }

                $answer = $this->finalizeChat($request, $message, $history, $answer, persistSession: true);
                $this->emit('sources', ['sources' => $answer['sources']]);
                $this->emit('grounding', ['grounding' => $answer['grounding']]);
                $this->emit('interaction', ['interaction_token' => $answer['interaction_token']]);
            } catch (Throwable $exception) {
                report($exception);
                $this->emit('error', ['message' => 'Stream jawaban terputus. Silakan coba lagi.']);
            }

            echo "data: [DONE]\n\n";
            $this->flushStream();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function clearHistory(Request $request): JsonResponse
    {
        if ($request->hasSession()) {
            $request->session()->forget('ai_chat_history');
        }

        return response()->json(['message' => 'Riwayat percakapan telah dihapus.']);
    }

    public function feedback(Request $request): JsonResponse
    {
        $data = $request->validate([
            'interaction_token' => ['required', 'uuid'],
            'feedback' => ['required', Rule::in(['helpful', 'not_helpful'])],
        ]);
        $usage = AiUsageLog::query()->where('interaction_token', $data['interaction_token'])->firstOrFail();
        $usage->update(['feedback' => $data['feedback'], 'feedback_at' => now()]);

        return response()->json(['message' => 'Terima kasih atas masukannya.']);
    }

    public function quickTrack(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_number' => ['required', 'string', 'max:30'],
            'phone' => ['required', 'string', 'max:30'],
        ]);
        $phone = preg_replace('/\D+/', '', $data['phone']);
        $order = ServiceOrder::query()
            ->where('order_number', str($data['order_number'])->upper())
            ->where('customer_phone', $phone)
            ->with('service')
            ->first();

        if (! $order) {
            return response()->json(['found' => false, 'message' => 'Pesanan tidak ditemukan. Periksa kembali nomor pesanan dan nomor HP.']);
        }

        return response()->json([
            'found' => true,
            'data' => [
                'order_number' => $order->order_number,
                'service' => data_get($order, 'service.name', '-'),
                'status' => $order->status->label(),
                'status_code' => $order->status->value,
                'estimated_price' => $order->estimated_price ? 'Rp '.number_format($order->estimated_price, 0, ',', '.') : '-',
                'final_price' => $order->final_price ? 'Rp '.number_format($order->final_price, 0, ',', '.') : '-',
                'paid_amount' => $order->paid_amount ? 'Rp '.number_format($order->paid_amount, 0, ',', '.') : '0',
                'created_at' => $order->created_at->format('d M Y H:i'),
                'deadline_at' => $order->deadline_at?->format('d M Y H:i') ?? '-',
                'pickup_at' => $order->pickup_at?->format('d M Y H:i') ?? '-',
            ],
        ]);
    }

    public function orderIntake(Request $request, OrderIntakeInterpreter $interpreter, AiBudgetGuard $budget): JsonResponse
    {
        if ($request->bearerToken()) {
            abort_unless($request->user()->tokenCan('ai:order-intake'), 403);
        }
        $data = $request->validate(['message' => ['required', 'string', 'max:3000']]);
        $budget->assertAvailable('order_intake', $data['message'], []);
        $interpretation = $interpreter->interpret($data['message']);
        $result = $interpretation['data'];
        AiUsageLog::create([
            'user_id' => $request->user()?->id,
            'feature' => 'order_intake',
            'interaction_token' => (string) Str::uuid(),
            'provider' => $interpretation['provider'],
            'model' => $interpretation['model'],
            'metadata' => [
                'requires_follow_up' => $result['requires_follow_up'],
                'fallback' => $interpretation['fallback'],
            ],
        ]);

        return response()->json([
            'data' => $result,
            'message' => $result['requires_follow_up'] ? 'Beberapa informasi masih perlu dikonfirmasi.' : 'Draft pesanan berhasil disusun dan harus diperiksa operator.',
        ]);
    }

    /** @return array<string, mixed> */
    private function executeChat(Request $request, KnowledgeBaseResponder $knowledge, AiBudgetGuard $budget): array
    {
        [$message, $history] = $this->prepareChat($request, $budget);

        return $this->finalizeChat($request, $message, $history, $knowledge->answer($message, $history));
    }

    /** @return array{0:string,1:list<array{role:string,content:string}>} */
    private function prepareChat(Request $request, AiBudgetGuard $budget): array
    {
        $maxHistory = (int) config('ai.limits.history_messages', 12);
        $maxCharacters = (int) config('ai.limits.message_characters', 3000);
        $data = $request->validate([
            'message' => ['required', 'string', 'max:'.$maxCharacters],
            'history' => ['sometimes', 'array', 'max:'.$maxHistory],
            'history.*.role' => ['required', Rule::in(['user', 'assistant'])],
            'history.*.content' => ['required', 'string', 'max:'.$maxCharacters],
            'ai_consent' => ['accepted'],
        ]);
        $history = array_slice($data['history'] ?? [], -$maxHistory);

        if ($request->hasSession() && $history === []) {
            $history = array_slice((array) $request->session()->get('ai_chat_history', []), -$maxHistory);
        }

        $budget->assertAvailable('faq_chat', $data['message'], $history);

        return [$data['message'], $history];
    }

    /** @param array<string, mixed> $answer @return array<string, mixed> */
    private function finalizeChat(Request $request, string $message, array $history, array $answer, bool $persistSession = false): array
    {
        $maxHistory = (int) config('ai.limits.history_messages', 12);
        $interactionToken = (string) Str::uuid();
        $inputRate = (int) config('ai.limits.input_cost_per_million_micros', 0);
        $outputRate = (int) config('ai.limits.output_cost_per_million_micros', 0);
        $costMicros = (int) ceil(($answer['input_tokens'] * $inputRate + $answer['output_tokens'] * $outputRate) / 1_000_000);
        AiUsageLog::create([
            'user_id' => $request->user()?->id,
            'feature' => 'faq_chat',
            'interaction_token' => $interactionToken,
            'provider' => $answer['provider'],
            'model' => $answer['model'],
            'input_tokens' => $answer['input_tokens'],
            'output_tokens' => $answer['output_tokens'],
            'cost_micros' => $costMicros,
            'status' => $answer['status'],
            'metadata' => [
                'sources' => collect($answer['sources'])->map(fn (array $source) => ['id' => $source['id'], 'type' => $source['type']])->all(),
                'ai_generated' => $answer['ai_generated'],
                'grounding' => $answer['grounding'],
                'conversation_turns' => count($history),
            ],
        ]);

        if ($request->hasSession()) {
            $sessionHistory = array_slice([
                ...$history,
                ['role' => 'user', 'content' => $message],
                ['role' => 'assistant', 'content' => $answer['answer']],
            ], -$maxHistory);
            $request->session()->put('ai_chat_history', $sessionHistory);

            if ($persistSession) {
                $request->session()->save();
            }
        }

        return $answer + ['interaction_token' => $interactionToken];
    }

    /** @param array<string, mixed> $payload */
    private function emit(string $type, array $payload): void
    {
        echo 'data: '.json_encode(['type' => $type] + $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n";
    }

    private function flushStream(): void
    {
        if (ob_get_level() > 0) {
            @ob_flush();
        }

        flush();
    }
}
