<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DigitalProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->isStaff()
            && $user->tokenCan('staff:products')
            && $user->hasRole('owner', 'admin');
    }

    public function rules(): array
    {
        $creating = $this->route('digitalProduct') === null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::in(['Komunitas', 'Streaming', 'AI Assistant', 'AI Kreatif'])],
            'mark' => ['nullable', 'string', 'max:4'],
            'stock' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'price' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'description' => ['required', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'variants' => ['sometimes', 'array', 'max:20'],
            'variants_present' => ['sometimes', 'boolean'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.label' => ['required', 'string', 'max:80', 'distinct:ignore_case'],
            'variants.*.price' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'variants.*.stock' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'variants.*.is_active' => ['sometimes', 'boolean'],
            'variants.*.sort_order' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'image' => [Rule::requiredIf($creating), 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:max_width=4096,max_height=4096'],
        ];
    }
}
