@extends('mail.layout')

@section('title', 'Equipment inspection reminder')
@section('eyebrow', 'EQUIPMENT INSPECTION')
@section('date_label', 'Next inspection date')

@section('headline')
@if ($delivery->reminder_offset < 0) Inspection overdue
@elseif ($delivery->reminder_offset === 0) Inspection due today
@else Inspection due in {{ $delivery->reminder_offset }} {{ $delivery->reminder_offset === 1 ? 'day' : 'days' }}
@endif
@endsection

@section('intro')
@if (! $delivery->isClientAudience()) The equipment below needs an inspection follow-up at {{ $delivery->site_name }}.
@else The equipment below is approaching its next inspection at {{ $delivery->site_name }}.
@endif
@endsection

@section('timing')
@if ($delivery->reminder_offset < 0) This date has passed.
@elseif ($delivery->reminder_offset === 0) Due today.
@else {{ $delivery->reminder_offset }} {{ $delivery->reminder_offset === 1 ? 'day' : 'days' }} remaining.
@endif
@endsection

@section('work_detail')
<tr><td valign="top" style="padding:13px 0;border-bottom:1px solid #dde1da;color:#69746e;font-size:13px;width:116px">Equipment</td><td valign="top" style="padding:13px 0;border-bottom:1px solid #dde1da;color:#202725;font-size:14px;font-weight:600">{{ $delivery->subject_name }}</td></tr>
@endsection

@section('next_step')
@if (! $delivery->isClientAudience()) Contact the client to confirm access and arrange the inspection. Review the asset record before the visit.
@else Please make the equipment available for inspection. Contact our team if the site or access details have changed.
@endif
@endsection
