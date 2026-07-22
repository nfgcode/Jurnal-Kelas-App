@props([
    // [label => count], rendered as label · bar · count · share-of-total.
    'items' => [],
    'tones' => [],
])

@php $total = array_sum($items) ?: 1; @endphp

@foreach ($items as $label => $count)
    <div class="breakdown">
        <span class="breakdown__label">{{ $label }}</span>
        <span class="stack">
            <span class="stack__seg--{{ $tones[$label] ?? 'hadir' }}" style="width: {{ $count / $total * 100 }}%"></span>
        </span>
        <span class="breakdown__value">{{ number_format($count, 0, ',', '.') }}</span>
        <span class="breakdown__pct">{{ round($count / $total * 100) }}%</span>
    </div>
@endforeach
