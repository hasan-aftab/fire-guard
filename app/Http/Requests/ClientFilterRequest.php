<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class ClientFilterRequest extends TrackerFilterRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return array_replace(parent::rules(), [
            'active' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
            'site_presence' => ['nullable', Rule::in(['all', 'with_sites', 'without_sites'])],
            'sort' => ['nullable', Rule::in(['name', 'name_desc', 'most_sites'])],
            'site_client' => ['nullable', 'integer', Rule::exists('clients', 'id')],
            'site_page' => ['nullable', 'integer', 'min:1'],
            'site_state' => ['nullable', Rule::in(['current', 'entered_in_error'])],
        ]);
    }
}
