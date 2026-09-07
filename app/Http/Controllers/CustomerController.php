<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('customers.index', [
            'customers' => Customer::query()
                ->when(request('q'), fn ($query, string $q) => $query->where(fn ($query) => $query->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")))
                ->latest()->paginate(20)->withQueryString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', 'in:umum,pelajar,guru,sekolah,kantor,umkm,instansi,pelanggan_tetap'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $customer = Customer::create($data);
        $this->audit->log('customer.created', $customer, after: $customer->toArray());

        return back()->with('status', 'Pelanggan berhasil ditambahkan.');
    }
}
