<?php

namespace App\Http\Requests;

use App\Actions\BuildScheduleAgenda;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'schedule_from' => ['nullable', 'required_if:schedule_date_mode,from,between', 'date_format:Y-m-d'],
            'schedule_to' => ['nullable', 'required_if:schedule_date_mode,through,between', 'date_format:Y-m-d', Rule::when($this->filled('schedule_from'), 'after_or_equal:schedule_from')],
            'schedule_date_mode' => ['nullable', Rule::in(['through', 'from', 'between'])],
            'schedule_type' => ['nullable', Rule::in(['all', 'asset', 'certificate'])],
            'schedule_horizon' => ['nullable', Rule::in(array_keys(BuildScheduleAgenda::horizonPresets()))],
            'schedule_overdue' => ['nullable', Rule::in(BuildScheduleAgenda::overdueModes())],
            // Not range-checked against the window: the strip only offers days it
            // is already showing, and a hand-typed day outside the window is
            // answered with an empty agenda rather than a validation error.
            'schedule_day' => ['nullable', 'date_format:Y-m-d'],
            'schedule_page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
