@props([
    'series' => [],
    'height' => 110,
    'drill' => null,
    // Optional traffic light: a bar at or above the target is green, one within
    // a third below it yellow, anything lower red — with a dashed target line
    // and a legend. Without a target the chart keeps its single green.
    'target' => null,
])

@php
    // Below 3 the three bands collapse into each other ("1–0 Perlu perhatian"),
    // so a near-empty period draws the plain chart instead.
    $target = $target !== null && $target >= 3 ? (int) $target : null;
    // $series is a list of ['key','label','value'] rows; the last is "today".
    $values = array_map(fn ($row) => (float) ($row['value'] ?? 0), $series);
    $peak = max([1, ...$values, (float) ($target ?? 0)]);
    $plot = $height - 16;   // the tick labels take the bottom 16px
    $waspada = $target ? $target * 2 / 3 : null;
    // A month of daily bars cannot keep 6px gaps inside a third-width card.
    $padat = count($series) > 16;
    $nada = fn ($v) => match (true) {
        $target === null => null,
        // Nothing written usually means no school (Sunday, a holiday): grey,
        // not a red alarm.
        $v <= 0 => 'kosong',
        $v >= $target => 'baik',
        $v >= $waspada => 'waspada',
        default => 'kurang',
    };
@endphp

<div class="barchart-frame">
    <div class="barchart-frame__axis" style="height: {{ $height }}px">
        <span>{{ (int) $peak }}</span>
        <span>{{ (int) round($peak * 2 / 3) }}</span>
        <span>{{ (int) round($peak / 3) }}</span>
        <span>0</span>
    </div>

    <div class="barchart {{ $padat ? 'barchart--padat' : '' }}" style="height: {{ $height }}px">
        @if ($target)
            <span class="barchart__target" style="bottom: {{ 16 + $target / $peak * $plot }}px">
                <span class="barchart__target-label">Target {{ $target }}</span>
            </span>
        @endif

        @foreach ($series as $row)
            @php $n = $nada((float) ($row['value'] ?? 0)); @endphp
            <div class="barchart__col">
                <div class="barchart__bar {{ $n ? 'barchart__bar--'.$n : ($loop->last ? 'barchart__bar--last' : '') }} {{ $drill ? 'is-clickable' : '' }}"
                     style="height: {{ max(2, ($row['value'] ?? 0) / $peak * $plot) }}px"
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

@if ($target)
    <x-legend class="mt-2" :swatch="true" :items="[
        '≥ ' . $target . ' Baik' => 'var(--green-200)',
        (int) ceil($waspada) . '–' . ($target - 1) . ' Perlu perhatian' => 'var(--yellow-200)',
        '< ' . (int) ceil($waspada) . ' Kurang' => 'var(--red-100)',
    ]" />
@endif
