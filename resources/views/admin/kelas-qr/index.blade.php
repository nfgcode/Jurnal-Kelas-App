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

    /* Class picker: each rombel is its own tappable card with a clear check tick,
       so a non-technical admin can see at a glance what is selected. Selected
       state via :has(input:checked) — same pattern as the guru's QR picker. */
    .pilih-kelas {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(148px, 1fr));
        gap: 10px;
        margin: 4px 0 16px;
    }

    .pilih-kelas__card {
        position: relative;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 14px;
        border: 1.5px solid var(--n-300);
        border-radius: var(--radius-control);
        background: var(--n-100);
        cursor: pointer;
        transition: border-color 0.15s ease, background 0.15s ease;
    }

    .pilih-kelas__card:hover { border-color: var(--p-300); }
    .pilih-kelas__card:has(input:checked) { border-color: var(--p-400); background: var(--p-100); }
    .pilih-kelas__card:has(input:focus-visible) { outline: 2px solid var(--p-400); outline-offset: 2px; }
    .pilih-kelas__card input { position: absolute; opacity: 0; pointer-events: none; }

    .pilih-kelas__check {
        flex: none;
        width: 22px;
        height: 22px;
        border-radius: 6px;
        border: 2px solid var(--n-300);
        background: var(--n-100);
        display: grid;
        place-items: center;
        transition: background 0.15s ease, border-color 0.15s ease;
    }

    /* Tick drawn in CSS so it needs no icon font; it scales in when checked. */
    .pilih-kelas__check::after {
        content: "";
        width: 6px;
        height: 11px;
        margin-top: -2px;
        border: solid #fff;
        border-width: 0 2.5px 2.5px 0;
        transform: rotate(45deg) scale(0);
        transition: transform 0.15s ease;
    }

    .pilih-kelas__card:has(input:checked) .pilih-kelas__check {
        background: var(--p-400);
        border-color: var(--p-400);
    }
    .pilih-kelas__card:has(input:checked) .pilih-kelas__check::after { transform: rotate(45deg) scale(1); }

    .pilih-kelas__name { font-size: 13px; font-weight: 600; color: var(--n-1000); }

    /* Print: one QR per A6 (105 x 148 mm) — four cards to a plain A4 sheet, with
       dashed cut guides. No special paper: the admin prints A4 and cuts it into
       four A6 cards. Everything but the QR grid is hidden so the cards land at
       the top-left and stay aligned to the A6 cut lines. */
    @media print {
        @page { size: A4 portrait; margin: 0; }

        .sidebar, .topbar, .sidebar-scrim { display: none !important; }
        .main { margin-left: 0 !important; }
        .content { padding: 0 !important; }
        .content > *:not(.qr-grid) { display: none !important; }

        .qr-grid {
            display: grid !important;
            grid-template-columns: repeat(2, 105mm);
            grid-auto-rows: 148mm;
            gap: 0;
            justify-content: center;
            margin: 0;
        }
        .qr-card {
            width: 105mm;
            height: 148mm;
            box-sizing: border-box;
            margin: 0;
            padding: 10mm;
            border: 0.3mm dashed #9a9a9a;
            border-radius: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .qr-card__title { font-size: 20pt; margin: 0 0 1mm; }
        .qr-card__room { font-size: 11pt; margin: 0 0 6mm; }
        .qr-card__svg { width: 64mm; height: 64mm; margin: 0 auto; }
        .qr-card__hint { font-size: 9pt; margin: 6mm 0 0; }
        .qr-card__url { font-size: 7pt; margin: 2mm 0 0; }
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
            <x-ikon nama="printer" /> Cetak
        </button>
    </x-page-head>

    @php $jurusanList = $semuaKelas->pluck('jurusan')->filter()->unique()->sort()->values(); @endphp

    <x-card title="Pilih Kelas">
        {{-- Search + grade/jurusan filters that hide picker cards client-side, so a
             large SMK's 40-plus rombel stay findable. Purely visual: the selects
             carry no name, so they never touch the print selection (kelas_id[]). --}}
        <div class="filter-bar mb-3">
            <label class="filter-bar__search">
                <x-ikon nama="search" />
                <input class="input-hifi" type="search" id="qrCari" placeholder="Cari nama kelas...">
            </label>

            <select class="select-hifi" id="qrTingkat" style="width: 150px">
                <option value="">Semua Tingkat</option>
                @foreach (['X', 'XI', 'XII'] as $t)
                    <option value="{{ $t }}">Tingkat {{ $t }}</option>
                @endforeach
            </select>

            <select class="select-hifi" id="qrJurusan" style="width: 200px" data-searchable>
                <option value="">Semua Jurusan</option>
                @foreach ($jurusanList as $j)
                    <option value="{{ $j }}">{{ $j }}</option>
                @endforeach
            </select>

            <span class="filter-bar__note" id="qrCocok"></span>
        </div>

        {{-- No checkbox ticked means every class: reprinting the whole set is the
             common case, and the empty state should not be an empty sheet. --}}
        <form method="GET" id="formPilihKelas">
            <div class="pilih-kelas">
                @foreach ($semuaKelas as $k)
                    <label class="pilih-kelas__card"
                           data-nama="{{ $k->nama_kelas }}"
                           data-tingkat="{{ $k->tingkat }}"
                           data-jurusan="{{ $k->jurusan }}">
                        <input type="checkbox" name="kelas_id[]" value="{{ $k->id }}"
                               @checked(in_array($k->id, $dipilih, true))>
                        <span class="pilih-kelas__check"></span>
                        <span class="pilih-kelas__name">{{ $k->nama_kelas }}</span>
                    </label>
                @endforeach
            </div>
            <p class="empty-state" id="qrKosong" hidden>Tidak ada kelas yang cocok dengan pencarian.</p>

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

    @push('scripts')
    <script>
        // Client-side picker filter: hide cards that do not match the search box
        // and the grade/jurusan selects. The selects may be upgraded to the
        // searchable control (app.js), which fires a normal `change` either way.
        (function () {
            const cari = document.getElementById('qrCari');
            const tingkat = document.getElementById('qrTingkat');
            const jurusan = document.getElementById('qrJurusan');
            const cocok = document.getElementById('qrCocok');
            const kosong = document.getElementById('qrKosong');
            const cards = Array.from(document.querySelectorAll('#formPilihKelas .pilih-kelas__card'));
            if (!cards.length) return;

            const terapkan = () => {
                const q = (cari.value || '').toLowerCase().trim();
                const t = tingkat.value;
                const j = jurusan.value;
                let n = 0;

                cards.forEach((c) => {
                    const match =
                        (!q || c.dataset.nama.toLowerCase().includes(q)) &&
                        (!t || c.dataset.tingkat === t) &&
                        (!j || c.dataset.jurusan === j);
                    c.style.display = match ? '' : 'none';
                    if (match) n++;
                });

                cocok.textContent = n + ' dari ' + cards.length + ' kelas';
                kosong.hidden = n > 0;
            };

            cari.addEventListener('input', terapkan);
            tingkat.addEventListener('change', terapkan);
            jurusan.addEventListener('change', terapkan);
            terapkan();
        })();
    </script>
    @endpush

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
