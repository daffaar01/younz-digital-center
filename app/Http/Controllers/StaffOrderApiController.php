<?php

namespace App\Http\Controllers;

use App\Enums\ServiceOrderStatus;
use App\Actions\Services\TransitionServiceOrder;
use App\Enums\UserRole;
use App\Models\ServiceFile;
use App\Models\ServiceOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffOrderApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isStaff() && $user->tokenCan('orders:read'), 403);

        $orders = ServiceOrder::query()->with(['service:id,name', 'assignee:id,name'])->withCount('files');
        if (! $user->hasRole(UserRole::Owner, UserRole::Admin)) {
            $user->role === UserRole::Cashier
                ? $orders->where('created_by', $user->id)
                : $orders->where('assigned_to', $user->id);
        }
        if ($status = ServiceOrderStatus::tryFrom($request->string('status')->toString())) {
            $orders->where('status', $status->value);
        }
        if ($search = trim($request->string('q')->toString())) {
            $orders->where(fn ($query) => $query
                ->where('order_number', 'like', "%{$search}%")
                ->orWhere('customer_name', 'like', "%{$search}%")
                ->orWhere('customer_phone', 'like', "%{$search}%"));
        }
        $paginator = $orders->latest()->paginate(20)->withQueryString();

        return response()->json(['data' => [
            'orders' => collect($paginator->items())->map(fn (ServiceOrder $order): array => $this->summary($order))->values(),
            'statuses' => collect(ServiceOrderStatus::cases())->map(fn (ServiceOrderStatus $status): array => [
                'code' => $status->value,
                'label' => $status->label(),
            ])->values(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]]);
    }

    public function show(Request $request, ServiceOrder $order): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isStaff() && $user->tokenCan('orders:read'), 403);
        Gate::authorize('view', $order);

        $canAccessFiles = Gate::allows('viewFiles', $order);
        $relations = ['service:id,name', 'assignee:id,name', 'statusHistories.user:id,name'];
        if ($canAccessFiles) {
            $relations[] = 'files';
        }
        $order->load($relations);

        return response()->json(['data' => [
            ...$this->summary($order),
            'customer_phone' => $order->customer_phone,
            'notes' => $order->notes,
            'specifications' => $order->specifications,
            'estimated_price' => $order->estimated_price,
            'final_price' => $order->final_price,
            'paid_amount' => $order->paid_amount,
            'payment_confirmation' => $order->payment_confirmation_at ? [
                'at' => $order->payment_confirmation_at->toIso8601String(),
                'method' => $order->payment_confirmation_method,
                'reference' => $order->payment_confirmation_reference,
            ] : null,
            'permissions' => [
                'manage_financials' => Gate::allows('manageFinancials', $order),
                'update_status' => Gate::allows('updateStatus', $order),
                'view_files' => $canAccessFiles,
                'upload_result' => Gate::allows('uploadResult', $order),
            ],
            'allowed_transitions' => collect($order->status->allowedTransitions())
                ->filter(fn (ServiceOrderStatus $status): bool => $user->role !== UserRole::Cashier
                    || in_array($status, [ServiceOrderStatus::AwaitingOperator, ServiceOrderStatus::Cancelled], true))
                ->map(fn (ServiceOrderStatus $status): array => ['code' => $status->value, 'label' => $status->label()])
                ->values(),
            'files' => $canAccessFiles ? $order->files->map(fn ($file): array => [
                'id' => $file->id,
                'name' => $file->original_name,
                'mime_type' => $file->mime_type,
                'size' => $file->size,
                'kind' => $file->kind,
            ])->values() : [],
            'history' => $order->statusHistories->sortByDesc('created_at')->map(fn ($history): array => [
                'status' => [
                    'code' => $history->to_status,
                    'label' => ServiceOrderStatus::tryFrom($history->to_status)?->label() ?? str($history->to_status)->headline()->toString(),
                ],
                'notes' => $history->notes,
                'actor' => $history->user?->name ?? 'Sistem/pelanggan',
                'created_at' => $history->created_at?->toIso8601String(),
            ])->values(),
        ]]);
    }

    public function updateStatus(Request $request, ServiceOrder $order, TransitionServiceOrder $transition): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->tokenCan('orders:write'), 403);
        Gate::authorize('updateStatus', $order);
        $data = $request->validate(['status' => ['required', 'string'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $next = ServiceOrderStatus::tryFrom($data['status']);
        if (! $next) {
            throw ValidationException::withMessages(['status' => 'Status tidak valid.']);
        }
        if ($user->role === UserRole::Cashier && ! in_array($next, [ServiceOrderStatus::AwaitingOperator, ServiceOrderStatus::Cancelled], true)) {
            abort(403, 'Kasir hanya dapat meneruskan pesanan ke operator atau membatalkannya.');
        }
        $transition->handle($order, $next, $user, $data['notes'] ?? null);

        return response()->json(['message' => 'Status pesanan berhasil diperbarui.']);
    }

    public function download(Request $request, ServiceOrder $order, ServiceFile $file): StreamedResponse
    {
        abort_unless($request->user()->tokenCan('orders:read'), 403);
        Gate::authorize('viewFiles', $order);
        abort_unless($file->service_order_id === $order->id, 404);

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    private function summary(ServiceOrder $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'customer_name' => $order->customer_name,
            'type' => $order->type,
            'service' => $order->service?->name,
            'assignee' => $order->assignee?->name,
            'status' => ['code' => $order->status->value, 'label' => $order->status->label()],
            'files_count' => (int) ($order->files_count ?? $order->files?->count() ?? 0),
            'deadline_at' => $order->deadline_at?->toIso8601String(),
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }
}