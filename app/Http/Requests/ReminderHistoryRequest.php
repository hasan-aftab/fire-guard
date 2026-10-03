<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReminderHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['pending', 'processing', 'sent', 'failed', 'skipped', 'uncertain'])],
            'type' => ['nullable', Rule::in(['asset', 'certificate'])],
            'audience' => ['nullable', Rule::in(['configured', 'configured_client', 'staff', 'client'])],
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')],
            'due_from' => ['nullable', 'date_format:Y-m-d'],
            'due_to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('due_from'), 'after_or_equal:due_from')],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
