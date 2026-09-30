<?php

namespace App\Http\Requests;

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
            'site_id' => ['required', 'integer', Rule::exists('sites', 'id')],
            'asset_id' => ['nullable', 'integer', Rule::exists('assets', 'id')->where('site_id', $this->integer('site_id'))],
            'certificate_type' => ['required', 'string', 'max:255'],
            'issue_date' => ['required', 'date_format:Y-m-d'],
            'expiry_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:issue_date'],
            'issued_by' => ['nullable', 'string', 'max:255'],
            'document_url' => ['nullable', 'url:http,https', 'max:2000'],
        ];
    }
}
