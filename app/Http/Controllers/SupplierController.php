<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('suppliers.index', ['suppliers' => Supplier::query()->latest()->paginate(20)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email'],
            'address' => ['nullable', 'string'],
        ]);
        $supplier = Supplier::create($data);
        $this->audit->log('supplier.created', $supplier, after: $supplier->toArray());

        return back()->with('status', 'Supplier berhasil ditambahkan.');
    }
}
