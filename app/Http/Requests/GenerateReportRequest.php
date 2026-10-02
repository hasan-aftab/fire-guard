<?php

namespace App\Http\Requests;

use App\Reports\ReportFilters;
use App\ReportType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ReportType::class)],
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')],
            'site_id' => ['nullable', 'integer', Rule::exists('sites', 'id')->where('client_id', $this->integer('client_id'))],
            'date_from' => ['required', 'date_format:Y-m-d', 'before_or_equal:date_to'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'include_entered_in_error' => ['sometimes', 'boolean'],
            'disposition' => ['sometimes', Rule::in(['inline', 'download'])],
        ];
    }

    public function filters(): ReportFilters
    {
        $data = $this->validated();

        return new ReportFilters(
            ReportType::from($data['type']),
            (int) $data['client_id'],
            isset($data['site_id']) ? (int) $data['site_id'] : null,
            $data['date_from'],
            $data['date_to'],
            (bool) ($data['include_entered_in_error'] ?? false),
        );
    }
}
