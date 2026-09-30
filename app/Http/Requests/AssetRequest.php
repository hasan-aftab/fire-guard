<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'site_id' => ['required', 'integer', Rule::exists('sites', 'id')],
            'asset_type_id' => ['required', 'integer', Rule::exists('asset_types', 'id')],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'location_desc' => ['nullable', 'string', 'max:5000'],
            'install_date' => ['nullable', 'date_format:Y-m-d'],
            'last_service_date' => ['nullable', 'date_format:Y-m-d'],
            'next_service_due' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['sometimes', Rule::in(['active', 'decommissioned', 'missing', 'needs_replacement'])],
        ];
    }
}
