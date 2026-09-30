<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'attention_from' => ['nullable', 'date_format:Y-m-d'],
            'attention_to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('attention_from'), 'after_or_equal:attention_from')],
            'attention_page' => ['nullable', 'integer', 'min:1'],
            'cert_from' => ['nullable', 'date_format:Y-m-d'],
            'cert_to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('cert_from'), 'after_or_equal:cert_from')],
            'cert_page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
