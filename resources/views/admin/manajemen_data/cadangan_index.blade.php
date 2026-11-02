@extends('layouts.app')

@section('title', 'Ekspor & Cadangkan Data')

@section('content')
<div class="cadangan-index-view space-y-5">
    <style>
        .cadangan-index-view .page-head {
            margin-top: 8px !important;
            margin-bottom: 24px !important;
        }
        .cadangan-index-view .page-head__title {
            font-size: 22px !important;
            font-weight: 700 !important;
            line-height: 1.25 !important;
            color: #111827 !important;
            margin-bottom: 6px !important;
        }
        .cadangan-index-view .page-head__sub {
            font-size: 14px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
            color: #4b5563 !important;
            margin: 0 !important;
        }
        .cadangan-index-view .breadcrumb-nav {
            font-size: 13px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
        }
        .cadangan-index-view .breadcrumb-nav a {
            color: #4b5563 !important;
        }
        .cadangan-index-view .breadcrumb-nav a:hover {
            color: var(--primary, #3b5d32) !important;
        }
        .cadangan-index-view .btn-hifi {
            font-size: 14px !important;
            font-weight: 600 !important;
            line-height: 1.4 !important;
        }
    </style>

    {{-- Breadcrumb Navigasi --}}
    <nav class="breadcrumb-nav mb-3" aria-label="Breadcrumb">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Dashboard</a>
            <span class="text-muted">&rsaquo;</span>
            <a href="{{ route('admin.manajemen-data.index') }}" class="text-decoration-none">Manajemen Data</a>
            <span class="text-muted">&rsaquo;</span>
            <span class="text-dark fw-semibold" aria-current="page">Ekspor &amp; Cadangkan Data</span>
        </div>
    </nav>

    {{-- Bento Hero Card Header (Fase 1) --}}
    <div class="card-hifi p-4 mb-4 border-l-4" style="border-left-color: var(--primary, #3b5d32);">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <h2 class="page-head__title mb-0" style="font-size: 21px !important;">Ekspor &amp; Cadangkan Data</h2>
                    <span class="chip chip--green">Database Aktif &amp; Terlindungi</span>
                </div>
                <p class="page-head__sub">
                    Pencadangan snapshot database MySQL otomatis (.sql.gz) dan ekspor administratif tabel (CSV) terproteksi.
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.manajemen-data.index') }}" class="btn-hifi btn-hifi--ghost">
                    <x-ikon nama="arrow-left" /> Menu Manajemen Data
                </a>
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="card-hifi p-3 mb-4 border-l-4 border-primary bg-primary-soft d-flex align-items-center gap-2">
            <x-ikon nama="check-circle" class="text-primary" />
            <div class="text-dark fw-medium small">{{ session('status') }}</div>
        </div>
    @endif

    @if ($errors->any())
        <div class="card-hifi p-4 mb-4 border-l-4" style="border-left-color: #b91c1c; background: #fff5f5;">
            <div class="fw-bold text-danger mb-1">Perhatian Terdeteksi:</div>
            <ul class="mb-0 ps-3 text-danger small">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Fase 2: Grid Bento 2 Kolom untuk Operasi Cadangan & Ekspor --}}
    <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px;" class="mb-4">
        {{-- Kartu Kiri: Buat Cadangan Database Lengkap --}}
        <div>
            <x-card title="Cadangan Basis Data Lengkap" class="h-100 border-l-4" style="border-left-color: var(--primary, #3b5d32);">
                <div class="p-1">
                    <p class="text-muted small mb-3" style="line-height: 1.5;">
                        Snapshot konsisten InnoDB mencakup 31 tabel relasional inti aplikasi. Disimpan secara privat dan diverifikasi integritas SHA-256 otomatis.
                    </p>
                    <form method="POST" action="{{ route('admin.manajemen-data.cadangan.store') }}">
                        @csrf
                        <div class="field mb-3">
                            <label class="field__label" for="formatSelect">Format Berkas Cadangan<span class="field__req"> *</span></label>
                            <select name="format" id="formatSelect" class="select-hifi w-100" required>
                                <option value="sql_gz" selected>MySQL Dump Terkompresi (.sql.gz) - Disarankan</option>
                                <option value="sql">MySQL Dump Teks Polos (.sql) - Alternatif</option>
                                <option value="zip_berkas">Arsip Berkas Penting (.zip) - Berkas Publik</option>
                            </select>
                            <span class="field__hint">Format terkompresi .sql.gz menghemat ruang disk hingga 80%.</span>
                        </div>
                        <div class="field mb-3">
                            <label class="field__label" for="catatanInput">Catatan Pembuatan (Opsional)</label>
                            <input type="text" id="catatanInput" name="catatan" class="select-hifi w-100" placeholder="Contoh: Sebelum migrasi kurikulum baru">
                        </div>
                        <div class="pt-2">
                            <button type="submit" class="btn-hifi">
                                <x-ikon nama="play-fill" /> Buat &amp; Verifikasi Cadangan
                            </button>
                        </div>
                    </form>
                </div>
            </x-card>
        </div>

        {{-- Kartu Kanan: Ekspor Administratif CSV --}}
        <div>
            <x-card title="Ekspor Tabel CSV Administratif" class="h-100 border-l-4" style="border-left-color: #166534;">
                <div class="p-1">
                    <p class="text-muted small mb-3" style="line-height: 1.5;">
                        Unduh data tabel dalam format spreadsheet CSV untuk keperluan pelaporan. Kunci rahasia, hash password, dan token sesi otomatis dihilangkan.
                    </p>
                    <form method="POST" action="{{ route('admin.manajemen-data.ekspor.csv') }}">
                        @csrf
                        <div class="field mb-3">
                            <label class="field__label" for="tabelSelect">Pilih Tabel Database<span class="field__req"> *</span></label>
                            <select name="tabel" id="tabelSelect" class="select-hifi w-100" required>
                                @foreach ($tabelList as $tbl)
                                    <option value="{{ $tbl }}">Tabel {{ $tbl }}</option>
                                @endforeach
                            </select>
                            <span class="field__hint">Pilih entitas data yang ingin dianalisis secara terpisah.</span>
                        </div>
                        <div class="p-2 mb-3 rounded" style="background: rgba(0,0,0,0.03); font-size: 13px; color: #4b5563;">
                            <span class="text-dark fw-semibold">Pemberitahuan:</span> Berkas CSV ditujukan khusus pelaporan staf dan <strong>bukan</strong> untuk pemulihan bencana sistem.
                        </div>
                        <div class="pt-2">
                            <button type="submit" class="btn-hifi btn-hifi--ghost">
                                <x-ikon nama="download" /> Unduh Berkas CSV
                            </button>
                        </div>
                    </form>
                </div>
            </x-card>
        </div>
    </div>

    {{-- Fase 3: Modernisasi Tabel Riwayat Arsip Cadangan --}}
    <x-card title="Riwayat Arsip Cadangan Tersimpan" flush class="border-l-4 border-primary">
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;">Pin</th>
                        <th style="width: 160px;">Waktu Pembuatan</th>
                        <th>Nama Berkas &amp; Catatan</th>
                        <th style="width: 100px;">Format</th>
                        <th style="width: 120px;">Ukuran</th>
                        <th style="width: 120px;">Tipe</th>
                        <th style="width: 150px;">Status Validasi</th>
                        <th class="is-num" style="width: 140px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($arsipList as $a)
                        <tr>
                            <td style="text-align: center;">
                                <form method="POST" action="{{ route('admin.manajemen-data.cadangan.pin', $a->id) }}">
                                    @csrf
                                    <button type="submit" class="btn-hifi btn-hifi--ghost btn-hifi--sm p-1" style="border: none; background: transparent;" title="{{ $a->penting ? 'Lepas tanda penting' : 'Tandai penting (Lindungi dari rotasi retensi)' }}">
                                        <x-ikon nama="{{ $a->penting ? 'pin-angle-fill' : 'pin-angle' }}" class="{{ $a->penting ? 'text-warning fs-5' : 'text-muted' }}" />
                                    </button>
                                </form>
                            </td>
                            <td class="text-muted small">{{ \Illuminate\Support\Carbon::parse($a->created_at)->format('d/m/Y H:i') }}</td>
                            <td>
                                <div class="font-monospace fw-bold text-dark">{{ $a->nama_berkas }}</div>
                                @if ($a->catatan)
                                    <div class="text-muted small mt-1">{{ $a->catatan }}</div>
                                @endif
                                <div class="text-secondary small font-monospace mt-1" style="font-size: 11px;">SHA256: {{ substr($a->checksum_sha256, 0, 16) }}...</div>
                            </td>
                            <td><span class="chip chip--neutral">{{ strtoupper($a->format) }}</span></td>
                            <td><span class="chip chip--neutral">{{ round($a->ukuran_bytes / 1024, 1) }} KB</span></td>
                            <td>
                                <span class="chip {{ $a->tipe === 'otomatis_bulanan' ? 'chip--solid-lembut' : 'chip--neutral' }}">
                                    {{ $a->tipe === 'otomatis_bulanan' ? 'Otomatis' : 'Manual' }}
                                </span>
                            </td>
                            <td>
                                @if ($a->status_validasi === 'terverifikasi')
                                    <span class="chip chip--green">
                                        <x-ikon nama="check-circle" /> Terverifikasi
                                    </span>
                                @else
                                    <span class="chip chip--neutral" style="color: #b91c1c; background: #fee2e2;">
                                        <x-ikon nama="exclamation-circle" /> Belum Valid
                                    </span>
                                @endif
                            </td>
                            <td class="is-num tbl__aksi">
                                <div class="d-inline-flex gap-1">
                                    <a href="{{ route('admin.manajemen-data.cadangan.download', $a->id) }}" class="btn-hifi btn-hifi--ghost btn-hifi--sm" title="Unduh Berkas Cadangan">
                                        <x-ikon nama="download" /> Unduh
                                    </a>
                                    <form method="POST" action="{{ route('admin.manajemen-data.cadangan.destroy', $a->id) }}" onsubmit="return confirm('Apakah Anda yakin ingin menghapus arsip cadangan ini?');" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-hifi btn-hifi--ghost btn-hifi--sm text-danger" title="Hapus Cadangan" {{ $a->penting ? 'disabled' : '' }}>
                                            <x-ikon nama="trash" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-state text-center py-4 text-muted">
                                Belum ada arsip cadangan tersimpan. Buat cadangan baru melalui formulir di atas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($arsipList->hasPages())
            <x-slot:foot>
                <span>
                    Menampilkan {{ $arsipList->firstItem() ?? 0 }}–{{ $arsipList->lastItem() ?? 0 }}
                    dari {{ number_format($arsipList->total(), 0, ',', '.') }} cadangan
                </span>
                <x-pager :paginator="$arsipList" />
            </x-slot:foot>
        @endif
    </x-card>
</div>
@endsection
