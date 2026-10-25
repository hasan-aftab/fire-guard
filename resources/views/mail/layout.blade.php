<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
</head>
<body style="margin:0;padding:0;background:#eeefeb;color:#202725;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;background:#eeefeb">
<tr><td align="center" style="padding:28px 12px 40px">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="640" style="width:100%;max-width:640px;border-collapse:collapse;background:#fffefa;border:1px solid #d9ddd6">
    <tr><td style="height:5px;background:#d89936;font-size:1px;line-height:1px">&nbsp;</td></tr>
    <tr><td style="padding:25px 34px;background:#202725;color:#fffefa">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse">
            <tr>
                <td valign="middle" width="42"><div style="height:38px;width:38px;line-height:38px;text-align:center;background:#d89936;color:#202725;font-size:16px;font-weight:800;letter-spacing:-1px">FG</div></td>
                <td valign="middle" style="padding-left:12px;font-size:16px;font-weight:700;letter-spacing:.2px">{{ config('fire-tracker.report.company_name') }}<br><span style="font-size:11px;font-weight:400;color:#bfc7c0">Service schedule notice</span></td>
                <td valign="middle" align="right" style="font-size:11px;color:#d89936;font-weight:700">@yield('eyebrow')</td>
            </tr>
        </table>
    </td></tr>
    @if ($preview)
    <tr><td style="padding:11px 34px;background:#fff1d9;color:#80510e;font-size:12px;font-weight:700;border-bottom:1px solid #efddbd">TEST COPY · Sample internal reminder</td></tr>
    @endif
    <tr><td style="padding:38px 34px 18px">
        <p style="margin:0 0 12px;color:#996219;font-size:12px;font-weight:700;letter-spacing:.5px">@yield('eyebrow')</p>
        <h1 style="margin:0 0 16px;color:#202725;font-size:30px;line-height:1.18;font-weight:700;letter-spacing:-.7px">@yield('headline')</h1>
        <p style="margin:0;color:#4b5650;font-size:15px;line-height:1.7">Hello {{ $delivery->recipient_name ?: 'team' }},<br>@yield('intro')</p>
    </td></tr>
    <tr><td style="padding:8px 34px 24px">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;background:#f7f3e9;border-left:4px solid #d89936">
            <tr><td style="padding:20px 22px">
                <p style="margin:0 0 6px;color:#6a5a3e;font-size:12px;font-weight:700">@yield('date_label')</p>
                <p style="margin:0;color:#202725;font-size:25px;font-weight:700;line-height:1.2">{{ $delivery->due_date->format('j F Y') }}</p>
                <p style="margin:8px 0 0;color:#80510e;font-size:13px;font-weight:700">@yield('timing')</p>
            </td></tr>
        </table>
    </td></tr>
    <tr><td style="padding:0 34px 8px">
        <p style="margin:0 0 13px;color:#202725;font-size:13px;font-weight:700">Work details</p>
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;border-top:1px solid #dde1da">
            @yield('work_detail')
            <tr><td valign="top" style="padding:13px 0;border-bottom:1px solid #dde1da;color:#69746e;font-size:13px;width:116px">Client</td><td valign="top" style="padding:13px 0;border-bottom:1px solid #dde1da;color:#202725;font-size:14px;font-weight:600">{{ $delivery->client_name }}</td></tr>
            <tr><td valign="top" style="padding:13px 0;border-bottom:1px solid #dde1da;color:#69746e;font-size:13px">Site</td><td valign="top" style="padding:13px 0;border-bottom:1px solid #dde1da;color:#202725;font-size:14px;font-weight:600">{{ $delivery->site_name }}@if ($delivery->site_address)<br><span style="font-size:13px;font-weight:400;color:#66716b">{{ $delivery->site_address }}</span>@endif</td></tr>
        </table>
    </td></tr>
    <tr><td style="padding:18px 34px 0">
        <p style="margin:0 0 9px;color:#202725;font-size:13px;font-weight:700">Client contact</p>
        <p style="margin:0;color:#4b5650;font-size:14px;line-height:1.65">{{ $delivery->client_contact_name ?: 'No contact name on file' }}<br>
        @if ($delivery->client_email){{ $delivery->client_email }}<br>@endif
        @if ($delivery->client_phone){{ $delivery->client_phone }}@elseif (! $delivery->client_email)<span style="color:#7d8781">No email or phone on file</span>@endif</p>
    </td></tr>
    <tr><td style="padding:25px 34px 35px">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;background:#eef1ec">
            <tr><td style="padding:18px 20px;color:#34403a;font-size:14px;line-height:1.65"><strong style="color:#202725">Next step</strong><br>@yield('next_step')</td></tr>
        </table>
    </td></tr>
    <tr><td style="padding:21px 34px 25px;background:#f4f5f1;border-top:1px solid #dde1da;color:#5d6861;font-size:12px;line-height:1.65">
        <strong style="color:#202725">{{ config('fire-tracker.report.company_name') }}</strong><br>
        @if (config('fire-tracker.report.company_phone')){{ config('fire-tracker.report.company_phone') }}<br>@endif
        @if (config('fire-tracker.report.company_email')){{ config('fire-tracker.report.company_email') }}<br>@endif
        This reminder reflects the current schedule recorded in FireGuard.
    </td></tr>
</table>
</td></tr></table>
</body>
</html>
