@if ($preview)TEST COPY - Sample internal reminder

@endif
{{ config('fire-tracker.report.company_name') }} - Certificate expiry reminder

Hello {{ $delivery->recipient_name ?: 'team' }},

The certificate below needs a renewal follow-up.

Certificate: {{ $delivery->subject_name }}
Client: {{ $delivery->client_name }}
Site: {{ $delivery->site_name }}
@if ($delivery->site_address)Address: {{ $delivery->site_address }}
@endif
Expiry date: {{ $delivery->due_date->format('j F Y') }}
@if ($delivery->reminder_offset < 0)Status: Expired
@elseif ($delivery->reminder_offset === 0)Status: Expires today
@else Status: Expires in {{ $delivery->reminder_offset }} {{ $delivery->reminder_offset === 1 ? 'day' : 'days' }}
@endif

Client contact: {{ $delivery->client_contact_name ?: 'No contact name on file' }}
@if ($delivery->client_email)Client email: {{ $delivery->client_email }}
@endif
@if ($delivery->client_phone)Client phone: {{ $delivery->client_phone }}
@endif

Check the certificate record and contact the client about renewal before the expiry date.

@if (config('fire-tracker.report.company_phone'))Phone: {{ config('fire-tracker.report.company_phone') }}
@endif
@if (config('fire-tracker.report.company_email'))Email: {{ config('fire-tracker.report.company_email') }}
@endif
{{ config('fire-tracker.report.company_name') }}
