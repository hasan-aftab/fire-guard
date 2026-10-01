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
            'site_phone' => ['nullable', 'string', 'max:255', 'regex:/^(?=(?:\D*\d){7,20}\D*$)\+?(?:\d+|\(\d+\))(?:[ .-]*(?:\d+|\(\d+\)))*(?:\s*(?:x|ext\.?)\s*\d+)?$/i'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'client_id.required' => 'The client for this site is missing. Close the form and open it again.',
            'client_id.exists' => 'The client for this site is no longer available. Refresh the page.',
            'client_id.in' => 'This site belongs to another client and cannot be moved here.',
            'site_phone.regex' => 'Enter a site phone number with 7 to 20 digits using common separators or an extension.',
        ];
    }
}
