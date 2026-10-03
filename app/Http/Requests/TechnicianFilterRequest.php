<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TechnicianFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'record_state' => ['nullable', Rule::in(['current', 'entered_in_error', 'all'])],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
            'license_status' => ['nullable', Rule::in(['all', 'expired', 'expiring_soon', 'valid', 'missing'])],
            'inspection_activity' => ['nullable', Rule::in(['all', 'with_inspections', 'without_inspections'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('from'), 'after_or_equal:from')],
            'sort' => ['nullable', Rule::in(['name', 'expiry_soonest', 'most_inspections'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
