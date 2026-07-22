@props([
    'label',
    'value',
    // Signed number, or a pre-formatted string like "+4,1%".
    'delta' => null,
    'caption' => null,
])

@php
    $direction = 'flat';
    $arrow = '—';

    if (is_numeric($delta)) {
        $direction = $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat');
        $arrow = $delta > 0 ? '↑' : ($delta < 0 ? '↓' : '—');
        $delta = ($delta > 0 ? '+' : '') . $delta;
    }
@endphp

<div class="kpi">
    <span class="kpi__label">{{ $label }}</span>

    <div class="d-flex align-items-baseline gap-2">
        <span class="kpi__value">{{ $value }}</span>
        @if ($delta !== null)
            <span class="delta delta--{{ $direction }}">{{ $arrow }} {{ $delta }}</span>
        @endif
    </div>

    @if ($caption)
        <span class="kpi__caption">{{ $caption }}</span>
    @endif
</div>
