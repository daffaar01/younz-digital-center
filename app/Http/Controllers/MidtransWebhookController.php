<?php

namespace App\Http\Controllers;

use App\Actions\ApplyServiceOrderMidtransStatus;
use App\Actions\Topup\ApplyMidtransStatus;
use App\Models\ServiceOrder;
use App\Models\TopupOrder;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MidtransWebhookController extends Controller
{
    public function __invoke(Request $request, AuditLogger $audit, ApplyMidtransStatus $applyStatus, ApplyServiceOrderMidtransStatus $applyServiceStatus): JsonResponse
    {
        $serverKey = (string) config('services.midtrans.server_key');
        $payload = $request->json()->all();
        $orderId = (string) ($payload['order_id'] ?? '');
        $statusCode = (string) ($payload['status_code'] ?? '');
        $grossAmount = (string) ($payload['gross_amount'] ?? '');
        $signature = (string) ($payload['signature_key'] ?? '');

        if ($serverKey === '' || $orderId === '' || $signature === '' || ! hash_equals(hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey), $signature)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $grossAmount)) {
            return response()->json(['message' => 'Invalid amount.'], 422);
        }

        $order = TopupOrder::query()->where('midtrans_order_id', $orderId)->first();
        $serviceOrder = $order ? null : ServiceOrder::query()->where('midtrans_order_id', $orderId)->first();

        $expectedAmount = $order?->total_amount ?? $serviceOrder?->estimated_price;
        $parsedGrossAmount = $this->parseIdrAmount($grossAmount);
        if (($order === null && $serviceOrder === null) || $parsedGrossAmount === null || $parsedGrossAmount !== (int) $expectedAmount) {
            return response()->json(['message' => 'Order not found or amount mismatch.'], 404);
        }

        $becamePaid = $order
            ? $applyStatus->handle($order, $payload)
            : $applyServiceStatus->handle($serviceOrder, $payload);

        $target = $order ?? $serviceOrder;
        $audit->log($order ? 'topup.midtrans_notification' : 'service_order.midtrans_notification', $target, metadata: [
            'transaction_status' => (string) ($payload['transaction_status'] ?? ''),
            'payment_transitioned' => $becamePaid,
        ]);

        return response()->json(['message' => 'Notification accepted.']);
    }

    private function parseIdrAmount(string $amount): ?int
    {
        if (! preg_match('/^\d+(?:\.0{1,2})?$/', $amount)) {
            return null;
        }

        $whole = explode('.', $amount, 2)[0];
        if (strlen($whole) > 18) {
            return null;
        }

        return (int) $whole;
    }
}
