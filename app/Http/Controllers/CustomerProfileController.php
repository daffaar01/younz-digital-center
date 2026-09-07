<?php

namespace App\Http\Controllers;

use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerProfileController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function edit(Request $request): View
    {
        return view('customer.profile.edit', [
            'customer' => $request->user()->customer,
        ]);
    }

    public function updateApi(Request $request): JsonResponse
    {
        $this->updateCustomerProfile($request);

        return response()->json([
            'message' => 'Profil pelanggan berhasil disimpan.',
            'data' => $request->user()->fresh('customer'),
        ]);
    }
    public function update(Request $request): RedirectResponse
    {
        $this->updateCustomerProfile($request);

        return redirect()->route('customer.dashboard')->with('status', 'Profil pelanggan berhasil disimpan.');
    }

    private function updateCustomerProfile(Request $request): void
    {
        $user = $request->user();
        if ($request->bearerToken()) {
            abort_unless($request->user()->tokenCan('profile:read'), 403);
        }
        $request->merge([
            'name' => trim($request->string('name')->toString()),
            'phone' => preg_replace('/\D+/', '', $request->string('phone')->toString()),
        ]);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'phone' => ['required', 'digits_between:9,15', Rule::unique('users', 'phone')->ignore($user->id)],
            'address' => ['nullable', 'string', 'max:1000'],
            'marketing_consent' => ['nullable', 'boolean'],
        ]);
        DB::transaction(function () use ($user, $data, $request): void {
            $existingCustomer = $user->customer;
            $before = ['name' => $user->name, 'phone' => $user->phone, 'address' => $existingCustomer?->address];
            $user->update(['name' => $data['name'], 'phone' => $data['phone']]);
            $customer = $user->customer()->updateOrCreate([], [
                'name' => $data['name'], 'email' => $user->email, 'phone' => $data['phone'],
                'address' => $data['address'] ?? null, 'type' => $existingCustomer?->type ?? 'umum',
                'marketing_consent' => $request->boolean('marketing_consent'),
            ]);
            $this->audit->log('customer.profile_updated', $customer, before: $before, after: ['name' => $data['name'], 'phone' => $data['phone'], 'address' => $data['address'] ?? null]);
        }, 3);
    }
}
