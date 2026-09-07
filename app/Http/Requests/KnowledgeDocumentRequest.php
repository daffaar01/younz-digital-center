<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KnowledgeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('owner', 'admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'type' => ['required', Rule::in(['faq', 'policy', 'privacy', 'service', 'guide'])],
            'content' => ['required', 'string', 'max:20000'],
            'status' => ['required', Rule::in(['active', 'draft', 'archived'])],
        ];
    }
}
