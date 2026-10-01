<?php

namespace App\Http\Requests;

use App\Models\Asset;
use App\Models\Site;
use Closure;
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
            'site_id' => ['required', 'integer', Rule::exists('sites', 'id'), function (string $attribute, mixed $value, Closure $fail): void {
                $asset = $this->route('asset');
                if ($asset instanceof Asset && $asset->site_id === (int) $value) {
                    return;
                }
                if (! Site::whereKey($value)->where('is_active', true)->whereNull('entered_in_error_at')->whereHas('client', fn ($client) => $client->where('is_active', true)->whereNull('entered_in_error_at'))->exists()) {
                    $fail('Choose an active site belonging to an active client.');
                }
            }],
            'asset_type_id' => ['required', 'integer', Rule::exists('asset_types', 'id')],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'location_desc' => ['nullable', 'string', 'max:5000'],
            'install_date' => ['nullable', 'date_format:Y-m-d'],
            'last_service_date' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('install_date'), 'after_or_equal:install_date')],
            'next_service_due' => [
                'nullable',
                'date_format:Y-m-d',
                Rule::when($this->filled('install_date'), 'after_or_equal:install_date'),
                Rule::when($this->filled('last_service_date'), 'after_or_equal:last_service_date'),
            ],
            'status' => ['sometimes', Rule::in(['active', 'decommissioned', 'missing', 'needs_replacement'])],
        ];
    }
}
