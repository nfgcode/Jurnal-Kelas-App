@props([
    // The day on screen.
    'tanggal',
    // Route the picker reloads with ?tanggal=.
    'rute' => 'dashboard',
])

@php
    $ke = fn ($t) => route($rute, ['tanggal' => $t->toDateString()]);
@endphp

{{-- ‹ date › plus "Hari ini". The date itself is a native date input laid
     over the label, so tapping it opens the platform's own calendar. --}}
<form method="GET" action="{{ route($rute) }}" {{ $attributes->class('pilih-tanggal') }}>
    <div class="pilih-tanggal__grup">
        <a class="pilih-tanggal__panah" href="{{ $ke($tanggal->copy()->subDay()) }}" aria-label="Hari sebelumnya">
            <x-ikon nama="chevron-left" />
        </a>
        <label class="pilih-tanggal__aktif">
            <x-ikon nama="calendar3" />
            <span class="pilih-tanggal__label">{{ $tanggal->translatedFormat('l, j F Y') }}</span>
            <span class="pilih-tanggal__label pilih-tanggal__label--pendek">{{ $tanggal->translatedFormat('D, j M Y') }}</span>
            <input class="pilih-tanggal__input" type="date" name="tanggal"
                   value="{{ $tanggal->toDateString() }}" aria-label="Pilih tanggal" data-kirim-otomatis>
        </label>
        <a class="pilih-tanggal__panah" href="{{ $ke($tanggal->copy()->addDay()) }}" aria-label="Hari berikutnya">
            <x-ikon nama="chevron-right" />
        </a>
    </div>

    <a class="pilih-tanggal__hari-ini {{ $tanggal->isToday() ? 'is-aktif' : '' }}" href="{{ route($rute) }}">Hari ini</a>
</form>
