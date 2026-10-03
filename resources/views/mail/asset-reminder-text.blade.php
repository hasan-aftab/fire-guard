@if ($preview)TEST COPY - Sample internal reminder

@endif
{{ config('fire-tracker.report.company_name') }} - Equipment inspection reminder

Hello {{ $delivery->recipient_name ?: 'team' }},

The equipment below needs an inspection follow-up.

Equipment: {{ $delivery->subject_name }}
Client: {{ $delivery->client_name }}
Site: {{ $delivery->site_name }}
@if ($delivery->site_address)Address: {{ $delivery->site_address }}
@endif
Next inspection date: {{ $delivery->due_date->format('j F Y') }}
@if ($delivery->reminder_offset < 0)Status: Overdue
@elseif ($delivery->reminder_offset === 0)Status: Due today
@else Status: Due in {{ $delivery->reminder_offset }} {{ $delivery->reminder_offset === 1 ? 'day' : 'days' }}
@endif

Client contact: {{ $delivery->client_contact_name ?: 'No contact name on file' }}
@if ($delivery->client_email)Client email: {{ $delivery->client_email }}
@endif
@if ($delivery->client_phone)Client phone: {{ $delivery->client_phone }}
@endif

Contact the client to confirm access and arrange the inspection. Review the asset record before the visit.

@if (config('fire-tracker.report.company_phone'))Phone: {{ config('fire-tracker.report.company_phone') }}
@endif
@if (config('fire-tracker.report.company_email'))Email: {{ config('fire-tracker.report.company_email') }}
@endif
{{ config('fire-tracker.report.company_name') }}
