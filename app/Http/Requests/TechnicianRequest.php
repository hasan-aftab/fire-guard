<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TechnicianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255', 'regex:/^(?=(?:\D*\d){7,20}\D*$)\+?(?:\d+|\(\d+\))(?:[ .-]*(?:\d+|\(\d+\)))*(?:\s*(?:x|ext\.?)\s*\d+)?$/i'],
            'email' => ['nullable', 'email', 'max:255'],
            'license_number' => ['nullable', 'string', 'max:255'],
            'license_expiry' => ['nullable', 'date_format:Y-m-d'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a phone number with 7 to 20 digits using common separators or an extension.',
        ];
    }
}
