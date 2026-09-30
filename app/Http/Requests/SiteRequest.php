<?php

namespace App\Http\Requests;

use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $site = $this->route('site');

        return [
            'client_id' => [
                'required',
                'integer',
                Rule::exists('clients', 'id'),
                ...($site instanceof Site ? [Rule::in([$site->client_id])] : []),
            ],
            'site_name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:5000'],
            'suburb_region' => ['nullable', 'string', 'max:255'],
            'access_notes' => ['nullable', 'string', 'max:10000'],
            'site_contact' => ['nullable', 'string', 'max:255'],
            'site_phone' => ['nullable', 'string', 'max:255'],
        ];
    }
}
