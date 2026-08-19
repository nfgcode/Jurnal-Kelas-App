@extends('layouts.app')

@section('title', 'Isi via QR')

@push('styles')
<style>
    /* Tap-a-card picker for the scanned class: bigger touch targets than a
       dropdown, and each option shows its own "sudah diisi hari ini" status.
       Selected state via :has(input:checked) — same pattern as the .seg control. */
    .qr-confirm { max-width: 560px; }

    .pick-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin: 0 0 18px;
        padding: 0;
        border: 0;
    }

    .pick-card {
        position: relative;
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 16px;
        border: 1.5px solid var(--n-300);
        border-radius: var(--radius-card);
        background: var(--n-100);
        cursor: pointer;
        transition: border-color 0.15s ease, background 0.15s ease;
    }

    .pick-card:hover { border-color: var(--p-300); }
    .pick-card:has(input:checked) { border-color: var(--p-400); background: var(--p-100); }
    .pick-card:has(input:focus-visible) { outline: 2px solid var(--p-400); outline-offset: 2px; }
    .pick-card input { position: absolute; opacity: 0; pointer-events: none; }

    .pick-card__dot {
        flex: none;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        border: 2px solid var(--n-300);
        display: grid;
        place-items: center;
    }

    .pick-card__dot::after {
        content: "";
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: var(--p-400);
        transform: scale(0);
        transition: transform 0.15s ease;
    }

    .pick-card:has(input:checked) .pick-card__dot { border-color: var(--p-400); }
    .pick-card:has(input:checked) .pick-card__dot::after { transform: scale(1); }

    .pick-card__body { flex: 1 1 auto; min-width: 0; }

    .pick-card__title {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        font-weight: 600;
        font-size: 13px;
        color: var(--n-1000);
    }

    .pick-card__meta { display: block; font-size: 11px; color: var(--n-600); margin-top: 3px; }

    .pick-card__rec {
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #fff;
        background: var(--p-300);
        border-radius: 20px;
        padding: 2px 8px;
    }

    .pick-card__flag { flex: none; }
</style>
@endpush

@section('content')
    <x-page-head
        title="Kelas {{ $kelas->nama_kelas }}"
        :sub="collect([$kelas->ruang, \Illuminate\Support\Carbon::today()->translatedFormat('l, j F Y')])->filter()->join(' · ')" />

    <div class="qr-confirm">
        <x-card title="Konfirmasi Pengisian">
            @if ($jadwals->isEmpty())
                <p class="empty-state">
                    Anda tidak mengajar di kelas {{ $kelas->nama_kelas }}, jadi tidak ada jurnal
                    yang bisa diisi dari QR ini. Pastikan Anda memindai QR ruang kelas yang benar.
                </p>
                <a class="btn-hifi btn-hifi--ghost" href="{{ route('dashboard') }}">Ke Dashboard</a>
            @else
                <p class="field__hint mb-3">
                    Anda memindai QR kelas <strong>{{ $kelas->nama_kelas }}</strong>. Ketuk mata
                    pelajaran yang Anda ajar di kelas ini, lalu lanjut mengisi jurnal. Presensi siswa
                    diisi terpisah, sekali sehari, oleh ketua kelas.
                </p>

                <form method="GET" action="{{ route('jurnal.create') }}">
                    <fieldset class="pick-list">
                        @foreach ($jadwals as $jadwal)
                            @php
                                $terisi = in_array($jadwal->id, $sudahDiisi, true);
                                $isRek = $rekomendasi?->id === $jadwal->id;
                            @endphp
                            <label class="pick-card">
                                <input type="radio" name="jadwal_id" value="{{ $jadwal->id }}" @checked($isRek) required>
                                <span class="pick-card__dot"></span>
                                <span class="pick-card__body">
                                    <span class="pick-card__title">
                                        {{ $jadwal->mataPelajaran?->nama }}
                                        @if ($isRek)<span class="pick-card__rec">Disarankan</span>@endif
                                    </span>
                                    <span class="pick-card__meta">{{ $jadwal->hari }} · JP {{ $jadwal->jpLabel() }}</span>
                                </span>
                                <span class="pick-card__flag">
                                    @if ($jadwal->hari === $hariIni)
                                        <x-chip :tone="$terisi ? 'green' : 'yellow'" :label="$terisi ? 'Terisi' : 'Belum'" />
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </fieldset>

                    <div class="d-flex justify-content-end gap-2">
                        <a class="btn-hifi btn-hifi--ghost" href="{{ route('dashboard') }}">Batal</a>
                        <button class="btn-hifi" type="submit">Isi Jurnal &amp; Presensi →</button>
                    </div>
                </form>
            @endif
        </x-card>
    </div>
@endsection
