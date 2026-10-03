@extends('mail.layout')

@section('title', 'Certificate expiry reminder')
@section('eyebrow', 'CERTIFICATE EXPIRY')
@section('date_label', 'Certificate expiry date')

@section('headline')
@if ($delivery->reminder_offset < 0) Certificate expired
@elseif ($delivery->reminder_offset === 0) Certificate expires today
@else Certificate expires in {{ $delivery->reminder_offset }} {{ $delivery->reminder_offset === 1 ? 'day' : 'days' }}
@endif
@endsection

@section('intro')
The certificate below needs a renewal follow-up for {{ $delivery->site_name }}.
@endsection

@section('timing')
@if ($delivery->reminder_offset < 0) This date has passed.
@elseif ($delivery->reminder_offset === 0) Expires today.
@else {{ $delivery->reminder_offset }} {{ $delivery->reminder_offset === 1 ? 'day' : 'days' }} remaining.
@endif
@endsection

@section('work_detail')
<tr><td valign="top" style="padding:13px 0;border-bottom:1px solid #dde1da;color:#69746e;font-size:13px;width:116px">Certificate</td><td valign="top" style="padding:13px 0;border-bottom:1px solid #dde1da;color:#202725;font-size:14px;font-weight:600">{{ $delivery->subject_name }}</td></tr>
@endsection

@section('next_step')
Check the certificate record and contact the client about renewal before the expiry date.
@endsection
