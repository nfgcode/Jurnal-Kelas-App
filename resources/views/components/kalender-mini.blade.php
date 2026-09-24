@props([
    // From DashboardController::mingguKalender(): tanggal, jumlah, belum.
    'minggu',
    'tanggal',
    // "jadwal" / "jadwal mengajar" — what one lesson is called on this screen.
    'satuan' => 'jadwal',
    // Status line for a day with open work, e.g. "%d perlu ditandai".
    'belumLabel' => '%d belum selesai',
    'rute' => 'dashboard',
])

@php
    $senin = $minggu[0]['tanggal'];
    $ke = fn ($t) => route($rute, ['tanggal' => $t->toDateString()]);
@endphp

{{-- Week at a glance beside today's table. On narrow screens the same markup
     folds into a seven-pill strip (see .kalender-mini in app.scss). --}}
<section {{ $attributes->class('card-hifi kalender-mini') }} aria-label="Kalender minggu ini">
    <div class="kalender-mini__head">
        <div>
            {{-- A bare "Minggu" would read as Sunday, hence "Pekan" for other weeks. --}}
            <h3 class="kalender-mini__judul">{{ $senin->isCurrentWeek() ? 'Minggu Ini' : 'Kalender Pekan' }}</h3>
            <span class="kalender-mini__rentang">
                {{ $senin->translatedFormat('j') }} – {{ $senin->copy()->addDays(6)->translatedFormat('j F Y') }}
            </span>
        </div>
        <div class="kalender-mini__nav">
            <a href="{{ $ke($senin->copy()->subWeek()) }}" aria-label="Minggu sebelumnya"><x-ikon nama="chevron-left" /></a>
            <a href="{{ $ke($senin->copy()->addWeek()) }}" aria-label="Minggu berikutnya"><x-ikon nama="chevron-right" /></a>
        </div>
    </div>

    <div class="kalender-mini__daftar">
        @foreach ($minggu as $hari)
            @php
                $t = $hari['tanggal'];
                $libur = $hari['jumlah'] === 0;
                $dipilih = $t->isSameDay($tanggal);
                $nada = match (true) {
                    $libur || $hari['belum'] === null => null,
                    $hari['belum'] > 0 => $t->isToday() ? 'kuning' : 'merah',
                    default => 'hijau',
                };
                $status = match (true) {
                    $libur => 'Tidak ada jadwal',
                    $hari['belum'] === null => 'Akan datang',
                    $hari['belum'] > 0 => sprintf($belumLabel, $hari['belum']),
                    default => 'Semua selesai',
                };
            @endphp
            <a href="{{ $ke($t) }}"
               @class([
                   'kalender-mini__hari',
                   'is-dipilih' => $dipilih,
                   'is-hari-ini' => $t->isToday(),
                   'is-libur' => $libur,
               ])
               @if ($dipilih) aria-current="date" @endif>
                <span class="kalender-mini__tgl">
                    <span class="kalender-mini__dow">{{ $t->translatedFormat('D') }}</span>
                    <span class="kalender-mini__num">{{ $t->day }}</span>
                    @if ($nada)<span class="kalender-mini__titik kalender-mini__titik--{{ $nada }}"></span>@endif
                </span>
                <span class="kalender-mini__info">
                    <span class="kalender-mini__jumlah">{{ $libur ? 'Libur' : $hari['jumlah'].' '.$satuan }}</span>
                    <span class="kalender-mini__status {{ $nada ? 'is-'.$nada : '' }}">{{ $status }}</span>
                </span>
                @unless ($dipilih || $libur)
                    <span class="kalender-mini__chev"><x-ikon nama="chevron-right" /></span>
                @endunless
            </a>
        @endforeach
    </div>

    <div class="kalender-mini__foot">Pilih hari untuk melihat jadwalnya</div>
</section>
