@extends('layouts.app')

@section('title', 'Kenaikan & Kelulusan')

@push('styles')
<style>
    /* Senior-Friendly Typography, Spacing & Touch Targets */
    .transisi-label {
        font-size: 15.5px;
        font-weight: 600;
        color: var(--n-900, #0f172a);
        margin-bottom: 6px;
        display: block;
    }
    .transisi-desc {
        font-size: 14.5px;
        line-height: 1.6;
        color: var(--n-700, #334155);
        margin-bottom: 14px;
    }
    .transisi-select {
        min-height: 44px;
        font-size: 15px;
        border: 1px solid var(--n-300, #cbd5e1);
        border-radius: var(--radius-sm, 6px);
        padding: 8px 14px;
        width: 100%;
        background-color: #fff;
        color: var(--n-900, #0f172a);
    }
    .transisi-select:focus-visible {
        outline: 3px solid #16a34a;
        outline-offset: 1px;
    }
    .transisi-btn {
        min-height: 44px;
        font-size: 15px;
        font-weight: 600;
        padding: 10px 22px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        border-radius: var(--radius-sm, 6px);
        cursor: pointer;
        text-decoration: none;
    }
    .transisi-btn:disabled, .transisi-btn[disabled] {
        opacity: 0.55;
        cursor: not-allowed;
        pointer-events: none;
    }
    .transisi-btn:focus-visible {
        outline: 3px solid #2563eb;
        outline-offset: 2px;
    }
    .transisi-callout {
        background: #eff6ff;
        border-left: 5px solid #2563eb;
        border-radius: 6px;
        padding: 16px 18px;
        margin-bottom: 22px;
    }
    .transisi-callout--warning {
        background: #fefce8;
        border-left-color: #ca8a04;
    }
    .transisi-badge-aksi {
        font-size: 12.5px;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 4px;
        display: inline-block;
        margin-bottom: 8px;
    }
    .transisi-badge-aksi--ubah {
        background: #fef2f2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
    .transisi-badge-aksi--aman {
        background: #f0fdf4;
        color: #166534;
        border: 1px solid #bbf7d0;
    }
    .checklist-summary-box {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 16px;
        background: #f8fafc;
        border: 1px solid var(--n-200, #e2e8f0);
        border-radius: var(--radius-card, 8px);
        margin-bottom: 16px;
    }
    .checklist-op-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .checklist-op-title {
        font-size: 14px;
        font-weight: 600;
        color: var(--n-800, #1e293b);
    }
</style>
@endpush

@section('content')
    <x-page-head
        title="Kenaikan Kelas & Kelulusan Siswa"
        sub="Kelola persiapan rombel kelas tujuan, simulasi kenaikan kelas, dan penetapan alumni antar tahun ajaran.">
        @if ($tahunAktif)
            <div class="d-inline-flex align-items-center gap-2">
                <span class="field__label mb-0" style="font-size: 14.5px">Tahun Ajaran Berjalan:</span>
                <x-chip tone="green" :label="$tahunAktif->kode . ' (Aktif)'" />
            </div>
        @else
            <x-chip tone="red" label="Belum Ada Tahun Ajaran Aktif" />
        @endif
    </x-page-head>

    {{-- Banner Panduan Awal Jika Belum Ada Tahun Ajaran Tujuan --}}
    @if (! $adaTahunTujuan)
        <div class="transisi-callout transisi-callout--warning mb-4" role="alert">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                <div>
                    <h5 class="fw-bold mb-1 text-dark" style="font-size: 16.5px">
                        Langkah Awal: Daftarkan Tahun Ajaran Baru Terlebih Dahulu
                    </h5>
                    <p class="mb-0 text-secondary" style="font-size: 14.5px; line-height: 1.5">
                        Sistem mendeteksi belum ada tahun ajaran tujuan mendatang yang berstatus persiapan/nonaktif (contoh: <strong>{{ \Carbon\Carbon::now()->year + 1 }}/{{ \Carbon\Carbon::now()->year + 2 }}</strong>).
                        Sebelum menyiapkan rombel kelas atau membuka simulasi transisi, daftarkan tahun ajaran mendatang terlebih dahulu pada menu Tahun Ajaran.
                    </p>
                </div>
                <div class="flex-shrink-0">
                    <a href="{{ route('admin.tahun-ajaran.index') }}" class="btn-hifi transisi-btn" style="background: #ca8a04; color: #fff; border: 0">
                        <x-ikon nama="calendar-plus" /> + Daftarkan Tahun Ajaran Baru
                    </a>
                </div>
            </div>
        </div>
    @endif

    {{-- Ringkasan Statistik Siswa Tahun Berjalan --}}
    <div class="mb-2">
        <span class="field__hint fw-semibold text-dark" style="font-size: 15px">
            Ringkasan Jumlah Siswa Aktif Tahun Berjalan ({{ $statistik['tahun_kode'] }}):
        </span>
    </div>
    <div class="grid-row grid-row--4 mb-4">
        <x-stat label="Siswa Aktif" :value="number_format($statistik['total_siswa'], 0, ',', '.')" caption="seluruh kelas asal" />
        <x-stat label="Tingkat X" :value="number_format($statistik['tingkat_x'], 0, ',', '.')" caption="calon naik ke XI" />
        <x-stat label="Tingkat XI" :value="number_format($statistik['tingkat_xi'], 0, ',', '.')" caption="calon naik ke XII" />
        <x-stat label="Tingkat XII" :value="number_format($statistik['tingkat_xii'], 0, ',', '.')" caption="calon lulusan" />
    </div>

    {{-- 2. Checklist Kesiapan Transisi Akademik --}}
    <x-card title="Pemeriksaan Kesiapan Transisi Akademik" class="mb-4">
        <x-slot:actions>
            <div class="d-flex align-items-center gap-2">
                <span class="card-hifi__meta" style="font-size: 14px">
                    Periode: <strong>{{ $checklist['tahun_asal'] ?? '—' }}</strong> &rarr; <strong>{{ $checklist['tahun_tujuan'] ?? 'Belum Ditentukan' }}</strong>
                </span>
                <x-chip :tone="$checklist['tone_keseluruhan']" :label="$checklist['label_keseluruhan']" />
            </div>
        </x-slot:actions>

        {{-- Ringkasan Operasi Ramah Senior --}}
        <div class="checklist-summary-box">
            <div class="d-flex flex-wrap align-items-center gap-4">
                <div class="checklist-op-item">
                    <span class="checklist-op-title">Kenaikan Kelas X &rarr; XI</span>
                    <x-chip :tone="$checklist['promosi_x_ke_xi']['tone']" :label="$checklist['promosi_x_ke_xi']['label']" />
                </div>
                <div class="checklist-op-item border-start ps-3">
                    <span class="checklist-op-title">Kenaikan Kelas XI &rarr; XII</span>
                    <x-chip :tone="$checklist['promosi_xi_ke_xii']['tone']" :label="$checklist['promosi_xi_ke_xii']['label']" />
                </div>
                <div class="checklist-op-item border-start ps-3">
                    <span class="checklist-op-title">Kelulusan Kelas XII</span>
                    <x-chip :tone="$checklist['kelulusan_xii']['tone']" :label="$checklist['kelulusan_xii']['label']" />
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                @if ($checklist['jumlah_terhambat'] > 0)
                    <span class="badge bg-danger p-2" style="font-size: 13.5px">
                        {{ $checklist['jumlah_terhambat'] }} Masalah Wajib Diperbaiki
                    </span>
                @endif
                @if ($checklist['jumlah_perlu_ditinjau'] > 0)
                    <span class="badge bg-warning text-dark p-2" style="font-size: 13.5px">
                        {{ $checklist['jumlah_perlu_ditinjau'] }} Perlu Pemeriksaan
                    </span>
                @endif
                <span class="badge bg-success p-2" style="font-size: 13.5px">
                    {{ $checklist['jumlah_siap'] }} Prasyarat Siap
                </span>
            </div>
        </div>

        {{-- Informasi Masalah Pemblokir (Jika Ada, Tampil Terbuka Tanpa Sembunyi) --}}
        @if ($checklist['jumlah_terhambat'] > 0)
            <div class="alert alert-danger mb-3 p-3" role="alert" style="font-size: 14.5px">
                <strong class="d-block mb-1">Perhatian Administrasi:</strong>
                Transisi akademik belum dapat diproses karena ada persiapan yang belum tuntas. Periksa rincian tabel di bawah untuk melihat langkah perbaikan yang diperlukan.
            </div>
        @endif

        {{-- Rincian Kriteria Penilaian (Progressive Disclosure) --}}
        <details class="mt-2" @open($checklist['jumlah_terhambat'] > 0 || request()->has('detail_kesiapan'))>
            <summary class="fw-semibold text-primary cursor-pointer mb-3" style="font-size: 14.5px">
                Klik di sini untuk melihat rincian pemeriksaan data ({{ count($checklist['item']) }} kriteria penilaian)
            </summary>

            <div class="tbl-wrap mt-2">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th style="font-size: 13.5px">Kategori Pemeriksaan</th>
                            <th style="font-size: 13.5px">Kriteria Penilaian</th>
                            <th class="is-num" style="font-size: 13.5px">Status</th>
                            <th style="font-size: 13.5px">Penjelasan / Diagnostik</th>
                            <th class="is-num" style="font-size: 13.5px">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($checklist['item'] as $item)
                            <tr>
                                <td class="is-muted" style="font-size: 13.5px">{{ $item['kategori'] }}</td>
                                <td class="is-strong" style="font-size: 14px">{{ $item['kriteria'] }}</td>
                                <td class="is-num">
                                    <x-chip :tone="$item['tone']" :label="$item['label']" />
                                </td>
                                <td style="font-size: 13.5px; max-width: 340px; word-break: break-word">
                                    {{ $item['pesan'] }}
                                    @if ($item['jumlah_terdampak'] !== null && $item['jumlah_terdampak'] > 0)
                                        <span class="badge bg-secondary ms-1">{{ $item['jumlah_terdampak'] }} data</span>
                                    @endif
                                </td>
                                <td class="is-num">
                                    @if ($item['tautan'])
                                        <a href="{{ $item['tautan'] }}" class="btn-hifi btn-hifi--ghost btn-hifi--sm" style="font-size: 13px">
                                            {{ $item['label_tautan'] ?? 'Buka Menu' }} &rarr;
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    </x-card>

    {{-- Alur Tindakan Bertahap: Langkah 1 & Langkah 2 --}}
    <div class="grid-row grid-row--2 mb-4 align-items-stretch">
        {{-- KARTU LANGKAH 1: PENYIAPAN ROMBEL --}}
        <x-card title="1. Siapkan Rombel Kelas Tujuan">
            <span class="transisi-badge-aksi transisi-badge-aksi--ubah">
                Langkah 1 &middot; Mengubah Data Master Kelas
            </span>

            <p class="transisi-desc">
                Sistem akan membuat rombel kelas di tahun ajaran tujuan secara otomatis dengan mempertahankan <strong>jurusan dan nomor paralel</strong> yang sama (contoh: <code>X TKJ 1</code> &rarr; <code>XI TKJ 1</code>, dan <code>XI TKJ 1</code> &rarr; <code>XII TKJ 1</code>).
            </p>

            <form method="POST" action="{{ route('admin.transisi.siapkan-kelas') }}" class="form-grid">
                @csrf
                <div class="form-grid form-grid--2">
                    <div>
                        <label for="tahun_asal_siap" class="transisi-label">Tahun Ajaran Asal</label>
                        <select class="select-hifi transisi-select" name="tahun_asal" id="tahun_asal_siap" required>
                            @foreach ($tahunList as $t)
                                <option value="{{ $t->kode }}" @selected($t->aktif)>
                                    {{ $t->kode }} {{ $t->aktif ? '(Aktif Saat Ini)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="tahun_tujuan_siap" class="transisi-label">Ke Tahun Ajaran Tujuan</label>
                        <select class="select-hifi transisi-select" name="tahun_tujuan" id="tahun_tujuan_siap" required @disabled(! $adaTahunTujuan)>
                            @if (! $adaTahunTujuan)
                                <option value="" disabled selected>Belum ada tahun ajaran tujuan (tambahkan di menu Tahun Ajaran)</option>
                            @else
                                <option value="">Pilih Tahun Ajaran Tujuan...</option>
                                @foreach ($tahunList as $t)
                                    @if (! $t->aktif)
                                        <option value="{{ $t->kode }}" @selected(($checklist['tahun_tujuan'] ?? '') === $t->kode)>
                                            {{ $t->kode }} (Persiapan)
                                        </option>
                                    @endif
                                @endforeach
                            @endif
                        </select>
                    </div>
                </div>

                <div class="d-flex flex-column align-items-end mt-3">
                    <button class="btn-hifi transisi-btn" type="submit" @disabled(! $adaTahunTujuan)>
                        <x-ikon nama="building" /> Siapkan Rombel Kelas Baru
                    </button>
                    @if (! $adaTahunTujuan)
                        <span class="text-danger small mt-1 fw-semibold">
                            Tambahkan tahun ajaran baru terlebih dahulu untuk mengaktifkan tombol ini.
                        </span>
                    @endif
                </div>
            </form>
        </x-card>

        {{-- KARTU LANGKAH 2: SIMULASI & EKSEKUSI --}}
        <x-card title="Langkah 2: Simulasi Kenaikan Kelas & Kelulusan">
            <span class="transisi-badge-aksi transisi-badge-aksi--aman">
                Aman &middot; Simulasi Tidak Mengubah Data Siswa
            </span>

            <p class="transisi-desc">
                Buka simulasi pratinjau untuk meninjau daftar siswa yang akan naik kelas, siswa yang tinggal kelas (pengecualian), dan siswa kelas XII yang akan lulus sebelum diproses secara permanen.
            </p>

            <form method="GET" action="{{ route('admin.transisi.pratinjau') }}" class="form-grid">
                <div class="form-grid form-grid--2">
                    <div>
                        <label for="tahun_asal_sim" class="transisi-label">Tahun Ajaran Asal</label>
                        <select class="select-hifi transisi-select" name="tahun_asal" id="tahun_asal_sim" required>
                            @foreach ($tahunList as $t)
                                <option value="{{ $t->kode }}" @selected($t->aktif)>
                                    {{ $t->kode }} {{ $t->aktif ? '(Aktif Saat Ini)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="tahun_tujuan_sim" class="transisi-label">Ke Tahun Ajaran Tujuan</label>
                        <select class="select-hifi transisi-select" name="tahun_tujuan" id="tahun_tujuan_sim" required @disabled(! $adaTahunTujuan)>
                            @if (! $adaTahunTujuan)
                                <option value="" disabled selected>Belum ada tahun ajaran tujuan (tambahkan di menu Tahun Ajaran)</option>
                            @else
                                <option value="">Pilih Tahun Ajaran Tujuan...</option>
                                @foreach ($tahunList as $t)
                                    @if (! $t->aktif)
                                        <option value="{{ $t->kode }}" @selected(($checklist['tahun_tujuan'] ?? '') === $t->kode)>
                                            {{ $t->kode }} (Persiapan)
                                        </option>
                                    @endif
                                @endforeach
                            @endif
                        </select>
                    </div>
                </div>

                <div class="d-flex flex-column align-items-end mt-3">
                    <button class="btn-hifi transisi-btn" type="submit" @disabled(! $adaTahunTujuan)>
                        <x-ikon nama="eye" /> Lihat Simulasi Transisi &rarr;
                    </button>
                    @if (! $adaTahunTujuan)
                        <span class="text-danger small mt-1 fw-semibold">
                            Pilih tahun ajaran tujuan terlebih dahulu untuk membuka simulasi.
                        </span>
                    @endif
                </div>
            </form>
        </x-card>
    </div>

    {{-- 3. Riwayat Transisi Tahunan --}}
    <x-card title="Riwayat Kenaikan & Kelulusan yang Pernah Diproses" flush>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th style="font-size: 13.5px">Waktu Eksekusi</th>
                        <th style="font-size: 13.5px">Tahun Asal &rarr; Tujuan</th>
                        <th class="is-num" style="font-size: 13.5px">Siswa Naik Kelas</th>
                        <th class="is-num" style="font-size: 13.5px">Siswa Lulus</th>
                        <th class="is-num" style="font-size: 13.5px">Siswa Tinggal</th>
                        <th class="is-num" style="font-size: 13.5px">Status</th>
                        <th style="font-size: 13.5px">Keterangan</th>
                        <th style="font-size: 13.5px">Diproses Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transisiList as $tr)
                        @php
                            $tone = match ($tr->status) {
                                'selesai' => 'green',
                                'diproses' => 'yellow',
                                'gagal' => 'red',
                                default => 'neutral',
                            };
                        @endphp
                        <tr>
                            <td class="is-muted is-nowrap" style="font-size: 13.5px">
                                {{ $tr->diproses_pada ? $tr->diproses_pada->format('d/m/Y H:i') : $tr->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="is-strong" style="font-size: 14px">{{ $tr->tahun_asal }} &rarr; {{ $tr->tahun_tujuan }}</td>
                            <td class="is-num" style="font-size: 14px">{{ number_format($tr->jumlah_siswa_dipromosikan, 0, ',', '.') }}</td>
                            <td class="is-num" style="font-size: 14px">{{ number_format($tr->jumlah_siswa_lulus, 0, ',', '.') }}</td>
                            <td class="is-num" style="font-size: 14px">{{ number_format($tr->jumlah_siswa_tinggal, 0, ',', '.') }}</td>
                            <td class="is-num"><x-chip :tone="$tone" :label="ucfirst($tr->status)" /></td>
                            <td class="is-muted small" style="max-width: 250px; word-break: break-word">{{ Str::limit($tr->catatan, 60) }}</td>
                            <td class="is-muted" style="font-size: 13.5px">{{ $tr->diprosesOleh?->nama ?? $tr->diprosesOleh?->username ?? 'Sistem' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-state" style="font-size: 14.5px; padding: 32px 16px">
                                Belum ada riwayat proses kenaikan kelas atau kelulusan antar tahun ajaran.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
