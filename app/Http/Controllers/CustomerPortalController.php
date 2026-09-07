<?php

namespace App\Http\Controllers;

use App\Enums\ServiceOrderStatus;
use App\Models\Customer;
use App\Models\ServiceOrder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerPortalController extends Controller
{
    public function index(Request $request): View
    {
        $customer = $request->user()->customer;
        if (! $customer instanceof Customer) {
            abort(403, 'Profil pelanggan belum tersedia.');
        }

        $ordersQuery = $customer->serviceOrders();

        return view('customer.dashboard', [
            'customer' => $customer,
            'orders' => (clone $ordersQuery)->with('service')->latest()->paginate(10),
            'totalOrders' => (clone $ordersQuery)->count(),
            'activeOrders' => (clone $ordersQuery)->whereNotIn('status', [
                ServiceOrderStatus::Completed->value,
                ServiceOrderStatus::Cancelled->value,
            ])->count(),
            'completedOrders' => (clone $ordersQuery)->where('status', ServiceOrderStatus::Completed->value)->count(),
        ]);
    }

    public function show(Request $request, ServiceOrder $order): View
    {
        $customer = $request->user()->customer;
        abort_unless($customer && $order->customer_id === $customer->id, 404);

        $order->load([
            'service',
            'files' => fn ($query) => $query->where('kind', 'result')->oldest(),
            'statusHistories' => fn ($query) => $query->oldest(),
        ]);

        return view('customer.orders.show', [
            'order' => $order,
            'canDownloadResults' => $order->canReleaseResults(),
        ]);
    }
}
