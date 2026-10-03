<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class InspectionFilterRequest extends TrackerFilterRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return array_replace(parent::rules(), [
            'technician_id' => ['nullable', 'integer', Rule::exists('technicians', 'id')],
            'sort' => ['nullable', Rule::in(['all', 'newest', 'oldest'])],
        ]);
    }
}
