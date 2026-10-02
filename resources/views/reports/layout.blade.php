<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report->type->title() }}</title>
    <style>
        @page { size: A4 portrait; margin: 17mm 15mm 20mm; }
        body { margin: 0; color: #1d2939; font: 10pt DejaVu Sans, sans-serif; line-height: 1.45; }
        h1, h2, h3 { color: #102a43; page-break-after: avoid; }
        h1 { font-size: 19pt; margin: 0 0 3mm; }
        h2 { font-size: 13pt; margin: 8mm 0 2mm; border-bottom: 1px solid #aab8c6; padding-bottom: 1mm; }
        h3 { font-size: 10.5pt; margin: 5mm 0 2mm; }
        p { margin: 0 0 2mm; }
        .brand { border-bottom: 2px solid #1d526b; padding-bottom: 4mm; margin-bottom: 6mm; }
        .brand-name { color: #1d526b; font-size: 15pt; font-weight: bold; }
        .small, .muted { color: #526477; font-size: 8.5pt; }
        .meta { width: 100%; border-collapse: collapse; margin: 4mm 0 6mm; }
        .meta td { width: 50%; padding: 2mm 0; vertical-align: top; }
        .record { page-break-inside: avoid; margin: 4mm 0 5mm; border-left: 3px solid #1d526b; padding-left: 3mm; }
        .record h3 { margin-top: 0; }
        table.data { width: 100%; border-collapse: collapse; margin: 3mm 0 5mm; font-size: 8.5pt; }
        table.data th, table.data td { border: 1px solid #d6dee5; padding: 2mm; text-align: left; vertical-align: top; }
        table.data th { background: #eaf0f4; }
        .photo { page-break-inside: avoid; margin: 3mm 0 6mm; }
        .photo img { display: block; max-width: 100%; max-height: 95mm; width: auto; height: auto; margin-bottom: 1mm; }
        .notice { border: 1px solid #c5d2dc; padding: 3mm; margin: 3mm 0; background: #f3f6f8; }
        .footer { position: fixed; bottom: -13mm; left: 0; right: 0; border-top: 1px solid #c5d2dc; padding-top: 2mm; color: #526477; font-size: 8pt; }
    </style>
</head>
<body>
    <div class="footer">{{ config('fire-tracker.report.company_name') }} · {{ $report->type->title() }} · Generated {{ $report->generatedAt }}</div>
    @include('reports.partials.header')

    <h1>{{ $report->type->title() }}</h1>
    <p class="muted">Reporting period: {{ $report->dateFrom }} to {{ $report->dateTo }} · Generated {{ $report->generatedAt }}</p>
    @yield('intro')

    <h2>Client</h2>
    <table class="meta"><tr>
        <td><strong>{{ $report->client['name'] }}</strong><br>{{ $report->client['address'] ?: 'Address not recorded' }}</td>
        <td>{{ $report->client['contact'] ?: 'Contact not recorded' }}<br>{{ $report->client['phone'] ?: '' }} {{ $report->client['email'] ?: '' }}</td>
    </tr></table>
    @if (! $report->client['is_active'] || $report->client['entered_in_error'])
        <p class="muted">Client record: {{ $report->client['entered_in_error'] ? 'entered in error' : 'inactive' }}</p>
    @endif

    @forelse ($report->sites as $site)
        @include('reports.partials.site', ['site' => $site])
    @empty
        <p class="notice">No matching records were found for this reporting period and scope.</p>
    @endforelse
</body>
</html>
