<div class="record">
    <h3>{{ $asset['type'] }}@if ($asset['serial']) · {{ $asset['serial'] }}@endif</h3>
    <p>Location: {{ $asset['location'] ?: 'Not recorded' }} · Equipment condition: {{ str_replace('_', ' ', $asset['status']) }} @if ($asset['entered_in_error']) · Entered in error @endif</p>
    <p class="small">Installed: {{ $asset['installed'] ?: 'Not recorded' }} · Last service date: {{ $asset['last_service'] ?: 'Not recorded' }} · Next service due: {{ $asset['next_service'] ?: 'Not recorded' }} · Current schedule: {{ str_replace('_', ' ', $asset['service_status']) }}</p>

    @if ($asset['inspections'] !== [])
        <table class="data">
            <thead><tr><th>Inspection date</th><th>Technician</th><th>Result</th><th>Next due</th><th>Notes</th></tr></thead>
            <tbody>
            @foreach ($asset['inspections'] as $inspection)
                <tr>
                    <td>{{ $inspection['date'] }}@if ($inspection['entered_in_error']) (entered in error)@endif</td>
                    <td>{{ $inspection['technician'] ?: 'Not recorded' }}</td>
                    <td>{{ ucfirst($inspection['result']) }}</td>
                    <td>{{ $inspection['next_due'] ?: 'Not recorded' }}</td>
                    <td>{{ $inspection['notes'] ?: 'None recorded' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @foreach ($asset['inspections'] as $inspection)
            @include('reports.partials.photos', ['inspection' => $inspection])
        @endforeach
    @endif
</div>
