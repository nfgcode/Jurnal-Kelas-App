@props(['percent' => 0, 'showValue' => false])

@php
    $pct = max(0, min(100, (float) $percent));
    // Olive warns, red alarms — the thresholds the recap screens use.
    $tone = $pct >= 85 ? '' : ($pct >= 70 ? ' meter__fill--warn' : ' meter__fill--bad');
@endphp

<span class="meter-cell">
    <span class="meter"><span class="meter__fill{{ $tone }}" style="width: {{ $pct }}%"></span></span>
    @if ($showValue)<span class="is-strong">{{ round($pct) }}%</span>@endif
</span>
