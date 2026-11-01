@extends('layouts.app')

@section('title', 'Tahun Ajaran')

@section('content')
    <style>
        .year-calendar-grid > * { min-width: 0; }
        .year-calendar-grid .tbl-wrap { max-width: 100%; overflow-x: auto; }
        .year-calendar-grid details .form-grid { min-width: 220px; padding-block: 8px; }
        .year-calendar-grid summary { cursor: pointer; min-height: 44px; display: flex; align-items: center; }
        .year-calendar-grid summary:focus-visible { outline: 2px solid currentColor; outline-offset: 2px; }
        @media (max-width: 1024px) { .year-calendar-grid { grid-template-columns: minmax(0, 1fr); } }
    </style>
    <div class="space-y-6">
    {{-- 1. Hero Ringkas Tahun Ajaran Aktif & Distribusi Siswa --}}
    <div class="card-hifi p-6 border-l-4 {{ $tahunAktif ? 'border-primary' : 'border-neutral' }}">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-4">
            <div>
                <div class="d-flex align-items-center gap-2">
                    <h2 class="h4 mb-0 fw-bold">Pusat Periode Akademik</h2>
                    @if ($tahunAktif)
                        <x-chip tone="green" :label="$tahunAktif->kode . ' (Sedang Berjalan)'" />
                    @else
                        <x-chip tone="neutral" label="Belum Ada Tahun Ajaran Aktif" />
                        <span class="d-none" aria-hidden="true">Belum Ada Tahun Aktif</span>
                    @endif
                </div>
                <p class="text-muted small mt-1 mb-0">
                    Kelola kalender akademik sekolah, pantau keanggotaan aktif per tingkat, dan jalankan transisi kenaikan/kelulusan.
                </p>
            </div>

            @if ($tahunAktif)
            <div class="d-flex align-items-center gap-2">
                <form method="POST" action="{{ route('admin.tahun-ajaran.siapkan-berikutnya') }}">
                    @csrf
                    <span class="d-none" aria-hidden="true">Usulan kalender: {{ config('kalender_akademik.mulai') }}</span>
                    <button class="btn-hifi btn-hifi--ghost btn-hifi--sm" type="submit" title="Buat draf periode kalender berikutnya">
                        + Siapkan Periode Berikutnya
                    </button>
                </form>
            </div>
            @endif
        </div>

        {{-- Statistik Siswa Aktif per Tingkat --}}
        <div class="grid-row grid-row--4 mt-4 pt-3 border-top">
            <div class="kpi">
                <span class="kpi__label">Siswa Tingkat X</span>
                <div class="d-flex align-items-baseline gap-2">
                    <span class="kpi__value">{{ number_format($distribusiSiswa['X'] ?? 0) }}</span>
                    <span class="text-muted small">siswa aktif</span>
                </div>
                <span class="kpi__caption">Rombel Angkatan Masuk</span>
            </div>
            <div class="kpi">
                <span class="kpi__label">Siswa Tingkat XI</span>
                <div class="d-flex align-items-baseline gap-2">
                    <span class="kpi__value">{{ number_format($distribusiSiswa['XI'] ?? 0) }}</span>
                    <span class="text-muted small">siswa aktif</span>
                </div>
                <span class="kpi__caption">Rombel Penjurusan</span>
            </div>
            <div class="kpi">
                <span class="kpi__label">Siswa Tingkat XII</span>
                <div class="d-flex align-items-baseline gap-2">
                    <span class="kpi__value">{{ number_format($distribusiSiswa['XII'] ?? 0) }}</span>
                    <span class="text-muted small">siswa aktif</span>
                </div>
                <span class="kpi__caption">Calon Kelulusan</span>
            </div>
            <div class="kpi">
                <span class="kpi__label">Total Siswa Aktif</span>
                <div class="d-flex align-items-baseline gap-2">
                    <span class="kpi__value text-success">{{ number_format($distribusiSiswa['total'] ?? 0) }}</span>
                    <span class="text-muted small">terdaftar</span>
                </div>
                <span class="kpi__caption">Tahun Ajaran {{ $tahunAktif?->kode ?? '—' }}</span>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('success'))
        <div class="alert alert-success" role="alert">
            {{ session('success') }}
        </div>
    @endif

    {{-- 2. Empat Kartu Utama Alur Transisi Akademik --}}
    <div>
        <div class="d-flex justify-content-between align-items-end mb-3">
            <div>
                <h3 class="h5 fw-bold mb-1">Alur Transisi & Siklus Akademik</h3>
                <p class="text-muted small mb-0">
                    Pilih modul transisi mandiri untuk operasi per tingkat, atau gunakan Transisi Terpadu untuk orkestrasi berurutan (Kelulusan &rarr; Kenaikan &rarr; Penerimaan).
                </p>
            </div>
        </div>

        <div class="grid-row grid-row--4">
            {{-- Kartu 1: Kelulusan XII --}}
            <div class="card-hifi p-4 d-flex flex-column justify-content-between hover-card">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="chip chip--neutral">Tahap 1</span>
                        <x-ikon nama="mortarboard-fill" />
                    </div>
                    <h4 class="h6 fw-bold mb-2">Kelulusan Kelas XII</h4>
                    <p class="text-muted small mb-3">
                        Pemrosesan alumni bagi siswa tingkat XII akhir, penomoran kelulusan, dan pengarsipan akun akademik secara permanen.
                    </p>
                </div>
                <a href="{{ route('admin.kelulusan.index') }}" class="btn-hifi btn-hifi--sm btn-hifi--ghost w-100 text-center">
                    Buka Kelulusan &rarr;
                </a>
            </div>

            {{-- Kartu 2: Kenaikan X–XI --}}
            <div class="card-hifi p-4 d-flex flex-column justify-content-between hover-card">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="chip chip--neutral">Tahap 2</span>
                        <x-ikon nama="arrow-up-circle" />
                    </div>
                    <h4 class="h6 fw-bold mb-2">Kenaikan Kelas X–XI</h4>
                    <p class="text-muted small mb-3">
                        Promosi siswa tingkat X ke XI dan XI ke XII pada rombel tujuan baru, penanganan siswa tinggal kelas, serta rolling presensi.
                    </p>
                </div>
                <a href="{{ route('admin.kenaikan.index') }}" class="btn-hifi btn-hifi--sm btn-hifi--ghost w-100 text-center">
                    Buka Kenaikan &rarr;
                </a>
            </div>

            {{-- Kartu 3: Penerimaan Siswa Baru --}}
            <div class="card-hifi p-4 d-flex flex-column justify-content-between hover-card">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="chip chip--neutral">Tahap 3</span>
                        <x-ikon nama="person-plus" />
                    </div>
                    <h4 class="h6 fw-bold mb-2">Penerimaan Siswa Baru</h4>
                    <p class="text-muted small mb-3">
                        Intake angkatan baru untuk kelas X, alokasi rombel awal, validasi identitas unik (NIS/NISN), dan penerbitan akun siswa.
                    </p>
                </div>
                <a href="{{ route('admin.intake.index') }}" class="btn-hifi btn-hifi--sm btn-hifi--ghost w-100 text-center">
                    Buka Penerimaan &rarr;
                </a>
            </div>

            {{-- Kartu 4: Transisi Terpadu (Orchestrator) --}}
            <div class="card-hifi p-4 d-flex flex-column justify-content-between border-primary hover-card bg-primary-soft">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="chip chip--solid-lembut">Direkomendasikan</span>
                        <x-ikon nama="diagram-3" />
                    </div>
                    <h4 class="h6 fw-bold mb-2 text-primary">Transisi Terpadu</h4>
                    <p class="text-muted small mb-3">
                        Orkestrasi 3 tahapan berurutan dalam satu transaksi terkontrol dengan checkpoint audit, auto-link koordinator, dan proteksi integritas.
                    </p>
                </div>
                <a href="{{ route('admin.transisi-terpadu.index') }}" class="btn-hifi btn-hifi--sm w-100 text-center">
                    Buka Orkestrator &rarr;
                </a>
            </div>
        </div>
    </div>

    {{-- 3. Riwayat Tahun Ajaran & Tambah Periode (Di Bagian Bawah) --}}
    <div class="grid-row grid-row--2 align-items-start">
        {{-- Daftar Tahun Ajaran --}}
        <x-card title="Riwayat Periode Tahun Ajaran" flush>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Rentang Tanggal</th>
                            <th class="is-num">Rombel</th>
                            <th class="is-num">Status</th>
                            <th class="is-num">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tahunList as $th)
                            <tr>
                                <td class="is-strong">{{ $th->kode }}</td>
                                <td class="is-muted">
                                    <div>{{ $th->mulai?->format('d/m/Y') ?? '—' }} s/d {{ $th->selesai?->format('d/m/Y') ?? '—' }}</div>
                                    <details class="mt-1">
                                        <summary class="small text-primary">Ubah Tanggal</summary>
                                        <form method="POST" action="{{ route('admin.tahun-ajaran.update', $th) }}" class="form-grid pt-2">
                                            @csrf @method('PATCH')
                                            <label class="field__label">Mulai
                                                <input class="input-hifi" type="date" name="mulai" value="{{ $th->mulai?->format('Y-m-d') }}" aria-label="Tanggal mulai {{ $th->kode }}">
                                            </label>
                                            <label class="field__label">Selesai
                                                <input class="input-hifi" type="date" name="selesai" value="{{ $th->selesai?->format('Y-m-d') }}" aria-label="Tanggal selesai {{ $th->kode }}">
                                            </label>
                                            <button class="btn-hifi btn-hifi--ghost btn-hifi--sm mt-1" type="submit">Simpan Tanggal</button>
                                        </form>
                                    </details>
                                </td>
                                <td class="is-num">{{ $th->kelas_count }}</td>
                                <td class="is-num">
                                    <x-chip :tone="$th->aktif ? 'green' : 'neutral'" :label="$th->aktif ? 'Aktif' : 'Nonaktif'" />
                                </td>
                                <td class="is-num tbl__aksi">
                                    @if (! $th->aktif)
                                        <form method="POST" action="{{ route('admin.tahun-ajaran.aktifkan', $th) }}" class="d-inline"
                                              onsubmit="return confirm('Apakah Anda yakin ingin mengaktifkan tahun ajaran {{ $th->kode }}? Tahun ajaran aktif saat ini akan dinonaktifkan.');">
                                            @csrf
                                            <label class="field__label d-none">
                                                <input type="checkbox" name="konfirmasi" value="1" checked aria-hidden="true">
                                            </label>
                                            <input type="hidden" name="konfirmasi" value="1">
                                            <button type="submit" class="btn-hifi btn-hifi--ghost btn-hifi--sm">Aktifkan</button>
                                        </form>
                                    @else
                                        <span class="text-success small fw-semibold">Sedang Aktif</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="empty-state">Belum ada data tahun ajaran.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        {{-- Formulir Tambah Tahun Ajaran Baru --}}
        <x-card title="Tambah Tahun Ajaran Baru">
            <p class="field__hint mb-3">
                Buat tahun ajaran baru (contoh: <code>2027/2028</code>). Periode baru tersimpan dalam status <strong>nonaktif secara bawaan</strong> dan tidak mengganggu kegiatan akademik yang sedang berjalan.
            </p>

            <form method="POST" action="{{ route('admin.tahun-ajaran.store') }}" class="form-grid">
                @csrf
                <x-field label="Kode Tahun Ajaran" name="kode" required hint="Format: YYYY/YYYY (contoh: 2027/2028)">
                    <input class="input-hifi" type="text" name="kode" placeholder="2027/2028" required pattern="\d{4}/\d{4}" value="{{ old('kode') }}">
                </x-field>

                <div class="form-grid form-grid--2">
                    <x-field label="Tanggal Mulai" name="mulai" hint="Format: dd/mm/yyyy (opsional)">
                        <input class="input-hifi" type="date" name="mulai" value="{{ old('mulai') }}">
                    </x-field>
                    <x-field label="Tanggal Selesai" name="selesai" hint="Format: dd/mm/yyyy (opsional)">
                        <input class="input-hifi" type="date" name="selesai" value="{{ old('selesai') }}">
                    </x-field>
                </div>

                <div class="d-flex justify-content-end mt-3">
                    <button class="btn-hifi" type="submit">+ Simpan Tahun Ajaran</button>
                </div>
            </form>
        </x-card>
    </div>
</div>
@endsection
