<?php

namespace App\Http\Requests;

use App\Models\AssetType;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-reference-data') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['type_name' => preg_replace('/\s+/u', ' ', trim((string) $this->input('type_name')))]);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'type_name' => ['required', 'string', 'max:255', Rule::unique('asset_types', 'type_name')->ignore($this->route('assetType')),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $current = $this->route('assetType');
                    $query = AssetType::query()->whereRaw('LOWER(type_name) = LOWER(?)', [$value]);
                    if ($current instanceof AssetType) {
                        $query->whereKeyNot($current->id);
                    }
                    if ($query->exists()) {
                        $fail('An asset type with this name already exists.');
                    }
                }],
            'default_interval_months' => ['required', 'integer', 'min:1', 'max:1200'],
        ];
    }
}
