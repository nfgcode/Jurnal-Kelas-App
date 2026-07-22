@props([
    // [label => css color], rendered as dots by default or swatches when
    // the legend describes filled areas rather than series.
    'items' => [],
    'swatch' => false,
])

<div class="legend">
    @foreach ($items as $label => $color)
        <span class="legend__item">
            <span class="{{ $swatch ? 'legend__swatch' : 'legend__dot' }}" style="background: {{ $color }}"></span>
            {{ $label }}
        </span>
    @endforeach
</div>
