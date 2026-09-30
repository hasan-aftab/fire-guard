<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'billing_address' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
