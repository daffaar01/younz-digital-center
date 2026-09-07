<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\DigitalTransaction;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Refund;
use App\Models\Sale;
use App\Models\ServiceOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffDashboardApiController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isStaff() && $user->tokenCan('staff:dashboard'), 403);

        $start = now()->startOfDay();
        $end = now()->endOfDay();
        $saleStatuses = ['completed', 'partially_refunded', 'refunded'];
        $grossSales = Sale::query()->whereIn('status', $saleStatuses)->whereBetween('completed_at', [$start, $end])->sum('total');
        $refunds = Refund::query()->where('status', 'completed')->whereBetween('processed_at', [$start, $end])->sum('amount');
        $canManage = $user->hasRole('owner', 'admin');
        $canOperateSales = $user->hasRole('owner', 'admin', 'cashier');

        return response()->json(['data' => [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => ['code' => $user->role->value, 'label' => $user->role->label()],
            ],
            'permissions' => [
                'manage' => $canManage,
                'operate_sales' => $canOperateSales,
            ],
            'summary' => [
                'today_revenue' => (int) $grossSales - (int) $refunds,
                'today_expenses' => (int) Expense::query()->whereDate('expense_date', today())->sum('amount'),
                'today_transactions' => Sale::query()->whereIn('status', $saleStatuses)->whereBetween('completed_at', [$start, $end])->count(),
                'active_orders' => ServiceOrder::query()->whereNotIn('status', ['selesai', 'dibatalkan'])->count(),
                'late_orders' => ServiceOrder::query()->whereNotIn('status', ['selesai', 'dibatalkan'])->where('deadline_at', '<', now())->count(),
                'failed_digital_transactions' => DigitalTransaction::query()->where('status', 'gagal')->whereDate('created_at', today())->count(),
                'pending_approvals' => $canManage ? ApprovalRequest::query()->where('status', 'pending')->count() : 0,
            ],
            'low_stock_products' => Product::query()
                ->whereColumn('stock', '<=', 'minimum_stock')
                ->where('is_active', true)
                ->orderBy('stock')
                ->limit(8)
                ->get(['id', 'name', 'sku', 'stock', 'minimum_stock']),
            'recent_sales' => Sale::query()
                ->with('user:id,name')
                ->whereIn('status', $saleStatuses)
                ->latest('completed_at')
                ->limit(8)
                ->get(['id', 'invoice_number', 'user_id', 'status', 'total', 'completed_at'])
                ->map(fn (Sale $sale): array => [
                    'id' => $sale->id,
                    'invoice_number' => $sale->invoice_number,
                    'cashier' => $sale->user?->name,
                    'status' => $sale->status,
                    'total' => $sale->total,
                    'completed_at' => $sale->completed_at?->toIso8601String(),
                ])->values(),
        ]]);
    }
}