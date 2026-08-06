@extends('layouts.app')

@section('title', 'Cadangan Data')

@push('styles')
<style>
    /* Restore-mode picker: tap-a-card radios (same :has(input:checked) pattern as
       the QR pickers), with the destructive "Ganti total" tinted red. */
    .mode-list {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 12px;
        margin: 4px 0 14px;
    }

    .mode-card {
        position: relative;
        display: flex;
        gap: 12px;
        padding: 14px 16px;
        border: 1.5px solid var(--n-300);
        border-radius: var(--radius-card);
        background: var(--n-100);
        cursor: pointer;
        transition: border-color 0.15s ease, background 0.15s ease;
    }

    .mode-card:hover { border-color: var(--p-300); }
    .mode-card:has(input:checked) { border-color: var(--p-400); background: var(--p-100); }
    .mode-card--danger:has(input:checked) { border-color: var(--red-100); background: #fdecee; }
    .mode-card:has(input:focus-visible) { outline: 2px solid var(--p-400); outline-offset: 2px; }
    .mode-card input { position: absolute; opacity: 0; pointer-events: none; }

    .mode-card__dot {
        flex: none;
        width: 20px;
        height: 20px;
        margin-top: 1px;
        border-radius: 50%;
        border: 2px solid var(--n-300);
        display: grid;
        place-items: center;
    }

    .mode-card__dot::after {
        content: "";
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: var(--p-400);
        transform: scale(0);
        transition: transform 0.15s ease;
    }

    .mode-card:has(input:checked) .mode-card__dot { border-color: var(--p-400); }
    .mode-card:has(input:checked) .mode-card__dot::after { transform: scale(1); }
    .mode-card--danger:has(input:checked) .mode-card__dot { border-color: var(--red-100); }
    .mode-card--danger:has(input:checked) .mode-card__dot::after { background: var(--red-100); }

    .mode-card__title { font-weight: 600; font-size: 13px; color: var(--n-1000); }
    .mode-card__desc { display: block; font-size: 11px; color: var(--n-600); margin-top: 3px; }

    .file-input {
        display: block;
        width: 100%;
        font-size: 12px;
        padding: 9px 10px;
        border: 1px solid var(--n-300);
        border-radius: var(--radius-control);
        background: var(--n-100);
    }

    /* Which tables go into the backup — tick-a-card, same family as the QR pickers. */
    .pilih-data {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 8px;
        margin: 4px 0 14px;
    }

    .pilih-data__card {
        position: relative;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        border: 1.5px solid var(--n-300);
        border-radius: var(--radius-control);
        background: var(--n-100);
        cursor: pointer;
        transition: border-color 0.15s ease, background 0.15s ease;
    }

    .pilih-data__card:hover { border-color: var(--p-300); }
    .pilih-data__card:has(input:checked) { border-color: var(--p-400); background: var(--p-100); }
    .pilih-data__card input { position: absolute; opacity: 0; pointer-events: none; }

    .pilih-data__check {
        flex: none;
        width: 20px;
        height: 20px;
        border-radius: 5px;
        border: 2px solid var(--n-300);
        background: var(--n-100);
        display: grid;
        place-items: center;
    }

    .pilih-data__check::after {
        content: "";
        width: 5px;
        height: 10px;
        margin-top: -2px;
        border: solid #fff;
        border-width: 0 2.5px 2.5px 0;
        transform: rotate(45deg) scale(0);
        transition: transform 0.15s ease;
    }

    .pilih-data__card:has(input:checked) .pilih-data__check { background: var(--p-400); border-color: var(--p-400); }
    .pilih-data__card:has(input:checked) .pilih-data__check::after { transform: rotate(45deg) scale(1); }

    .pilih-data__body { min-width: 0; }
    .pilih-data__name { display: block; font-size: 12px; font-weight: 600; color: var(--n-1000); }
    .pilih-data__count { display: block; font-size: 10px; color: var(--n-600); }

    .linkish { background: none; border: 0; padding: 0; color: var(--p-300); font-size: 11px; cursor: pointer; }
    .linkish:hover { text-decoration: underline; }
</style>
@endpush

@section('content')
    <x-page-head
        title="Cadangan & Pemulihan Data"
        sub="Ekspor dan impor seluruh data untuk pindah server atau pemulihan bila server bermasalah. Khusus admin." />

    <div class="grid-row grid-row--2">
        <x-card title="Ekspor / Backup">
            @php
                $label = [
                    'mata_pelajaran' => 'Mata Pelajaran',
                    'users' => 'Pengguna',
                    'kelas' => 'Kelas',
                    'jadwal' => 'Jadwal',
                    'jurnal' => 'Jurnal',
                    'presensi' => 'Presensi',
                    'presensi_log' => 'Log Presensi',
                    'pengumuman' => 'Pengumuman',
                    'laporan_error' => 'Laporan Error',
                    'personal_access_tokens' => 'Token API',
                ];
            @endphp

            <p class="field__hint mb-3">
                Pilih data yang ingin dicadangkan, lalu unduh. Simpan berkas di tempat aman —
                di dalamnya ada data pribadi pengguna dan kata sandi (ter-hash).
            </p>

            <form method="GET" id="formEkspor">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="field__label">Data yang diekspor</label>
                    <span>
                        <button type="button" class="linkish" onclick="pilihTabel(true)">Pilih semua</button>
                        ·
                        <button type="button" class="linkish" onclick="pilihTabel(false)">Kosongkan</button>
                    </span>
                </div>

                <div class="pilih-data">
                    @foreach ($ringkasan as $tabel => $jumlah)
                        <label class="pilih-data__card">
                            <input type="checkbox" name="tabel[]" value="{{ $tabel }}" checked>
                            <span class="pilih-data__check"></span>
                            <span class="pilih-data__body">
                                <span class="pilih-data__name">{{ $label[$tabel] ?? $tabel }}</span>
                                <span class="pilih-data__count">{{ number_format($jumlah, 0, ',', '.') }} baris</span>
                            </span>
                        </label>
                    @endforeach
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <button class="btn-hifi" type="submit" formaction="{{ route('admin.cadangan.json') }}">
                        <x-ikon nama="shield-lock" /> Unduh Backup (JSON)
                    </button>
                    <button class="btn-hifi btn-hifi--ghost" type="submit" formaction="{{ route('admin.cadangan.xlsx') }}">
                        <x-ikon nama="clipboard-data" /> Unduh Excel (XLSX)
                    </button>
                </div>
            </form>

            <p class="field__hint mt-3">
                <strong>JSON</strong> = berkas untuk memulihkan (restore) — memuat tabel terpilih
                beserta relasi & ID, diunduh <strong>terkompresi</strong> (<code>.json.gz</code>,
                ±20× lebih kecil). <strong>XLSX</strong> hanya untuk dibaca/diedit dan mencakup
                tabel master saja (presensi &amp; token tidak ikut). Tanpa mencentang apa pun = semua data.
            </p>
        </x-card>

        <x-card title="Impor / Pulihkan (dari JSON)">
            <form method="POST" action="{{ route('admin.cadangan.pulihkan') }}" enctype="multipart/form-data">
                @csrf

                <label class="field__label d-block mb-1">Berkas cadangan (.json atau .json.gz)</label>
                <input class="file-input mb-1" type="file" name="berkas" accept=".json,.gz,application/json,application/gzip" required>
                @error('berkas')<span class="field__error d-block mb-2">{{ $message }}</span>@enderror

                <label class="field__label d-block mt-3 mb-1">Cara memulihkan</label>
                <div class="mode-list">
                    <label class="mode-card">
                        <input type="radio" name="mode" value="gabung" checked>
                        <span class="mode-card__dot"></span>
                        <span>
                            <span class="mode-card__title">Gabung (aman)</span>
                            <span class="mode-card__desc">Tambah data baru & perbarui baris yang ID-nya cocok. Tidak menghapus apa pun.</span>
                        </span>
                    </label>
                    <label class="mode-card mode-card--danger">
                        <input type="radio" name="mode" value="ganti">
                        <span class="mode-card__dot"></span>
                        <span>
                            <span class="mode-card__title">Ganti total</span>
                            <span class="mode-card__desc">Kosongkan tabel lalu isi ulang dari berkas — hasil persis isi backup. Menghapus data saat ini.</span>
                        </span>
                    </label>
                </div>

                <label class="checkbox-row">
                    <input type="checkbox" name="konfirmasi" value="1">
                    Saya paham pemulihan dapat menimpa atau menghapus data yang ada.
                </label>
                @error('konfirmasi')<span class="field__error d-block mt-1">{{ $message }}</span>@enderror

                <div class="d-flex justify-content-end mt-3">
                    <button class="btn-hifi" type="submit">Pulihkan Data</button>
                </div>
            </form>

            <p class="field__hint mt-3">
                <strong>Penting:</strong> lakukan saat aplikasi tidak sedang dipakai. Seluruh proses
                berjalan dalam satu transaksi — bila gagal di tengah jalan, tidak ada perubahan yang
                tersimpan. Untuk berkas besar, server harus mengizinkan unggahan besar (PHP
                <code>upload_max_filesize</code>/<code>post_max_size</code> dan nginx
                <code>client_max_body_size</code>).
            </p>
        </x-card>
    </div>

    @push('scripts')
    <script>
        function pilihTabel(nyala) {
            document.querySelectorAll('#formEkspor input[type=checkbox]').forEach((c) => { c.checked = nyala; });
        }
    </script>
    @endpush
@endsection
