@props(['series' => [], 'height' => 110])

@php
    // $series is [label => value]; the final column is emphasised as "today".
    $peak = max([1, ...array_map('floatval', array_values($series))]);
@endphp

<div class="barchart-frame">
    <div class="barchart-frame__axis" style="height: {{ $height }}px">
        <span>{{ (int) $peak }}</span>
        <span>{{ (int) round($peak * 2 / 3) }}</span>
        <span>{{ (int) round($peak / 3) }}</span>
        <span>0</span>
    </div>

    <div class="barchart" style="height: {{ $height }}px">
        @foreach ($series as $label => $value)
            <div class="barchart__col">
                <div class="barchart__bar {{ $loop->last ? 'barchart__bar--last' : '' }}"
                     style="height: {{ max(2, $value / $peak * ($height - 16)) }}px"
                     title="{{ $label }}: {{ $value }}"></div>
                <span class="barchart__tick">{{ $label }}</span>
            </div>
        @endforeach
    </div>
</div>
