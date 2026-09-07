<?php

namespace App\Http\Controllers;

use App\Actions\Services\TransitionServiceOrder;
use App\Enums\ServiceOrderStatus;
use App\Models\ServiceFile;
use App\Models\ServiceOrder;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerOrderActionController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function acceptEstimateApi(Request $request, ServiceOrder $order, TransitionServiceOrder $transition): JsonResponse
    {
        $this->acceptEstimate($request, $order, $transition);
        return response()->json(['message' => 'Estimasi disetujui. Ikuti petunjuk pembayaran dari operator.']);
    }

    public function rejectEstimateApi(Request $request, ServiceOrder $order, TransitionServiceOrder $transition): JsonResponse
    {
        $this->rejectEstimate($request, $order, $transition);
        return response()->json(['message' => 'Estimasi ditolak dan pesanan dibatalkan.']);
    }

    public function confirmPaymentApi(Request $request, ServiceOrder $order): JsonResponse
    {
        $this->confirmPayment($request, $order);
        return response()->json(['message' => 'Konfirmasi pembayaran diterima dan akan diverifikasi operator.']);
    }

    public function requestRevisionApi(Request $request, ServiceOrder $order, TransitionServiceOrder $transition): JsonResponse
    {
        $this->requestRevision($request, $order, $transition);
        return response()->json(['message' => 'Permintaan revisi dikirim kepada operator.']);
    }

    public function downloadApi(Request $request, ServiceOrder $order, ServiceFile $file): StreamedResponse
    {
        return $this->download($request, $order, $file);
    }
    public function acceptEstimate(Request $request, ServiceOrder $order, TransitionServiceOrder $transition): RedirectResponse
    {
        $this->authorizeOrder($request, $order);

        DB::transaction(function () use ($request, $order, $transition): void {
            $locked = ServiceOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->status !== ServiceOrderStatus::AwaitingCustomer || $locked->estimated_price === null) {
                throw ValidationException::withMessages(['order' => 'Estimasi pesanan belum siap untuk disetujui.']);
            }

            $before = $locked->only(['estimate_approved_at']);
            $locked->update(['estimate_approved_at' => now()]);
            $payable = $locked->final_price ?? $locked->estimated_price;
            $next = $locked->paid_amount >= $payable ? ServiceOrderStatus::Queued : ServiceOrderStatus::AwaitingPayment;
            $transition->handle($locked, $next, $request->user(), 'Estimasi disetujui pelanggan.');
            $this->audit->log('service_order.estimate_accepted', $locked, $before, $locked->fresh()->only(['estimate_approved_at']));
        }, 3);

        return back()->with('status', 'Estimasi disetujui. Ikuti petunjuk pembayaran dari operator.');
    }

    public function rejectEstimate(Request $request, ServiceOrder $order, TransitionServiceOrder $transition): RedirectResponse
    {
        $this->authorizeOrder($request, $order);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        if ($order->status !== ServiceOrderStatus::AwaitingCustomer) {
            throw ValidationException::withMessages(['order' => 'Estimasi ini sudah tidak menunggu keputusan pelanggan.']);
        }

        $transition->handle($order, ServiceOrderStatus::Cancelled, $request->user(), 'Estimasi ditolak pelanggan: '.$data['reason']);
        $this->audit->log('service_order.estimate_rejected', $order, metadata: ['reason' => $data['reason']]);

        return back()->with('status', 'Estimasi ditolak dan pesanan dibatalkan.');
    }

    public function confirmPayment(Request $request, ServiceOrder $order): RedirectResponse
    {
        $this->authorizeOrder($request, $order);
        $data = $request->validate([
            'method' => ['required', Rule::in(['transfer', 'qris', 'cash', 'other'])],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        DB::transaction(function () use ($request, $order, $data): void {
            $locked = ServiceOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->status !== ServiceOrderStatus::AwaitingPayment || ! $locked->estimate_approved_at) {
                throw ValidationException::withMessages(['order' => 'Pesanan ini belum siap untuk konfirmasi pembayaran.']);
            }

            $before = $locked->only(['payment_confirmation_at', 'payment_confirmation_method', 'payment_confirmation_reference']);
            $locked->update([
                'payment_confirmation_at' => now(),
                'payment_confirmation_method' => $data['method'],
                'payment_confirmation_reference' => $data['reference'] ?? null,
            ]);
            $locked->statusHistories()->create([
                'user_id' => $request->user()->id,
                'from_status' => $locked->status->value,
                'to_status' => $locked->status->value,
                'notes' => 'Pelanggan mengirim konfirmasi pembayaran melalui '.$data['method'].'. Menunggu verifikasi operator.',
            ]);
            $this->audit->log('service_order.payment_confirmed_by_customer', $locked, $before, $locked->fresh()->only(['payment_confirmation_at', 'payment_confirmation_method', 'payment_confirmation_reference']));
        }, 3);

        return back()->with('status', 'Konfirmasi pembayaran diterima dan akan diverifikasi operator.');
    }

    public function requestRevision(Request $request, ServiceOrder $order, TransitionServiceOrder $transition): RedirectResponse
    {
        $this->authorizeOrder($request, $order);
        $data = $request->validate(['notes' => ['required', 'string', 'min:5', 'max:1000']]);

        DB::transaction(function () use ($request, $order, $transition, $data): void {
            $locked = ServiceOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->status !== ServiceOrderStatus::Ready) {
                throw ValidationException::withMessages(['order' => 'Revisi hanya dapat diminta ketika hasil sudah siap.']);
            }
            if ($locked->revision_requests >= (int) config('services.orders.max_revision_requests', 3)) {
                throw ValidationException::withMessages(['order' => 'Batas permintaan revisi tercapai. Hubungi operator untuk bantuan.']);
            }

            $locked->increment('revision_requests');
            $transition->handle($locked->fresh(), ServiceOrderStatus::AwaitingRevision, $request->user(), 'Permintaan revisi pelanggan: '.$data['notes']);
            $this->audit->log('service_order.revision_requested', $locked, metadata: ['revision' => $locked->fresh()->revision_requests]);
        }, 3);

        return back()->with('status', 'Permintaan revisi dikirim kepada operator.');
    }

    public function download(Request $request, ServiceOrder $order, ServiceFile $file): StreamedResponse
    {
        $this->authorizeOrder($request, $order);
        abort_unless($file->service_order_id === $order->id && $file->kind === 'result', 404);
        abort_unless($order->canReleaseResults(), 403, 'File hasil tersedia setelah pembayaran lunas dan status pesanan siap atau selesai.');
        $this->audit->log('service_file.customer_downloaded', $file, metadata: ['order_id' => $order->id]);

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    public function invoice(Request $request, ServiceOrder $order): View
    {
        $this->authorizeOrder($request, $order);

        return view('customer.orders.invoice', ['order' => $order->load('service')]);
    }

    private function authorizeOrderWrite(Request $request): void
    {
        if ($request->bearerToken()) {
            abort_unless($request->user()->tokenCan('orders:write'), 403);
        }
    }

    private function authorizeOrder(Request $request, ServiceOrder $order): void
    {
        if ($request->bearerToken()) {
            abort_unless($request->user()->tokenCan('orders:read'), 403);
        }
        $customer = $request->user()->customer;
        abort_unless($customer && $order->customer_id === $customer->id, 404);
    }
}
