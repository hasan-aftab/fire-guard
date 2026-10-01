<?php

namespace App\Http\Requests;

use App\Models\Asset;
use App\Models\Inspection;
use App\Models\Technician;
use Closure;
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
            'asset_id' => ['required', 'integer', Rule::exists('assets', 'id'), function (string $attribute, mixed $value, Closure $fail): void {
                $inspection = $this->route('inspection');
                if ($inspection instanceof Inspection && $inspection->asset_id === (int) $value) {
                    return;
                }
                if (! Asset::whereKey($value)->where('status', 'active')->whereHas('site', fn ($site) => $site->where('is_active', true)->whereHas('client', fn ($client) => $client->where('is_active', true)))->exists()) {
                    $fail('Choose active equipment at an active site.');
                }
            }],
            'technician_id' => ['required', 'integer', Rule::exists('technicians', 'id'), function (string $attribute, mixed $value, Closure $fail): void {
                $inspection = $this->route('inspection');
                if ($inspection instanceof Inspection && $inspection->technician_id === (int) $value) {
                    return;
                }
                if (! Technician::whereKey($value)->where('is_active', true)->exists()) {
                    $fail('Choose an active technician.');
                }
            }],
            'inspection_date' => ['required', 'date_format:Y-m-d'],
            'result' => ['required', Rule::in(['pass', 'fail', 'needs_repair'])],
            'notes' => ['nullable', 'string', 'max:10000'],
            'next_due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:inspection_date'],
            'photo_url' => ['nullable', 'url:http,https', 'max:2000'],
        ];
    }
}
