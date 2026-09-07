<?php

namespace App\Http\Controllers;

use App\Models\DigitalTransaction;
use App\Models\Expense;
use App\Models\Refund;
use App\Models\Sale;
use App\Models\ServiceOrder;
use App\Models\TopupOrder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function daily(Request $request): View
    {
        $date = $request->date('date') ?? today();
        $sales = Sale::query()->whereIn('status', ['completed', 'partially_refunded', 'refunded'])->whereDate('completed_at', $date)->with(['items', 'payments', 'user'])->get();
        $expenses = Expense::query()->whereDate('expense_date', $date)->with('category')->get();
        $digital = DigitalTransaction::query()->where('status', 'berhasil')->whereDate('created_at', $date)->get();
        $topupOrders = TopupOrder::query()->where('payment_status', 'paid')->where('fulfillment_status', 'success')->whereDate('fulfilled_at', $date)->get();
        $refunds = Refund::query()->where('status', 'completed')->whereDate('processed_at', $date)->with(['sale', 'items.saleItem', 'approver'])->get();
        $grossProfit = $sales->sum(fn (Sale $sale) => $sale->total - $sale->items->sum(fn ($item) => $item->cost_price * $item->quantity));
        $digitalProfit = $digital->sum(fn (DigitalTransaction $transaction) => $transaction->profit)
            + $topupOrders->sum(fn (TopupOrder $order) => $order->total_amount - $order->cost_price);
        $refundProfitReduction = $refunds->sum(fn (Refund $refund) => $refund->amount - $refund->items
            ->where('restore_stock', true)
            ->sum(fn ($item) => $item->saleItem->cost_price * $item->quantity));
        $grossRevenue = $sales->sum('total') + $digital->sum('selling_price') + $digital->sum('admin_fee') + $topupOrders->sum('total_amount');
        $refundsTotal = $refunds->sum('amount');

        return view('reports.daily', [
            'date' => $date,
            'sales' => $sales,
            'expenses' => $expenses,
            'digitalTransactions' => $digital,
            'topupOrders' => $topupOrders,
            'refunds' => $refunds,
            'grossRevenue' => $grossRevenue,
            'refundsTotal' => $refundsTotal,
            'revenue' => $grossRevenue - $refundsTotal,
            'expensesTotal' => $expenses->sum('amount'),
            'grossProfit' => $grossProfit + $digitalProfit - $refundProfitReduction,
            'completedOrders' => ServiceOrder::query()->where('status', 'selesai')->whereDate('updated_at', $date)->count(),
        ]);
    }
}
