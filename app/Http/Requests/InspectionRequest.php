<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'asset_id' => ['required', 'integer', Rule::exists('assets', 'id')],
            'technician_id' => ['required', 'integer', Rule::exists('technicians', 'id')],
            'inspection_date' => ['required', 'date_format:Y-m-d'],
            'result' => ['required', Rule::in(['pass', 'fail', 'needs_repair'])],
            'notes' => ['nullable', 'string', 'max:10000'],
            'next_due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:inspection_date'],
            'photo_url' => ['nullable', 'url:http,https', 'max:2000'],
        ];
    }
}
