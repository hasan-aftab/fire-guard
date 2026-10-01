<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TrackerFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'record_state' => ['nullable', Rule::in(['current', 'entered_in_error', 'all'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(['all', 'overdue', 'due_soon', 'ok', 'unknown'])],
            'result' => ['nullable', Rule::in(['all', 'pass', 'fail', 'needs_repair'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('from'), 'after_or_equal:from')],
            'issue_from' => ['nullable', 'date_format:Y-m-d'],
            'issue_to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('issue_from'), 'after_or_equal:issue_from')],
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')],
            'site_id' => ['nullable', 'integer', Rule::exists('sites', 'id')],
            'asset_id' => ['nullable', 'integer', Rule::exists('assets', 'id')],
        ];
    }
}
