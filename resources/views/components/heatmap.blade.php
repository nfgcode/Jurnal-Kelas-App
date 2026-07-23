@props([
    // ['X IPA 1' => [dayLabel => level, ...], ...] where level is 0-4 or a
    // status keyword (hadir/izin/sakit/alpa).
    'rows' => [],
    'labelWidth' => 90,
    // Optional drill-down: with $drillTipe set plus a row-label→id map and a
    // column-label→date map, each cell opens the dashboard detail modal for
    // that class on that day.
    'drillTipe' => null,
    'kelasIds' => [],
    'tanggalMap' => [],
])

@php
    $columns = $rows ? array_keys(reset($rows)) : [];
    // minmax keeps each cell legible; the grid scrolls inside .scroll-x rather
    // than crushing its columns once there are more days than fit.
    $template = $labelWidth . 'px repeat(' . max(1, count($columns)) . ', minmax(16px, 1fr))';
@endphp

@if ($rows)
    <div class="scroll-x">
        <div class="heat" style="grid-template-columns: {{ $template }}">
            <span></span>
            @foreach ($columns as $column)
                <span class="heat__col-label">{{ $column }}</span>
            @endforeach

            @foreach ($rows as $label => $cells)
                <span class="heat__label">{{ $label }}</span>
                @foreach ($cells as $column => $level)
                    @php $drill = $drillTipe && isset($kelasIds[$label]) && isset($tanggalMap[$column]); @endphp
                    <span class="heat__cell heat__cell--{{ is_numeric($level) ? 'l' . $level : $level }} {{ $drill ? 'is-clickable' : '' }}"
                          @if ($drill)
                              role="button" tabindex="0"
                              data-detail-tipe="{{ $drillTipe }}"
                              data-detail-kelas="{{ $kelasIds[$label] }}"
                              data-detail-tanggal="{{ $tanggalMap[$column] }}"
                          @endif
                          title="{{ $label }} · {{ $column }}"></span>
                @endforeach
            @endforeach
        </div>
    </div>
@else
    <p class="empty-state">Belum ada data untuk dipetakan.</p>
@endif
