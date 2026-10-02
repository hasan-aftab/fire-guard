@extends('reports.layout')

@section('intro')
    <p class="notice">System record summary as of {{ $report->generatedAt }}. Inspection history uses the selected period. This document does not state regulatory compliance or a formal audit conclusion.</p>
    <table class="data">
        <thead><tr><th>Sites</th><th>Assets</th><th>Overdue assets</th><th>Due soon</th><th>Certificates</th><th>Expired certificates</th><th>Inspections in period</th></tr></thead>
        <tbody><tr>
            <td>{{ $report->summary['sites'] }}</td><td>{{ $report->summary['assets'] }}</td><td>{{ $report->summary['overdue_assets'] }}</td><td>{{ $report->summary['due_soon_assets'] }}</td><td>{{ $report->summary['certificates'] }}</td><td>{{ $report->summary['expired_certificates'] }}</td><td>{{ $report->summary['inspections'] }}</td>
        </tr></tbody>
    </table>
@endsection
