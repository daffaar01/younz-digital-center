<?php

namespace App\Http\Controllers;

use App\Actions\Approvals\RequestSaleRefund;
use App\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RefundController extends Controller
{
    public function store(Request $request, Sale $sale, RequestSaleRefund $action): RedirectResponse
    {
        $data = $request->validate([
            'method' => ['required', Rule::in(['cash', 'transfer', 'qris', 'other'])],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'items' => ['required', 'array'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.restore_stock' => ['nullable', 'boolean'],
        ]);

        $refund = $action->handle(
            $request->user(),
            $sale,
            $data['items'],
            $data['method'],
            $data['reason'],
        );

        return redirect()->route('sales.show', $sale)
            ->with('status', "Permintaan refund {$refund->refund_number} dibuat dan menunggu persetujuan.");
    }
}
