<?php

namespace App\Http\Controllers;

use App\Actions\Sales\CheckoutSale;
use App\Http\Requests\CheckoutRequest;
use App\Models\CashSession;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class PosController extends Controller
{
    public function index(): View
    {
        return view('pos.index');
    }

    public function checkout(CheckoutRequest $request, CheckoutSale $checkout): JsonResponse
    {
        abort_unless($request->user()->tokenCan('pos:checkout'), 403);
        $data = $request->validated();
        $sale = $checkout->handle(
            $request->user(),
            $data['items'],
            $data['payments'],
            $data['discount'] ?? 0,
            isset($data['customer_id']) ? Customer::find($data['customer_id']) : null,
            isset($data['cash_session_id']) ? CashSession::find($data['cash_session_id']) : null,
            $data['notes'] ?? null,
        );

        return response()->json(['data' => $sale], 201);
    }
}
