<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $sales = Sale::query()
            ->with(['user', 'customer'])
            ->withSum(['refunds as refunded_amount' => fn ($query) => $query->where('status', 'completed')], 'amount')
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($query) => $query->where('name', 'like', "%{$search}%"));
            }))
            ->latest('completed_at')
            ->paginate(20)
            ->withQueryString();

        return view('sales.index', compact('sales', 'search'));
    }

    public function show(Sale $sale): View
    {
        $sale->load([
            'items.refundItems.refund',
            'payments',
            'customer',
            'user',
            'refunds' => fn ($query) => $query->with(['requester', 'approver', 'approvalRequest'])->latest(),
        ]);

        return view('sales.show', compact('sale'));
    }

    public function receipt(Sale $sale): View
    {
        $sale->load(['items', 'payments', 'customer', 'user', 'refunds' => fn ($query) => $query->where('status', 'completed')]);
        $this->audit->log('receipt.viewed', $sale);

        return view('sales.receipt', compact('sale'));
    }
}
