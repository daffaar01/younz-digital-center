<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TestimonialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('owner', 'admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:80'],
            'customer_role' => ['nullable', 'string', 'max:100'],
            'quote' => ['required', 'string', 'max:800'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'source_label' => ['nullable', 'string', 'max:80'],
            'is_published' => ['sometimes', 'boolean'],
            'consent_at' => ['nullable', 'required_if:is_published,1', 'date', 'before_or_equal:now'],
            'display_order' => ['required', 'integer', 'between:0,999'],
        ];
    }
}
