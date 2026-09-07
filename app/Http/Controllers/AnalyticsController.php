<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnalyticsController extends Controller
{
    public const EVENTS = [
        'page_view',
        'order_cta_clicked',
        'order_form_started',
        'order_submitted',
        'order_submit_failed',
        'track_order_started',
        'topup_cta_clicked',
        'ai_question_sent',
    ];

    public const SAFE_PATHS = [
        '/',
        '/pesan',
        '/cek-pesanan',
        '/topup',
    ];

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event' => ['required', Rule::in(self::EVENTS)],
            'visitor_id' => ['required', 'uuid'],
            'path' => ['required', 'string', Rule::in(self::SAFE_PATHS)],
            'source' => ['nullable', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_-]*$/'],
            'analytics_consent' => ['accepted'],
        ]);

        AnalyticsEvent::create([
            'event_name' => $data['event'],
            'visitor_hash' => hash_hmac('sha256', $data['visitor_id'], (string) config('app.key')),
            'path' => $data['path'],
            'source' => filled($data['source'] ?? null) ? $data['source'] : null,
        ]);

        return response()->json(['message' => 'Event diterima.'], 202);
    }
}
