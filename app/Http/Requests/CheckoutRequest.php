<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('owner', 'admin', 'cashier') ?? false;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.discount' => ['sometimes', 'integer', 'min:0'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'in:cash,transfer,qris,ewallet'],
            'payments.*.amount' => ['required', 'integer', 'min:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
            'discount' => ['sometimes', 'integer', 'min:0'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'cash_session_id' => ['required', 'integer', 'exists:cash_sessions,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
