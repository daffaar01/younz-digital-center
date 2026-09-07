<?php

namespace App\Http\Controllers;

use App\Enums\TopupFulfillmentStatus;
use App\Jobs\SendTopupDiscordNotification;
use App\Jobs\SendTopupWhatsAppNotification;
use App\Models\TopupOrder;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DigiflazzWebhookController extends Controller
{
    public function __invoke(Request $request, AuditLogger $audit): JsonResponse
    {
        $secret = (string) config('services.digiflazz.webhook_secret');
        $signature = (string) $request->header('X-Hub-Signature', '');
        $expected = 'sha1='.hash_hmac('sha1', $request->getContent(), $secret);

        if ($secret === '' || ! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $payload = $request->json()->all();
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        $reference = (string) ($data['ref_id'] ?? '');

        $order = TopupOrder::query()
            ->where('digiflazz_reference', $reference)
            ->orWhere('order_number', $reference)
            ->first();

        if ($reference === '' || $order === null) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        $event = DB::transaction(function () use ($order, $data): ?string {
            $locked = TopupOrder::query()->lockForUpdate()->findOrFail($order->id);
            $previous = $locked->fulfillment_status;
            $status = strtolower((string) ($data['status'] ?? 'pending'));
            $updates = [
                'provider_rc' => filled($data['rc'] ?? null) ? (string) $data['rc'] : $locked->provider_rc,
                'provider_message' => filled($data['message'] ?? null) ? (string) $data['message'] : $locked->provider_message,
                'serial_number' => filled($data['sn'] ?? null) ? (string) $data['sn'] : $locked->serial_number,
            ];

            if ($locked->fulfillment_status !== TopupFulfillmentStatus::Success) {
                if ($status === 'sukses') {
                    $updates['fulfillment_status'] = TopupFulfillmentStatus::Success;
                    $updates['fulfilled_at'] = now();
                } elseif ($status === 'gagal') {
                    $updates['fulfillment_status'] = TopupFulfillmentStatus::Failed;
                } else {
                    $updates['fulfillment_status'] = TopupFulfillmentStatus::ProviderPending;
                }
            }

            $locked->update($updates);
            $current = $locked->fresh()->fulfillment_status;

            return match (true) {
                $previous !== TopupFulfillmentStatus::Success && $current === TopupFulfillmentStatus::Success => 'success',
                $previous !== TopupFulfillmentStatus::Failed && $current === TopupFulfillmentStatus::Failed => 'failed',
                default => null,
            };
        });

        if ($event !== null) {
            SendTopupWhatsAppNotification::dispatch($order->id, $event);
            SendTopupDiscordNotification::dispatch($order->id, $event);
        }

        $audit->log('topup.digiflazz_notification', $order, metadata: ['provider_status' => (string) ($data['status'] ?? '')]);

        return response()->json(['message' => 'Notification accepted.']);
    }
}
