<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class AssetFilterRequest extends TrackerFilterRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'asset_type_id' => ['nullable', 'integer', Rule::exists('asset_types', 'id')],
            'equipment_status' => ['nullable', Rule::in(['all', 'active', 'decommissioned', 'missing', 'needs_replacement'])],
            'sort' => ['nullable', Rule::in(['oldest', 'newest', 'due_soonest', 'due_latest'])],
        ];
    }
}
