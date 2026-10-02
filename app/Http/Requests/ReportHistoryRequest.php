<?php

namespace App\Http\Requests;

use App\ReportType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportHistoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::enum(ReportType::class)],
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')],
            'site_id' => ['nullable', 'integer', Rule::exists('sites', 'id')->where('client_id', $this->integer('client_id'))],
            'generated_from' => ['nullable', 'date_format:Y-m-d'],
            'generated_to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('generated_from'), 'after_or_equal:generated_from')],
            'period_from' => ['nullable', 'date_format:Y-m-d'],
            'period_to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('period_from'), 'after_or_equal:period_from')],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
