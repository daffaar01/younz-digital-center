<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWhatsAppInboundMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class WhatsAppWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $expected = (string) config('services.whatsapp.internal_token');
        $supplied = (string) $request->bearerToken();
        abort_unless(strlen($expected) >= 16 && strlen($supplied) === strlen($expected) && hash_equals($expected, $supplied), 401);

        $data = $request->validate([
            'messageId' => ['required', 'string', 'max:200'],
            'chatJid' => ['required', 'string', 'max:200'],
            'senderJid' => ['required', 'string', 'max:200'],
            'text' => ['nullable', 'required_without:image', 'string', 'max:3000'],
            'image' => ['nullable', 'file', 'max:5120', 'mimes:jpeg,png,webp'],
            'timestamp' => ['required', 'integer'],
        ]);

        if (! str_ends_with($data['chatJid'], '@s.whatsapp.net')) {
            return response()->json(['accepted' => false, 'reason' => 'unsupported_chat']);
        }

        $deduplicationKey = 'whatsapp:inbound:'.hash('sha256', $data['messageId']);
        if (! Cache::add($deduplicationKey, true, now()->addDays(7))) {
            return response()->json(['accepted' => true, 'duplicate' => true]);
        }

        $path = null;
        try {
            if ($request->hasFile('image')) {
                \App\Ai\VisionImage::fromBytes($request->file('image')->get());
                $path = $request->file('image')->store('whatsapp-vision', 'local');
                abort_unless(is_string($path), 500);
                \App\Jobs\ProcessWhatsAppImage::dispatch($path, $data['senderJid'], trim($data['text'] ?? ''));
            } else {
                ProcessWhatsAppInboundMessage::dispatch(
                    $data['messageId'], $data['chatJid'], $data['senderJid'], trim($data['text']),
                );
            }
        } catch (\Throwable $exception) {
            Cache::forget($deduplicationKey);
            if (is_string($path)) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return response()->json(['accepted' => true], 202);
    }
}
