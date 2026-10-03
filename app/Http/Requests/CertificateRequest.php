<?php

namespace App\Http\Requests;

use App\Models\Asset;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Site;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CertificateRequest extends FormRequest
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
                $certificate = $this->route('certificate');
                if ($certificate instanceof Certificate && $certificate->site_id === (int) $value) {
                    return;
                }
                if (! Site::whereKey($value)->where('is_active', true)->whereNull('entered_in_error_at')->whereHas('client', fn ($client) => $client->where('is_active', true)->whereNull('entered_in_error_at'))->exists()) {
                    $fail('Choose an active site belonging to an active client.');
                }
            }],
            'asset_id' => ['nullable', 'integer', Rule::exists('assets', 'id')->where('site_id', $this->integer('site_id')), function (string $attribute, mixed $value, Closure $fail): void {
                if ($value === null || $value === '') {
                    return;
                }
                $certificate = $this->route('certificate');
                if ($certificate instanceof Certificate && $certificate->asset_id === (int) $value) {
                    return;
                }
                if (! Asset::whereKey($value)->where('status', 'active')->whereNull('entered_in_error_at')->exists()) {
                    $fail('Choose active equipment for a new certificate.');
                }
            }],
            'certificate_type_id' => ['required', 'integer', Rule::exists('certificate_types', 'id'), function (string $attribute, mixed $value, Closure $fail): void {
                $certificate = $this->route('certificate');
                if ($certificate instanceof Certificate && $certificate->certificate_type_id === (int) $value) {
                    return;
                }
                if (! CertificateType::query()->whereKey($value)->where('is_active', true)->exists()) {
                    $fail('Choose an active certificate type.');
                }
            }],
            'issue_date' => ['required', 'date_format:Y-m-d'],
            'expiry_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:issue_date'],
            'issued_by' => ['nullable', 'string', 'max:255'],
            'upload_pending' => ['sometimes', 'boolean'],
        ];
    }
}
