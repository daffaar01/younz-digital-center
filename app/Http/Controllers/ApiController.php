<?php

namespace App\Http\Controllers;

use App\Actions\ApplyServiceOrderMidtransStatus;
use App\Enums\ServiceOrderStatus;
use App\Http\Requests\ServiceOrderRequest;
use App\Integrations\Midtrans\MidtransClient;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\Testimonial;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApiController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        abort_unless($request->user()->tokenCan('profile:read'), 403);

        return response()->json(['data' => $request->user()]);
    }

    public function customerPortal(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->tokenCan('profile:read') && ! $user->isStaff() && $user->hasVerifiedEmail(), 403);
        $customer = $user->customer;
        abort_unless($customer, 403, 'Profil pelanggan belum tersedia.');
        $orders = $customer->serviceOrders()->with('service:id,name')->latest();

        return response()->json(['data' => [
            'user' => $user->only(['id', 'name', 'email', 'phone']),
            'customer' => $customer->only(['id', 'name', 'email', 'phone', 'address', 'marketing_consent']),
            'summary' => [
                'total' => (clone $orders)->count(),
                'active' => (clone $orders)->whereNotIn('status', [ServiceOrderStatus::Completed->value, ServiceOrderStatus::Cancelled->value])->count(),
                'completed' => (clone $orders)->where('status', ServiceOrderStatus::Completed->value)->count(),
            ],
            'orders' => $orders->limit(20)->get()->map(fn (ServiceOrder $order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'type' => $order->type,
                'service' => $order->service?->name,
                'status' => ['code' => $order->status->value, 'label' => $order->status->label()],
                'estimated_price' => $order->estimated_price,
                'final_price' => $order->final_price,
                'paid_amount' => $order->paid_amount,
                'created_at' => $order->created_at?->toIso8601String(),
                'updated_at' => $order->updated_at?->toIso8601String(),
            ])->values(),
        ]]);
    }

    public function customerOrder(Request $request, ServiceOrder $order): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->tokenCan('orders:read') && ! $user->isStaff(), 403);
        $customer = $user->customer;
        abort_unless($customer && $order->customer_id === $customer->id, 404);
        $order->load([
            'service:id,name',
            'files' => fn ($query) => $query->where('kind', 'result')->oldest(),
            'statusHistories' => fn ($query) => $query->oldest(),
        ]);

        return response()->json(['data' => [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'type' => $order->type,
            'service' => $order->service?->name,
            'customer_name' => $order->customer_name,
            'status' => ['code' => $order->status->value, 'label' => $order->status->label()],
            'specifications' => $order->specifications,
            'notes' => $order->notes,
            'estimated_price' => $order->estimated_price,
            'final_price' => $order->final_price,
            'paid_amount' => $order->paid_amount,
            'estimate_approved_at' => $order->estimate_approved_at?->toIso8601String(),
            'payment_confirmation_at' => $order->payment_confirmation_at?->toIso8601String(),
            'revision_requests' => $order->revision_requests,
            'max_revision_requests' => (int) config('services.orders.max_revision_requests', 3),
            'deadline_at' => $order->deadline_at?->toIso8601String(),
            'created_at' => $order->created_at?->toIso8601String(),
            'can_download_results' => $order->canReleaseResults(),
            'files' => $order->files->map(fn ($file): array => [
                'id' => $file->id,
                'name' => $file->original_name,
                'size' => $file->size,
            ])->values(),
            'history' => $order->statusHistories->map(fn ($history): array => [
                'status' => [
                    'code' => $history->to_status,
                    'label' => ServiceOrderStatus::tryFrom($history->to_status)?->label() ?? str($history->to_status)->replace('_', ' ')->title()->toString(),
                ],
                'notes' => $history->notes,
                'created_at' => $history->created_at?->toIso8601String(),
            ])->values(),
        ]]);
    }

    public function site(): JsonResponse
    {
        return response()->json([
            'data' => [
                'services' => Service::query()
                    ->where('is_active', true)
                    ->orderBy('type')
                    ->limit(12)
                    ->get(['id', 'name', 'type', 'unit', 'base_price', 'description']),
                'featured_products' => Product::query()
                    ->with('category:id,name')
                    ->where('is_active', true)
                    ->where('stock', '>', 0)
                    ->orderBy('name')
                    ->limit(8)
                    ->get(['id', 'category_id', 'name', 'unit', 'selling_price', 'stock']),
                'testimonials' => Testimonial::query()
                    ->published()
                    ->orderBy('display_order')
                    ->latest()
                    ->limit(6)
                    ->get(['id', 'customer_name', 'customer_role', 'quote', 'rating', 'source_label']),
                'store' => [
                    'address' => config('services.store.address'),
                    'open_hours' => config('services.store.open_hours'),
                    'maps_url' => config('services.store.maps_url'),
                    'whatsapp' => config('services.whatsapp.display_number'),
                ],
                'meta' => [
                    'title' => 'Younz Digital Center | Print, Desain, Website & Layanan Digital',
                    'description' => 'Pesan layanan print, fotokopi, scan, desain, website, aplikasi, ATK, dan top up di Younz Digital Center.',
                ],
            ],
        ]);
    }

    public function publicStoreOrder(ServiceOrderRequest $request, ServiceOrderController $orders, MidtransClient $midtrans): JsonResponse
    {
        $validated = $request->validated();
        $idempotencyKey = $validated['idempotency_key'] ?? null;
        $customerName = trim((string) ($validated['customer_name'] ?? $request->user()?->customer?->name ?? $request->user()?->name));
        $customerPhone = preg_replace('/\D+/', '', (string) ($validated['customer_phone'] ?? $request->user()?->customer?->phone ?? $request->user()?->phone));
        $fingerprint = $idempotencyKey ? hash('sha256', json_encode([
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
            'product_id' => (int) $validated['product_id'],
            ...(isset($validated['variant_id']) ? ['variant_id' => (int) $validated['variant_id']] : []),
            'quantity' => (int) $validated['quantity'],
            'notes' => trim((string) ($validated['notes'] ?? '')),
        ], JSON_THROW_ON_ERROR)) : null;
        $order = $idempotencyKey
            ? ServiceOrder::query()->where('checkout_idempotency_key', $idempotencyKey)->first()
            : null;
        $created = $order === null;

        if ($order && ! hash_equals((string) $order->checkout_request_fingerprint, (string) $fingerprint)) {
            return response()->json(['message' => 'Kunci idempotensi sudah digunakan untuk pesanan berbeda.'], 409);
        }

        if (! $order) {
            try {
                $order = $orders->createOrder($request, $fingerprint, true);
            } catch (QueryException $exception) {
                $order = $idempotencyKey
                    ? ServiceOrder::query()->where('checkout_idempotency_key', $idempotencyKey)->first()
                    : null;
                if (! $order || ! hash_equals((string) $order->checkout_request_fingerprint, (string) $fingerprint)) {
                    throw $exception;
                }
                $created = false;
            }

        }

        if ($order->estimated_price && ! $order->midtrans_redirect_url) {
            $claim = DB::transaction(function () use ($order): ?string {
                $locked = ServiceOrder::query()->lockForUpdate()->findOrFail($order->id);
                if ($locked->midtrans_redirect_url || ($locked->payment_expires_at && $locked->payment_expires_at->isPast())) {
                    return null;
                }
                $leaseActive = in_array($locked->snap_creation_state, ['creating', 'reconciling'], true)
                    && $locked->snap_creation_started_at
                    && $locked->snap_creation_started_at->isAfter(now()->subMinutes(2));
                if ($leaseActive) {
                    return null;
                }
                $reconcile = in_array($locked->snap_creation_state, ['failed', 'creating', 'reconciling', 'reconciled'], true);
                $locked->update([
                    'snap_creation_state' => $reconcile ? 'reconciling' : 'creating',
                    'snap_creation_started_at' => now(),
                ]);

                return $reconcile ? 'reconcile' : 'create';
            });

            if ($claim === 'reconcile') {
                try {
                    $status = $midtrans->getServiceOrderTransactionStatus($order->fresh());
                    if ($status === null) {
                        $claim = 'create';
                        $order->update(['snap_creation_state' => 'creating', 'snap_creation_started_at' => now()]);
                    } else {
                        app(ApplyServiceOrderMidtransStatus::class)->handle($order, $status);
                        $redirectUrl = filled($status['redirect_url'] ?? null) ? (string) $status['redirect_url'] : null;
                        $order->update([
                            'midtrans_redirect_url' => $redirectUrl,
                            'snap_creation_state' => $redirectUrl ? 'ready' : 'reconciled',
                        ]);
                        $claim = null;
                    }
                } catch (\Throwable $exception) {
                    report($exception);
                    $order->update(['snap_creation_state' => 'failed']);
                    $claim = null;
                }
            }

            if ($claim === 'create') {
                try {
                    $snap = $midtrans->createServiceOrderSnapTransaction($order->fresh());
                    $order->update([
                        'midtrans_snap_token' => $snap['token'],
                        'midtrans_redirect_url' => $snap['redirect_url'],
                        'snap_creation_state' => 'ready',
                    ]);
                } catch (\Throwable $exception) {
                    report($exception);
                    $order->update(['snap_creation_state' => 'failed']);
                }
            }

            $order->refresh();
            if (! $order->midtrans_redirect_url && $order->payment_status !== 'paid') {
                return response()->json([
                    'message' => $order->snap_creation_state === 'creating'
                        ? 'Pesanan tersimpan dan pembayaran sedang disiapkan. Pantau status pesanan.'
                        : 'Pesanan tersimpan, tetapi halaman pembayaran belum tersedia. Hubungi operator dari halaman pelacakan.',
                    'data' => $this->publicOrderData($order),
                    'tracking_token' => $order->public_token,
                    'payment' => $this->publicPaymentData($order),
                    'partial_success' => true,
                ], $order->snap_creation_state === 'creating' ? 202 : 502);
            }
        }

        return response()->json([
            'message' => $order->payment_status === 'paid'
                ? 'Pembayaran terverifikasi. Pesanan sedang diproses.'
                : ($order->estimated_price
                    ? 'Pesanan dibuat. Lanjutkan ke pembayaran Midtrans.'
                    : 'Pesanan diterima dan akan diperiksa operator.'),
            'data' => $this->publicOrderData($order),
            'tracking_token' => $order->public_token,
            'payment' => $this->publicPaymentData($order),
        ], $created ? 201 : 200);
    }

    public function customerStoreDigitalProductOrder(ServiceOrderRequest $request, ServiceOrderController $orders, MidtransClient $midtrans): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->tokenCan('orders:write') && ! $user->isStaff() && $user->customer, 403);
        if ($request->validated('type') !== 'digital') {
            throw ValidationException::withMessages([
                'type' => 'Endpoint ini hanya menerima checkout produk digital.',
            ]);
        }

        return $this->publicStoreOrder($request, $orders, $midtrans);
    }

    public function customerDigitalProductOrderStatus(Request $request, ServiceOrder $order): JsonResponse
    {
        $this->authorizeCustomerDigitalProductOrder($request, $order);

        return response()->json(['data' => $this->digitalProductPaymentStatusData($order->fresh())]);
    }

    public function refreshCustomerDigitalProductOrderPayment(
        Request $request,
        ServiceOrder $order,
        MidtransClient $midtrans,
        ApplyServiceOrderMidtransStatus $applyStatus,
    ): JsonResponse {
        $this->authorizeCustomerDigitalProductOrder($request, $order);

        if ($order->estimated_price && $order->midtrans_order_id && ! in_array($order->payment_status, ['paid', 'refunded'], true)) {
            $payload = $midtrans->getServiceOrderTransactionStatus($order);
            if ($payload !== null) {
                $applyStatus->handle($order, $payload);
            }
        }

        return response()->json(['data' => $this->digitalProductPaymentStatusData($order->fresh())]);
    }

    /** @return array{required: bool, status: string, amount: int|null, redirect_url: string|null} */
    private function publicPaymentData(ServiceOrder $order): array
    {
        return [
            'required' => $order->estimated_price !== null,
            'status' => (string) ($order->payment_status ?? 'unpaid'),
            'amount' => $order->estimated_price,
            'redirect_url' => $order->midtrans_redirect_url,
        ];
    }

    public function publicTrackOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_number' => ['required', 'string', 'max:30'],
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $order = ServiceOrder::query()
            ->where('order_number', mb_strtoupper(trim($data['order_number'])))
            ->where('customer_phone', preg_replace('/\D+/', '', $data['phone']))
            ->with('service:id,name')
            ->first();

        if (! $order) {
            return response()->json(['message' => 'Pesanan tidak ditemukan. Periksa nomor pesanan dan nomor WhatsApp.'], 404);
        }

        return response()->json(['data' => $this->publicOrderData($order)]);
    }

    public function publicOrder(string $token): JsonResponse
    {
        $order = ServiceOrder::query()
            ->where('public_token', $token)
            ->with('service:id,name')
            ->firstOrFail();

        return response()->json(['data' => $this->publicOrderData($order)]);
    }

    /** @return array<string, mixed> */
    private function publicOrderData(ServiceOrder $order): array
    {
        $order->loadMissing('service:id,name');

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'type' => $order->type,
            'service' => $order->service?->name,
            'status' => [
                'code' => $order->status->value,
                'label' => $order->status->label(),
            ],
            'estimated_price' => $order->estimated_price,
            'final_price' => $order->final_price,
            'paid_amount' => $order->paid_amount,
            'payment_status' => (string) ($order->payment_status ?? 'unpaid'),
            'deadline_at' => $order->deadline_at?->toIso8601String(),
            'pickup_at' => $order->pickup_at?->toIso8601String(),
            'created_at' => $order->created_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
        ];
    }

    private function authorizeCustomerDigitalProductOrder(Request $request, ServiceOrder $order): void
    {
        $user = $request->user();
        abort_unless($user->tokenCan('orders:read') && ! $user->isStaff(), 403);
        abort_unless($user->customer && $order->customer_id === $user->customer->id && $order->type === 'digital', 404);
    }

    /** @return array<string, mixed> */
    private function digitalProductPaymentStatusData(ServiceOrder $order): array
    {
        $paymentStatus = (string) ($order->payment_status ?? 'unpaid');
        $terminal = in_array($paymentStatus, ['paid', 'expired', 'cancelled', 'failed', 'refunded'], true);
        $specifications = $order->specifications ?? [];

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'product_name' => (string) ($specifications['digital_product_name'] ?? 'Produk Digital'),
            'variant_label' => $specifications['digital_product_variant_label'] ?? null,
            'quantity' => max(1, (int) ($specifications['quantity'] ?? 1)),
            'payment_status' => $paymentStatus,
            'payment_terminal' => $terminal,
            'amount' => $order->estimated_price,
            'paid_amount' => $order->paid_amount,
            'redirect_url' => $terminal ? null : $order->midtrans_redirect_url,
            'order_status' => [
                'code' => $order->status->value,
                'label' => $order->status->label(),
            ],
            'updated_at' => $order->updated_at?->toIso8601String(),
        ];
    }

    public function products(Request $request): JsonResponse
    {
        $products = Product::query()->with('category')->where('is_active', true)
            ->when($request->string('q')->toString(), fn ($query, string $q) => $query->where(fn ($query) => $query->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%")))
            ->paginate(20);

        return response()->json($products);
    }

    public function services(): JsonResponse
    {
        return response()->json(['data' => Service::query()->where('is_active', true)->orderBy('name')->get()]);
    }

    public function storeOrder(ServiceOrderRequest $request, ServiceOrderController $orders): JsonResponse
    {
        abort_unless($request->user()->tokenCan('orders:write'), 403);
        if ($request->validated('type') === 'digital') {
            throw ValidationException::withMessages([
                'type' => 'Produk digital harus dipesan melalui checkout Produk Digital.',
            ]);
        }
        $order = $orders->createOrder($request);

        return response()->json(['data' => $order, 'tracking_token' => $order->public_token], 201);
    }

    public function orders(Request $request): JsonResponse
    {
        abort_unless($request->user()->tokenCan('orders:read'), 403);
        abort_if($request->user()->isStaff(), 403, 'Gunakan endpoint pesanan staf sesuai cakupan akses Anda.');
        $customerId = $request->user()->customer?->id;
        abort_unless($customerId, 403);
        $orders = ServiceOrder::query()
            ->where('customer_id', $customerId)
            ->latest()->paginate(20);

        return response()->json($orders);
    }

    public function order(Request $request, ServiceOrder $order): JsonResponse
    {
        abort_unless($request->user()->tokenCan('orders:read'), 403);
        abort_if($request->user()->isStaff(), 403, 'Gunakan endpoint pesanan staf sesuai cakupan akses Anda.');
        abort_unless($order->customer_id === $request->user()->customer?->id, 403);

        return response()->json(['data' => $order->load(['service', 'statusHistories'])]);
    }
}
