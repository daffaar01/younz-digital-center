<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\DigitalTransaction;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Refund;
use App\Models\Sale;
use App\Models\ServiceOrder;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $start = now()->startOfDay();
        $end = now()->endOfDay();
        $saleStatuses = ['completed', 'partially_refunded', 'refunded'];
        $grossSales = Sale::query()->whereIn('status', $saleStatuses)->whereBetween('completed_at', [$start, $end])->sum('total');
        $refunds = Refund::query()->where('status', 'completed')->whereBetween('processed_at', [$start, $end])->sum('amount');

        return view('dashboard', [
            'todayRevenue' => $grossSales - $refunds,
            'todayExpenses' => Expense::query()->whereDate('expense_date', today())->sum('amount'),
            'todayTransactions' => Sale::query()->whereIn('status', $saleStatuses)->whereBetween('completed_at', [$start, $end])->count(),
            'activeOrders' => ServiceOrder::query()->whereNotIn('status', ['selesai', 'dibatalkan'])->count(),
            'lateOrders' => ServiceOrder::query()->whereNotIn('status', ['selesai', 'dibatalkan'])->where('deadline_at', '<', now())->count(),
            'lowStockProducts' => Product::query()->whereColumn('stock', '<=', 'minimum_stock')->where('is_active', true)->orderBy('stock')->limit(8)->get(),
            'failedDigitalTransactions' => DigitalTransaction::query()->where('status', 'gagal')->whereDate('created_at', today())->count(),
            'recentSales' => Sale::query()
                ->with('user')
                ->whereIn('status', $saleStatuses)
                ->latest('completed_at')
                ->limit(8)
                ->get(),
            'pendingApprovals' => auth()->user()->hasRole('owner', 'admin')
                ? ApprovalRequest::query()->where('status', 'pending')->count()
                : 0,
        ]);
    }
}
