@props([
    // ['X IPA 1' => [dayLabel => level, ...], ...] where level is 0-4 or a
    // status keyword (hadir/izin/sakit/alpa).
    'rows' => [],
    'labelWidth' => 90,
])

@php
    $columns = $rows ? array_keys(reset($rows)) : [];
    $template = $labelWidth . 'px repeat(' . max(1, count($columns)) . ', 1fr)';
@endphp

@if ($rows)
    <div class="heat" style="grid-template-columns: {{ $template }}">
        <span></span>
        @foreach ($columns as $column)
            <span class="heat__col-label">{{ $column }}</span>
        @endforeach

        @foreach ($rows as $label => $cells)
            <span class="heat__label">{{ $label }}</span>
            @foreach ($cells as $column => $level)
                <span class="heat__cell heat__cell--{{ is_numeric($level) ? 'l' . $level : $level }}"
                      title="{{ $label }} · {{ $column }}"></span>
            @endforeach
        @endforeach
    </div>
@else
    <p class="empty-state">Belum ada data untuk dipetakan.</p>
@endif
