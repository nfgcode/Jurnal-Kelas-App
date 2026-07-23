@props(['periode'])

@php
    // Preset menu, in the same order Periode::PRESETS declares them.
    $opsi = [
        'hari_ini' => 'Hari Ini',
        'minggu_ini' => 'Minggu Ini',
        'minggu_lalu' => 'Minggu Lalu',
        'bulan_ini' => 'Bulan Ini',
        'bulan_lalu' => 'Bulan Lalu',
        '30_hari' => '30 Hari Terakhir',
        'tahun_ini' => 'Tahun Ini',
        'custom' => 'Rentang Kustom',
    ];

    // Any other query params (report filters, search) ride along as hidden
    // fields so changing the period never drops them. The period's own fields
    // and the pager are excluded.
    $bawaan = collect(request()->except(['preset', 'mulai', 'selesai', 'page']))
        ->filter(fn ($value) => is_scalar($value));
@endphp

<form method="GET" class="periode-filter d-flex align-items-center gap-2 flex-wrap">
    @foreach ($bawaan as $nama => $nilai)
        <input type="hidden" name="{{ $nama }}" value="{{ $nilai }}">
    @endforeach

    <select class="select-hifi" name="preset" aria-label="Periode"
            onchange="if (this.value === 'custom') { this.closest('form').querySelector('.periode-filter__custom').classList.remove('d-none'); } else { this.form.submit(); }">
        @foreach ($opsi as $nilai => $teks)
            <option value="{{ $nilai }}" @selected($periode->preset === $nilai)>{{ $teks }}</option>
        @endforeach
    </select>

    <span class="periode-filter__custom d-flex align-items-center gap-1 {{ $periode->isCustom() ? '' : 'd-none' }}">
        <input class="input-hifi" type="date" name="mulai" value="{{ $periode->mulaiString() }}"
               max="{{ $periode->selesaiString() }}" onchange="this.form.submit()" aria-label="Tanggal mulai">
        <span class="is-muted">–</span>
        <input class="input-hifi" type="date" name="selesai" value="{{ $periode->selesaiString() }}"
               onchange="this.form.submit()" aria-label="Tanggal selesai">
    </span>
</form>
