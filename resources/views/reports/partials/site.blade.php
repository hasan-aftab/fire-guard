<section>
    <h2>Site: {{ $site['name'] }}</h2>
    <p>{{ $site['address'] }}@if ($site['region']), {{ $site['region'] }}@endif</p>
    @if ($site['contact'] || $site['phone'])
        <p class="small">Site contact: {{ $site['contact'] ?: 'Not recorded' }} {{ $site['phone'] ?: '' }}</p>
    @endif
    @if (! $site['is_active'] || $site['entered_in_error'])
        <p class="muted">Site record: {{ $site['entered_in_error'] ? 'entered in error' : 'inactive' }}</p>
    @endif

    @foreach ($site['assets'] as $asset)
        @include('reports.partials.asset', ['asset' => $asset])
    @endforeach

    @if ($site['certificates'] !== [])
        <h3>Recorded certificates</h3>
        <table class="data">
            <thead><tr><th>Type</th><th>Issued</th><th>Expires</th><th>Issuer</th><th>Status today</th></tr></thead>
            <tbody>
            @foreach ($site['certificates'] as $certificate)
                <tr>
                    <td>{{ $certificate['type'] }} @if ($certificate['entered_in_error']) (entered in error) @endif</td>
                    <td>{{ $certificate['issue_date'] }}</td>
                    <td>{{ $certificate['expiry_date'] }}</td>
                    <td>{{ $certificate['issued_by'] ?: 'Not recorded' }}</td>
                    <td>{{ str_replace('_', ' ', $certificate['status']) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</section>
