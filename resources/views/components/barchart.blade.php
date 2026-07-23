@props(['series' => [], 'height' => 110, 'drill' => null])

@php
    // $series is a list of ['key','label','value'] rows; the last is "today".
    $values = array_map(fn ($row) => (float) ($row['value'] ?? 0), $series);
    $peak = max([1, ...$values]);
@endphp

<div class="barchart-frame">
    <div class="barchart-frame__axis" style="height: {{ $height }}px">
        <span>{{ (int) $peak }}</span>
        <span>{{ (int) round($peak * 2 / 3) }}</span>
        <span>{{ (int) round($peak / 3) }}</span>
        <span>0</span>
    </div>

    <div class="barchart" style="height: {{ $height }}px">
        @foreach ($series as $row)
            <div class="barchart__col">
                <div class="barchart__bar {{ $loop->last ? 'barchart__bar--last' : '' }} {{ $drill ? 'is-clickable' : '' }}"
                     style="height: {{ max(2, ($row['value'] ?? 0) / $peak * ($height - 16)) }}px"
                     @if ($drill)
                         role="button" tabindex="0"
                         data-detail-tipe="{{ $drill }}" data-detail-tanggal="{{ $row['key'] ?? '' }}"
                     @endif
                     title="{{ $row['label'] ?? '' }}: {{ $row['value'] ?? 0 }}"></div>
                <span class="barchart__tick">{{ $row['label'] ?? '' }}</span>
            </div>
        @endforeach
    </div>
</div>
