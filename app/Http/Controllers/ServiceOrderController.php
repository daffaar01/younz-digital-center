<?php

namespace App\Http\Controllers;

use App\Actions\Services\TransitionServiceOrder;
use App\Enums\ServiceOrderStatus;
use App\Jobs\SendDigitalProductOrderWhatsAppNotification;
use App\Enums\UserRole;
use App\Http\Requests\ServiceOrderRequest;
use App\Jobs\SendServiceOrderDiscordNotification;
use App\Models\DigitalProduct;
use App\Models\DigitalProductVariant;
use App\Models\Service;
use App\Models\ServiceFile;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\DocumentNumberGenerator;
use App\Support\FileSecurityScanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ServiceOrderController extends Controller
{
    public function __construct(
        private readonly DocumentNumberGenerator $numbers,
        private readonly AuditLogger $audit,
        private readonly FileSecurityScanner $fileScanner,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $orders = ServiceOrder::query()->with(['customer', 'service', 'assignee'])->withCount('files');
        if (! $user->hasRole(UserRole::Owner, UserRole::Admin)) {
            $user->role === UserRole::Cashier
                ? $orders->where('created_by', $user->id)
                : $orders->where('assigned_to', $user->id);
        }

        return view('orders.index', [
            'orders' => $orders->latest()->paginate(20),
            'services' => Service::query()->where('is_active', true)->orderBy('name')->get(),
            'statuses' => ServiceOrderStatus::cases(),
        ]);
    }

    public function store(ServiceOrderRequest $request): RedirectResponse
    {
        $order = $this->createOrder($request);

        return redirect()->route('orders.show', $order)->with('status', 'Pesanan berhasil dibuat.');
    }

    public function show(Request $request, ServiceOrder $order): View
    {
        Gate::authorize('view', $order);
        $canAccessFiles = $request->user()->can('viewFiles', $order);
        $relations = ['customer', 'service', 'assignee', 'statusHistories.user'];
        if ($canAccessFiles) {
            $relations[] = 'files';
        }

        return view('orders.show', [
            'order' => $order->load($relations),
            'statuses' => ServiceOrderStatus::cases(),
            'canAccessFiles' => $canAccessFiles,
            'assignees' => $request->user()->can('manageFinancials', $order)
                ? User::query()->where('is_active', true)->whereIn('role', [
                    UserRole::PrintOperator->value,
                    UserRole::Designer->value,
                    UserRole::Developer->value,
                ])->orderBy('name')->get()
                : collect(),
        ]);
    }

    public function updateStatus(Request $request, ServiceOrder $order, TransitionServiceOrder $transition): RedirectResponse
    {
        Gate::authorize('updateStatus', $order);
        $data = $request->validate([
            'status' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $next = ServiceOrderStatus::tryFrom($data['status']);
        if ($next === null) {
            abort(422, 'Status tidak valid.');
        }
        if ($request->user()->role === UserRole::Cashier
            && ! in_array($next, [ServiceOrderStatus::AwaitingOperator, ServiceOrderStatus::Cancelled], true)
        ) {
            abort(403, 'Kasir hanya dapat meneruskan pesanan sederhana ke operator atau membatalkannya.');
        }
        $transition->handle($order, $next, $request->user(), $data['notes'] ?? null);

        return back()->with('status', 'Status pesanan berhasil diperbarui.');
    }

    public function updateDetails(Request $request, ServiceOrder $order): RedirectResponse
    {
        Gate::authorize('manageFinancials', $order);
        $data = $request->validate([
            'estimated_price' => ['nullable', 'integer', 'min:0'],
            'final_price' => ['nullable', 'integer', 'min:0'],
            'paid_amount' => ['required', 'integer', 'min:0'],
            'deadline_at' => ['nullable', 'date'],
            'assigned_to' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->whereIn('role', [
                        UserRole::PrintOperator->value,
                        UserRole::Designer->value,
                        UserRole::Developer->value,
                    ])),
            ],
        ]);
        $payable = $data['final_price'] ?? $data['estimated_price'] ?? $order->payableAmount();
        if ($payable !== null && $data['paid_amount'] > $payable) {
            throw ValidationException::withMessages(['paid_amount' => 'Nominal terbayar tidak boleh melebihi tagihan.']);
        }
        if (! empty($data['assigned_to'])) {
            $assignee = User::query()->findOrFail($data['assigned_to']);
            if (! $this->canHandleType($assignee, $order->type)) {
                throw ValidationException::withMessages(['assigned_to' => 'Role pegawai tidak sesuai dengan jenis pesanan.']);
            }
        }

        // An authorized manual payment update must also drive the release status.
        // Online payments and refunds retain their independently verified status.
        if ($order->type !== 'digital' && blank($order->midtrans_order_id)
            && ! in_array($order->payment_status, ['refunded', 'partial_refunded'], true)
        ) {
            $data['payment_status'] = $payable !== null && $data['paid_amount'] >= $payable ? 'paid' : 'unpaid';
        }

        $before = $order->only(array_keys($data));
        $order->update($data);
        $this->audit->log('service_order.details_updated', $order, $before, $order->fresh()->only(array_keys($data)));

        return back()->with('status', 'Harga, pembayaran, dan deadline berhasil diperbarui.');
    }

    public function uploadResult(Request $request, ServiceOrder $order): RedirectResponse
    {
        Gate::authorize('uploadResult', $order);
        $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,txt,zip'],
        ]);
        $uploaded = $request->file('file');
        $scan = $this->fileScanner->scan($uploaded);
        $storedPath = $uploaded->store("service-orders/{$order->id}/results", 'local');

        if (! is_string($storedPath)) {
            throw ValidationException::withMessages(['file' => 'File hasil tidak dapat disimpan.']);
        }

        try {
            $file = $order->files()->create([
                'uploaded_by' => $request->user()->id,
                'disk' => 'local',
                'path' => $storedPath,
                'original_name' => $uploaded->getClientOriginalName(),
                'mime_type' => $uploaded->getMimeType() ?: 'application/octet-stream',
                'size' => $uploaded->getSize(),
                'kind' => 'result',
                'scan_status' => $scan['status'],
                'scanned_at' => $scan['scanned_at'],
                'expires_at' => now()->addDays((int) config('services.file_security.retention_days', 90)),
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPath);
            throw $exception;
        }

        $this->audit->log('service_file.result_uploaded', $file, metadata: ['order_id' => $order->id]);

        return back()->with('status', 'File hasil berhasil diunggah secara privat.');
    }

    public function download(ServiceOrder $order, ServiceFile $file): StreamedResponse
    {
        Gate::authorize('viewFiles', $order);
        abort_unless($file->service_order_id === $order->id, 404);
        $this->audit->log('service_file.downloaded', $file, metadata: ['order_id' => $order->id]);

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    public function createOrder(ServiceOrderRequest $request, ?string $checkoutFingerprint = null, bool $initializeDigitalPayment = false): ServiceOrder
    {
        if ($request->validated('type') === 'digital' && ! $initializeDigitalPayment) {
            throw ValidationException::withMessages([
                'type' => 'Produk digital harus dipesan melalui checkout Produk Digital.',
            ]);
        }

        $storedPath = null;

        try {
            $order = DB::transaction(function () use ($request, &$storedPath, $checkoutFingerprint, $initializeDigitalPayment) {
                $data = $request->safe()->except('file');
                $idempotencyKey = $data['idempotency_key'] ?? null;
                unset($data['idempotency_key']);
                $user = $request->user();

                if (! $user?->isStaff()) {
                    unset($data['estimated_price'], $data['deadline_at']);
                    $data['customer_id'] = $user?->customer?->id;
                    $data['customer_name'] = $user?->customer?->name ?: $user?->name ?: $data['customer_name'];
                    $data['customer_phone'] = $user?->customer?->phone ?: $user?->phone ?: $data['customer_phone'];
                }

                $data['customer_phone'] = preg_replace('/\D+/', '', (string) $data['customer_phone']);

                if (! empty($data['service_id'])) {
                    $data['type'] = Service::query()->where('is_active', true)->findOrFail($data['service_id'])->type;
                }

                if ($data['type'] === 'digital') {
                    $product = DigitalProduct::query()
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->find($data['product_id']);
                    if (! $product) {
                        throw ValidationException::withMessages(['product_id' => 'Produk digital tidak tersedia.']);
                    }
                    $quantity = (int) $data['quantity'];
                    $hasActiveVariants = $product->variants()->where('is_active', true)->exists();
                    $variant = null;
                    if ($hasActiveVariants) {
                        if (empty($data['variant_id'])) {
                            throw ValidationException::withMessages(['variant_id' => 'Pilih durasi produk terlebih dahulu.']);
                        }
                        $variant = DigitalProductVariant::query()
                            ->where('digital_product_id', $product->id)
                            ->where('is_active', true)
                            ->lockForUpdate()
                            ->find($data['variant_id']);
                        if (! $variant) {
                            throw ValidationException::withMessages(['variant_id' => 'Pilihan durasi tidak tersedia untuk produk ini.']);
                        }
                    } elseif (! empty($data['variant_id'])) {
                        throw ValidationException::withMessages(['variant_id' => 'Produk ini tidak memiliki pilihan durasi aktif.']);
                    }

                    $stockOwner = $variant ?: $product;
                    $unitPrice = $variant ? $variant->price : $product->price;
                    if ($stockOwner->stock !== null && $quantity > $stockOwner->stock) {
                        throw ValidationException::withMessages(['quantity' => 'Kuantitas melebihi stok pilihan yang tersedia.']);
                    }
                    $stockReserved = $stockOwner->stock !== null && is_int($unitPrice) && $unitPrice > 0;
                    if ($stockReserved) {
                        $stockOwner->decrement('stock', $quantity);
                    }
                    unset($data['product_id'], $data['variant_id'], $data['quantity']);
                    $data['specifications'] = [
                        ...($data['specifications'] ?? []),
                        'digital_product_id' => $product->id,
                        'digital_product_name' => $product->name,
                        'digital_product_variant_id' => $variant?->id,
                        'digital_product_variant_label' => $variant?->label,
                        'quantity' => $quantity,
                        'unit_price_snapshot' => $unitPrice,
                        'stock_reserved' => $stockReserved,
                        'stock_released' => false,
                    ];
                    $data['checkout_idempotency_key'] = $idempotencyKey;
                    $data['checkout_request_fingerprint'] = $checkoutFingerprint;
                    if ($initializeDigitalPayment) {
                        $amount = is_int($unitPrice) && $unitPrice > 0 ? $unitPrice * $quantity : null;
                        $data['estimated_price'] = $amount;
                        $data['payment_status'] = $amount ? 'pending' : 'unpaid';
                        $data['payment_expires_at'] = $amount
                            ? now()->addMinutes(max(5, min(1440, (int) config('services.midtrans.expiry_minutes', 60))))
                            : null;
                    }
                }

                $source = $data['source'] ?? null;
                unset($data['source']);
                if ($source) {
                    $data['specifications'] = [
                        ...($data['specifications'] ?? []),
                        'acquisition_source' => $source,
                    ];
                }

                if ($user && $this->isOperationalRole($user)) {
                    abort_unless($this->canHandleType($user, $data['type']), 403, 'Role Anda tidak sesuai dengan jenis pesanan ini.');
                    $data['assigned_to'] = $user->id;
                }

                $orderNumber = $this->numbers->next('ORD');
                if ($initializeDigitalPayment && ! empty($data['estimated_price'])) {
                    $data['midtrans_order_id'] = 'SVC-'.$orderNumber;
                }
                $initialStatus = $initializeDigitalPayment && ! empty($data['estimated_price'])
                    ? ServiceOrderStatus::AwaitingPayment
                    : ServiceOrderStatus::AwaitingReview;
                $order = ServiceOrder::create([
                    ...$data,
                    'order_number' => $orderNumber,
                    'public_token' => (string) Str::uuid(),
                    'created_by' => $user?->id,
                    'status' => $initialStatus,
                ]);

                $order->statusHistories()->create([
                    'user_id' => $request->user()?->id,
                    'to_status' => $initialStatus->value,
                    'notes' => $initialStatus === ServiceOrderStatus::AwaitingPayment
                        ? 'Pesanan dibuat dan menunggu pembayaran Midtrans.'
                        : 'Pesanan dibuat.',
                ]);

                if ($request->hasFile('file')) {
                    $uploaded = $request->file('file');
                    $scan = $this->fileScanner->scan($uploaded);
                    $storedPath = $uploaded->store("service-orders/{$order->id}", 'local');
                    if (! is_string($storedPath)) {
                        throw new \RuntimeException('File pesanan tidak dapat disimpan.');
                    }
                    $order->files()->create([
                        'uploaded_by' => $user?->id,
                        'disk' => 'local',
                        'path' => $storedPath,
                        'original_name' => $uploaded->getClientOriginalName(),
                        'mime_type' => $uploaded->getMimeType() ?: 'application/octet-stream',
                        'size' => $uploaded->getSize(),
                        'kind' => 'source',
                        'scan_status' => $scan['status'],
                        'scanned_at' => $scan['scanned_at'],
                        'expires_at' => now()->addDays((int) config('services.file_security.retention_days', 90)),
                    ]);
                }

                $this->audit->log('service_order.created', $order, after: $order->toArray());

                return $order;
            }, 3);
        } catch (Throwable $exception) {
            if (is_string($storedPath)) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        }

        SendServiceOrderDiscordNotification::dispatch(
            orderId: $order->id,
            event: 'order.created',
        );
        if ($order->type === 'digital') {
            SendDigitalProductOrderWhatsAppNotification::dispatch($order->id);
        }

        return $order;
    }

    private function isOperationalRole(User $user): bool
    {
        return $user->hasRole(UserRole::PrintOperator, UserRole::Designer, UserRole::Developer);
    }

    private function canHandleType(User $user, string $type): bool
    {
        return match ($user->role) {
            UserRole::PrintOperator => in_array($type, ['print', 'fotokopi', 'scan', 'ketik'], true),
            UserRole::Designer => $type === 'desain',
            UserRole::Developer => in_array($type, ['website', 'aplikasi'], true),
            default => false,
        };
    }
}
