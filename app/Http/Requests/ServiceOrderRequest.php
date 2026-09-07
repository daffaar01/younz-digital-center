<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $authenticatedDigitalCustomer = $this->user()
            && ! $this->user()->isStaff()
            && $this->string('type')->toString() === 'digital';
        $rules = [
            'service_id' => ['prohibited_if:type,digital', 'nullable', Rule::exists('services', 'id')->where('is_active', true)],
            'customer_name' => [Rule::requiredIf(! $authenticatedDigitalCustomer), 'string', 'max:150'],
            'customer_phone' => [Rule::requiredIf(! $authenticatedDigitalCustomer), 'string', 'max:30'],
            'type' => ['required', 'in:print,fotokopi,scan,ketik,desain,website,aplikasi,digital'],
            'idempotency_key' => ['prohibited_unless:type,digital', 'required_if:type,digital', 'uuid'],
            'product_id' => ['prohibited_unless:type,digital', 'required_if:type,digital', 'integer', Rule::exists('digital_products', 'id')->where('is_active', true)],
            'variant_id' => ['prohibited_unless:type,digital', 'nullable', 'integer'],
            'quantity' => ['prohibited_unless:type,digital', 'required_if:type,digital', 'integer', 'min:1', 'max:99'],
            'specifications' => ['nullable', 'array'],
            'source' => ['nullable', 'in:hero,catalog,process,sticky,final,direct,digital-product,digital-product-detail,android-app'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'file' => ['prohibited_if:type,digital', 'nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,txt'],
        ];

        if ($this->user()?->isStaff()) {
            $rules['customer_id'] = ['nullable', 'exists:customers,id'];
            $rules['estimated_price'] = ['nullable', 'integer', 'min:0'];
            $rules['deadline_at'] = ['nullable', 'date', 'after_or_equal:today'];
        }

        return $rules;
    }
}
