<div class="brand">
    @if ($report->logoDataUri)
        <img src="{{ $report->logoDataUri }}" alt="Company logo" style="float: right; max-width: 40mm; max-height: 18mm; width: auto; height: auto;">
    @endif
    <div class="brand-name">{{ config('fire-tracker.report.company_name') }}</div>
    @if (config('fire-tracker.report.company_phone') || config('fire-tracker.report.company_email'))
        <div class="small">{{ config('fire-tracker.report.company_phone') }} {{ config('fire-tracker.report.company_email') }}</div>
    @endif
</div>
