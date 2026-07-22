@props([
    'label',
    'value',
    // Signed number or percentage string; null renders no trend at all.
    'delta' => null,
    'caption' => null,
    // 12 recent values driving the sparkline. Fewer is fine, more is trimmed.
    'spark' => [],
])

@php
    $spark = array_slice(array_values((array) $spark), -12);
    $peak = max([1, ...array_map('floatval', $spark)]);
    // A metric with no daily series renders as a full-height empty track,
    // which reads as "flat" rather than as a broken line of stubs.
    $tanpaSeri = array_sum($spark) == 0;

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
    <span class="kpi__value">{{ $value }}</span>

    <div class="kpi__row">
        @if ($spark)
            <span class="spark">
                @foreach ($spark as $point)
                    <span class="spark__bar {{ $point > 0 ? ($loop->last ? 'spark__bar--last' : '') : 'spark__bar--empty' }}"
                          style="height: {{ $tanpaSeri ? 22 : max(2, $point / $peak * 22) }}px"></span>
                @endforeach
            </span>
        @elseif ($caption)
            <span class="kpi__caption">{{ $caption }}</span>
        @else
            <span></span>
        @endif

        @if ($delta !== null)
            <span class="delta delta--{{ $direction }}">{{ $arrow }} {{ $delta }}</span>
        @endif
    </div>

    @if ($spark && $caption)
        <span class="kpi__caption">{{ $caption }}</span>
    @endif
</div>
