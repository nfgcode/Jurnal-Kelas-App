@props([
    // [label => count], rendered as label · bar · count · share-of-total.
    'items' => [],
    'tones' => [],
    // Optional drill-down: when $drillTipe is set and a row's label appears in
    // $drillKeys, that row opens the dashboard detail modal for [tipe, status].
    'drillTipe' => null,
    'drillKeys' => [],
])

@php $total = array_sum($items) ?: 1; @endphp

@foreach ($items as $label => $count)
    @php $drill = $drillTipe && isset($drillKeys[$label]); @endphp
    <div class="breakdown {{ $drill ? 'is-clickable' : '' }}"
         @if ($drill)
             role="button" tabindex="0"
             data-detail-tipe="{{ $drillTipe }}" data-detail-status="{{ $drillKeys[$label] }}"
         @endif>
        <span class="breakdown__label">{{ $label }}</span>
        <span class="stack">
            <span class="stack__seg--{{ $tones[$label] ?? 'hadir' }}" style="width: {{ $count / $total * 100 }}%"></span>
        </span>
        <span class="breakdown__value">{{ number_format($count, 0, ',', '.') }}</span>
        <span class="breakdown__pct">{{ round($count / $total * 100) }}%</span>
    </div>
@endforeach
