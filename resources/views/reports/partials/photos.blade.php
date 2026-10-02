@if ($inspection['photos'] !== [])
    <p class="small">Inspection evidence · {{ $inspection['date'] }}</p>
    @foreach ($inspection['photos'] as $photo)
        <div class="photo">
            @if ($photo['src'])
                <img src="{{ $photo['src'] }}" alt="Inspection evidence">
            @else
                <p class="muted">Image unavailable</p>
            @endif
            <p class="small">{{ $photo['name'] }}</p>
        </div>
    @endforeach
@endif
