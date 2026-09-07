<?php

namespace App\Http\Controllers;

use App\Actions\Approvals\RequestActionApproval;
use App\Enums\ApprovalType;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    public function __construct(private readonly RequestActionApproval $requestApproval) {}

    public function store(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'not_in:0'],
            'type' => ['required', 'in:purchase,adjustment,return_customer,return_supplier,damaged,lost,internal_use'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $approval = $this->requestApproval->handle(
            $request->user(),
            ApprovalType::StockAdjustment,
            $product,
            [
                'quantity' => (int) $validated['quantity'],
                'movement_type' => $validated['type'],
                'reason' => $validated['reason'],
            ],
            $validated['reason'],
        );

        return back()->with('status', "Penyesuaian stok menunggu persetujuan ({$approval->request_number}).");
    }
}
