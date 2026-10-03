<?php

namespace App\Http\Requests;

use App\Models\CertificateType;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CertificateTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-reference-data') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => preg_replace('/\s+/u', ' ', trim((string) $this->input('name')))]);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('certificate_types', 'name')->ignore($this->route('certificateType')),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $current = $this->route('certificateType');
                    $query = CertificateType::query()->whereRaw('LOWER(name) = LOWER(?)', [$value]);
                    if ($current instanceof CertificateType) {
                        $query->whereKeyNot($current->id);
                    }
                    if ($query->exists()) {
                        $fail('A certificate type with this name already exists.');
                    }
                }],
        ];
    }
}
