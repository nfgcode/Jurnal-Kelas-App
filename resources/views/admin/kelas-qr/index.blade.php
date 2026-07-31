@extends('layouts.app')

@section('title', 'Cetak QR Kelas')

@push('styles')
<style>
    .qr-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
        gap: 14px;
    }
    .qr-card {
        border: 1px solid var(--n-200);
        border-radius: var(--radius-card);
        background: var(--n-100);
        padding: 16px;
        text-align: center;
    }
    .qr-card__title { font-size: 15px; font-weight: 700; margin: 0 0 2px; }
    .qr-card__room { font-size: 11px; color: var(--n-600); margin-bottom: 10px; }
    .qr-card__svg { width: 190px; height: 190px; margin: 0 auto; }
    .qr-card__svg svg { width: 100%; height: 100%; }
    .qr-card__url { font-size: 8.5px; color: var(--n-500); word-break: break-all; margin-top: 8px; }
    .qr-card__hint { font-size: 10px; color: var(--n-700); margin-top: 6px; }

    /* Class picker: a plain checkbox list. With a couple of dozen rombel this
       stays scannable, and unlike a multi-select every choice is visible at once. */
    .pilih-kelas {
        display: flex;
        flex-wrap: wrap;
        gap: 6px 14px;
        margin: 10px 0 12px;
    }
    .pilih-kelas label {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        cursor: pointer;
    }
    .pilih-kelas input { width: 15px; height: 15px; cursor: pointer; }

    @media print {
        .sidebar, .topbar, .sidebar-scrim, .page-head__actions,
        .card-hifi, .pilih-kelas { display: none !important; }
        .main { margin-left: 0 !important; }
        .content { padding: 0 !important; }
        .qr-card { break-inside: avoid; page-break-inside: avoid; }
        .qr-grid { grid-template-columns: repeat(3, 1fr); }
    }
</style>
@endpush

@section('content')
    <x-page-head
        title="Cetak QR Kelas"
        :sub="($dipilih === [] ? 'Semua kelas' : $daftar->count() . ' kelas terpilih') . ' dari ' . $semuaKelas->count() . ' · tempel di tiap ruang rombel'">
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.kelas-qr.pdf', request()->query()) }}">
            Unduh PDF
        </a>
        <button type="button" class="btn-hifi" onclick="window.print()">
            <i class="bi bi-printer"></i> Cetak
        </button>
    </x-page-head>

    <x-card title="Pilih Kelas">
        {{-- No checkbox ticked means every class: reprinting the whole set is the
             common case, and the empty state should not be an empty sheet. --}}
        <form method="GET" id="formPilihKelas">
            <div class="pilih-kelas">
                @foreach ($semuaKelas as $k)
                    <label>
                        <input type="checkbox" name="kelas_id[]" value="{{ $k->id }}"
                               @checked(in_array($k->id, $dipilih, true))>
                        {{ $k->nama_kelas }}
                    </label>
                @endforeach
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <button class="btn-hifi" type="submit">Tampilkan</button>
                <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.kelas-qr.index') }}">Semua Kelas</a>
                <button class="btn-hifi btn-hifi--ghost" type="button"
                        onclick="document.querySelectorAll('#formPilihKelas input[type=checkbox]').forEach(c => c.checked = false)">
                    Kosongkan Pilihan
                </button>
            </div>
        </form>
    </x-card>

    <x-card>
        <p class="field__hint mb-2">
            Setiap QR mengarah ke halaman pengisian jurnal khusus kelasnya, dan hanya bisa
            diakses oleh guru yang login. Guru cukup memindai QR di ruang kelas, login, lalu
            langsung mengisi jurnal dan presensi kelas itu.
        </p>
        <p class="field__hint">
            <strong>Penting:</strong> agar QR bisa dibuka dari HP, alamat aplikasi
            (<code>APP_URL</code>) harus di-set ke alamat jaringan lokal sekolah
            (mis. <code>http://192.168.1.10:8888</code>), bukan <code>localhost</code>.
            Alamat tujuan tiap QR tercetak kecil di bawahnya untuk verifikasi.
        </p>
    </x-card>

    <div class="qr-grid">
        @foreach ($daftar as $item)
            <div class="qr-card">
                <p class="qr-card__title">{{ $item['kelas']->nama_kelas }}</p>
                <p class="qr-card__room">Ruang {{ $item['kelas']->ruang ?? '—' }}</p>
                <div class="qr-card__svg">{!! $item['svg'] !!}</div>
                <p class="qr-card__hint">Scan untuk isi jurnal &amp; presensi (khusus guru)</p>
                <p class="qr-card__url">{{ $item['url'] }}</p>
            </div>
        @endforeach
    </div>
@endsection
