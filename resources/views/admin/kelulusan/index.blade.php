@extends('layouts.app')

@section('title', 'Kelulusan Kelas XII')

@section('content')
<div class="kelulusan-view space-y-5">
    <style>
        .kelulusan-view .page-head {
            margin-top: 8px !important;
            margin-bottom: 24px !important;
        }
        .kelulusan-view .page-head__title {
            font-size: 22px !important;
            font-weight: 700 !important;
            line-height: 1.25 !important;
            color: #111827 !important;
            margin-bottom: 6px !important;
        }
        .kelulusan-view .page-head__sub {
            font-size: 14px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
            color: #4b5563 !important;
            margin: 0 !important;
        }
        .kelulusan-view .breadcrumb-nav {
            font-size: 13px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
        }
        .kelulusan-view .breadcrumb-nav a {
            color: #4b5563 !important;
        }
        .kelulusan-view .breadcrumb-nav a:hover {
            color: var(--primary, #3b5d32) !important;
        }
        .kelulusan-view .card-hifi__title {
            font-size: 18.5px !important;
            font-weight: 700 !important;
            line-height: 1.3 !important;
            color: #111827 !important;
        }
        .kelulusan-view .field__label {
            font-size: 14px !important;
            font-weight: 600 !important;
            line-height: 1.4 !important;
            color: #1f2937 !important;
        }
        .kelulusan-view .field__hint {
            font-size: 13px !important;
            font-weight: 400 !important;
            line-height: 1.4 !important;
            color: #4b5563 !important;
        }
        .kelulusan-view .kpi-card__num {
            font-size: 26px !important;
            font-weight: 700 !important;
            line-height: 1.2 !important;
            color: #111827 !important;
        }
        .kelulusan-view .file-upload-box {
            border: 2px dashed #cbd5e1;
            border-radius: var(--radius-card, 12px);
            background: #fafbfa;
            padding: 22px 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }
        .kelulusan-view .file-upload-box:hover {
            border-color: var(--primary, #3b5d32);
            background: #ffffff;
            box-shadow: 0 4px 14px rgba(59, 93, 50, 0.06);
        }
        .kelulusan-view .file-upload-box.has-file {
            border-color: var(--primary, #3b5d32);
            border-style: solid;
            background: #fdfefe;
        }
        .kelulusan-view .file-upload-box input[type="file"] {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
            z-index: 2;
        }
        .kelulusan-view .file-upload-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: rgba(59, 93, 50, 0.1);
            color: var(--primary, #3b5d32);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-bottom: 8px;
        }
        .kelulusan-view .tbl th {
            font-size: 12.5px !important;
            font-weight: 600 !important;
            line-height: 1.4 !important;
            padding: 10px 14px !important;
        }
        .kelulusan-view .tbl td {
            font-size: 13.5px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
            padding: 10px 14px !important;
        }
    </style>

    {{-- Breadcrumb Navigasi --}}
    <nav class="breadcrumb-nav mb-3" aria-label="Breadcrumb">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Dashboard</a>
            <span class="text-muted">&rsaquo;</span>
            <a href="{{ route('admin.tahun-ajaran.index') }}" class="text-decoration-none">Tahun Ajaran</a>
            @if(!empty($coordinatorId))
                <span class="text-muted">&rsaquo;</span>
                <a href="{{ route('admin.transisi-terpadu.show', $coordinatorId) }}" class="text-decoration-none">Transisi Terpadu</a>
            @endif
            <span class="text-muted">&rsaquo;</span>
            <span class="text-dark fw-semibold" aria-current="page">Kelulusan Kelas XII</span>
        </div>
    </nav>

    {{-- Banner Penghubung Sesi Terpadu --}}
    @if(!empty($coordinatorId))
        <div class="card-hifi p-4 border-l-4 border-primary bg-primary-soft mb-4 d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div style="font-size: 22px; color: var(--primary, #3b5d32);">
                    <x-ikon nama="diagram-3" />
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="chip chip--solid-lembut">Sesi Terpadu</span>
                        <h4 class="h6 fw-bold mb-0 text-primary">Terhubung dengan Koordinator #{{ $coordinatorId }}</h4>
                    </div>
                    <p class="text-muted small mb-0">
                        Hasil eksekusi berkas kelulusan ini akan otomatis memperbarui checkpoint pada alur transisi terpadu Anda.
                    </p>
                </div>
            </div>
            <a href="{{ route('admin.transisi-terpadu.show', $coordinatorId) }}" class="btn-hifi btn-hifi--ghost btn-hifi--sm text-nowrap">
                &larr; Kembali ke Transisi Terpadu
            </a>
        </div>
    @endif

    {{-- Page Header --}}
    <div class="page-head d-flex justify-content-between align-items-start">
        <div>
            <h2 class="page-head__title">Kelulusan Kelas XII</h2>
            <p class="page-head__sub">
                Pengelolaan kelulusan siswa tingkat akhir berdasarkan berkas resmi sekolah. Proses ini berdiri mandiri dan mengarsipkan akun alumni secara aman.
            </p>
        </div>
    </div>

    {{-- Status Flash & Error Messages --}}
    @if (session('status'))
        <div class="card-hifi p-3 border-l-4 border-primary bg-primary-soft d-flex align-items-center gap-2 mb-4">
            <x-ikon nama="check-circle" />
            <span class="fw-semibold text-primary" style="font-size: 14px;">{{ session('status') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="card-hifi p-4 border-l-4 border-danger mb-4" role="alert">
            <div class="d-flex align-items-center gap-2 text-danger fw-bold mb-2" style="font-size: 14px;">
                <x-ikon nama="exclamation-triangle" />
                <span>Terjadi kendala pada pengajuan kelulusan:</span>
            </div>
            <ul class="mb-0 ps-3 text-danger" style="font-size: 13.5px; line-height: 1.5;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Filter Periode Tahun Ajaran --}}
    <div class="card-hifi p-4 mb-4">
        <form method="GET" action="{{ route('admin.kelulusan.index') }}" id="formFilterKelulusan">
            <div class="form-grid form-grid--2 mb-2">
                <div class="field">
                    <label class="field__label" for="tahun_asal">Tahun Ajaran Kelulusan (Sumber)</label>
                    <select name="tahun_asal" id="tahun_asal" class="select-hifi" onchange="document.getElementById('formFilterKelulusan').submit()" required>
                        @foreach ($tahunList as $t)
                            <option value="{{ $t->kode }}" @selected($t->kode === $tahunAsal)>
                                {{ $t->kode }} {{ $t->aktif ? '(Aktif Saat Ini)' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <span class="field__hint">Periode angkatan kelas XII yang diproses kelulusannya.</span>
                </div>
                <div class="field">
                    <label class="field__label" for="tahun_tujuan">Tahun Penempatan Ulang (Bagi Siswa Tidak Lulus)</label>
                    <select name="tahun_tujuan" id="tahun_tujuan" class="select-hifi" onchange="document.getElementById('formFilterKelulusan').submit()">
                        @if ($tahunTujuanList->isEmpty())
                            <option value="" disabled selected>Belum ada tahun tujuan nonaktif mendatang</option>
                        @else
                            @foreach ($tahunTujuanList as $t)
                                <option value="{{ $t->kode }}" @selected($t->kode === $tahunTujuan)>
                                    {{ $t->kode }} (Mendatang)
                                </option>
                            @endforeach
                        @endif
                    </select>
                    <span class="field__hint">Rombel tujuan jika ada siswa yang harus mengulang kelas XII.</span>
                </div>
            </div>
        </form>
    </div>

    {{-- Bento Baris 2: Info Roster (Kiri) + Form Upload Verifikasi (Kanan) --}}
    <div style="display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 2fr); gap: 16px; align-items: stretch;" class="mb-4">
        {{-- Kartu Info Roster XII --}}
        <div class="card-hifi p-4 d-flex flex-column justify-content-between">
            <div>
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="chip chip--neutral fw-bold">Roster Calon Lulusan</span>
                    <div style="font-size: 20px; color: var(--primary, #3b5d32);">
                        <x-ikon nama="mortarboard" />
                    </div>
                </div>
                <div class="kpi-card__num mb-1 text-primary">{{ number_format($siswaCount, 0, ',', '.') }}</div>
                <div class="fw-semibold text-dark mb-2" style="font-size: 14.5px;">Siswa Aktif Tingkat XII</div>
                <p class="field__hint mb-0">
                    Siswa terdaftar aktif pada tahun ajaran <strong>{{ $tahunAsal }}</strong>. Seluruh siswa ini wajib tercatat keputusannya pada berkas resmi kelulusan sekolah.
                </p>
            </div>
            <div class="pt-3 mt-3 border-top d-flex align-items-center gap-2 text-muted" style="font-size: 13px;">
                <x-ikon nama="shield-check" />
                <span>Status alumni diterbitkan atomik saat eksekusi final disetujui.</span>
            </div>
        </div>

        {{-- Kartu Form Upload Berkas Kelulusan --}}
        <div class="card-hifi p-4 d-flex flex-column justify-content-between">
            <div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h3 class="card-hifi__title mb-0" style="font-size: 18px !important;">Mulai Verifikasi Kelulusan</h3>
                        <span class="field__hint">Unggah berkas keputusan kelulusan siswa tingkat akhir.</span>
                    </div>
                    <button type="button" class="btn-hifi btn-hifi--ghost btn-hifi--sm" data-bs-toggle="modal" data-bs-target="#templateModal">
                        <x-ikon nama="file-earmark-excel" /> Template Excel
                    </button>
                </div>

                <form method="POST" action="{{ route('admin.kelulusan.upload') }}" enctype="multipart/form-data" id="formUploadKelulusan">
                    @csrf
                    <input type="hidden" name="tahun_asal" value="{{ $tahunAsal }}">
                    <input type="hidden" name="tahun_tujuan" value="{{ $tahunTujuan }}">
                    @if (request()->query('coordinator_id'))
                        <input type="hidden" name="coordinator_id" value="{{ request()->query('coordinator_id') }}">
                    @endif

                    <div class="field mb-3">
                        <label class="field__label" for="effective_date">
                            Tanggal Kelulusan<span class="field__req"> *</span>
                        </label>
                        <input type="date" id="effective_date" name="effective_date" class="select-hifi" value="{{ old('effective_date', today()->toDateString()) }}" required>
                        <span class="field__hint mt-1">Tanggal penetapan resmi status alumni bagi siswa yang lulus.</span>
                    </div>

                    <div class="field mb-3">
                        <label class="field__label mb-2">
                            Berkas Spreadsheet Kelulusan (XLSX / CSV)<span class="field__req"> *</span>
                        </label>
                        <div class="file-upload-box" id="dropzoneBox">
                            <input type="file" id="berkasKelulusan" name="berkas" accept=".xlsx,.csv" required onchange="handleFileSelect(this)">
                            <div class="file-upload-icon">
                                <x-ikon nama="cloud-arrow-up" />
                            </div>
                            <div class="fw-bold text-dark mb-1" id="fileNameDisplay" style="font-size: 14.5px;">
                                Klik untuk memilih file Excel atau seret ke sini
                            </div>
                            <div class="text-muted small" id="fileHintDisplay">
                                Format yang didukung: .xlsx atau .csv (maksimal 10 MB)
                            </div>
                        </div>
                        <span class="field__hint mt-2">
                            Pastikan berkas memuat kolom <code>NIS</code> dan <code>Status Kelulusan</code> (LULUS / TIDAK LULUS / BELUM TERVERIFIKASI).
                        </span>
                    </div>
                </form>
            </div>

            <div class="pt-3 border-top d-flex justify-content-end align-items-center">
                <button type="submit" form="formUploadKelulusan" class="btn-hifi" style="padding: 10px 24px;">
                    <x-ikon nama="upload" /> Unggah &amp; Lanjutkan ke Pratinjau
                </button>
            </div>
        </div>
    </div>

    {{-- Riwayat Operasi Kelulusan --}}
    <x-card title="Riwayat & Draf Operasi Kelulusan" flush>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th style="width: 140px;">Tahun Asal</th>
                        <th style="width: 140px;">Tahun Tujuan</th>
                        <th>Referensi Dokumen</th>
                        <th style="width: 180px;">Status Alur</th>
                        <th>Operator</th>
                        <th style="width: 160px;">Waktu Dibuat</th>
                        <th class="is-num" style="width: 160px;">Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($operations as $op)
                        <tr>
                            <td class="is-strong">#{{ $op->id }}</td>
                            <td><span class="chip chip--neutral">{{ $op->source_year }}</span></td>
                            <td>
                                @if ($op->target_year)
                                    <span class="chip chip--solid-lembut">{{ $op->target_year }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <strong class="text-dark">{{ $op->source_metadata['official_reference'] ?? '-' }}</strong>
                                <div class="text-muted small">Tgl Efektif: {{ $op->source_metadata['effective_date'] ?? '-' }}</div>
                            </td>
                            <td>
                                @if ($op->state === 'completed')
                                    <span class="chip chip--green">Selesai</span>
                                @elseif ($op->state === 'ready')
                                    <span class="chip chip--solid-lembut">Siap Finalisasi</span>
                                @elseif ($op->state === 'needs_attention')
                                    <span class="chip chip--neutral" style="color: #b45309; background: #fef3c7;">Perlu Tindak Lanjut</span>
                                @elseif ($op->state === 'failed')
                                    <span class="chip chip--neutral" style="color: #b91c1c; background: #fee2e2;">Gagal</span>
                                @else
                                    <span class="chip chip--neutral">{{ ucfirst($op->state) }}</span>
                                @endif
                            </td>
                            <td>{{ $op->actor->nama ?? $op->actor->username ?? 'Admin' }}</td>
                            <td class="is-muted is-nowrap">{{ $op->created_at->format('d/m/Y H:i') }}</td>
                            <td class="is-num tbl__aksi">
                                @if ($op->state === 'completed')
                                    <a href="{{ route('admin.kelulusan.show', $op->id) }}" class="btn-hifi btn-hifi--sm btn-hifi--ghost">
                                        <x-ikon nama="eye" /> Lihat Hasil
                                    </a>
                                @else
                                    <a href="{{ route('admin.kelulusan.review', $op->id) }}" class="btn-hifi btn-hifi--sm">
                                        <x-ikon nama="arrow-right" /> Buka Draf
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-state text-center py-4 text-muted" style="font-size: 13.5px;">
                                Belum ada catatan operasi kelulusan. Silakan unggah berkas resmi di atas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($operations->hasPages())
            <x-slot:foot>
                <div class="d-flex justify-content-between align-items-center w-100">
                    <span class="small text-muted">Menampilkan {{ $operations->count() }} draf operasi</span>
                    <div>{{ $operations->links() }}</div>
                </div>
            </x-slot:foot>
        @endif
    </x-card>
</div>

<script>
    function handleFileSelect(input) {
        const box = document.getElementById('dropzoneBox');
        const nameDisplay = document.getElementById('fileNameDisplay');
        const hintDisplay = document.getElementById('fileHintDisplay');

        if (input.files && input.files[0]) {
            const file = input.files[0];
            const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
            box.classList.add('has-file');
            nameDisplay.innerHTML = `<span class="text-primary"><x-ikon nama="file-earmark-check" /> ${file.name}</span>`;
            hintDisplay.innerHTML = `Ukuran: ${sizeMb} MB &bull; Siap diverifikasi`;
        } else {
            box.classList.remove('has-file');
            nameDisplay.textContent = 'Klik untuk memilih file Excel atau seret ke sini';
            hintDisplay.textContent = 'Format yang didukung: .xlsx atau .csv (maksimal 10 MB)';
        }
    }
</script>

{{-- Modal Download Template Excel & Bantuan Prompt AI --}}
<div class="modal fade" id="templateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content card-hifi p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center gap-2">
                    <div style="font-size: 22px; color: var(--primary, #3b5d32);"><x-ikon nama="file-earmark-spreadsheet" /></div>
                    <h4 class="card-hifi__title mb-0" style="font-size: 18px !important;">Template Berkas Kelulusan Kelas XII</h4>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="mb-4">
                <p class="field__hint mb-3" style="font-size: 13.5px; color: #374151;">
                    Gunakan berkas template resmi yang telah disesuaikan dengan skema database sekolah (memuat data siswa aktif tingkat XII tahun <strong>{{ $tahunAsal }}</strong>).
                </p>

                {{-- Box Opsi Download Template --}}
                <div class="card-hifi p-3 mb-4 d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 border" style="background: #fafbfa;">
                    <div>
                        <div class="fw-bold text-dark mb-1" style="font-size: 14.5px;">Unduh Template Excel (.XLSX) Resmi</div>
                        <div class="text-muted small">Kolom otomatis terisi: <code>nis</code>, <code>nama_siswa</code>, <code>kelas</code>, dan kolom <code>status_kelulusan</code>.</div>
                    </div>
                    <a href="{{ route('admin.kelulusan.template', ['tahun_asal' => $tahunAsal]) }}" class="btn-hifi text-nowrap">
                        <x-ikon nama="download" /> Unduh File Template
                    </a>
                </div>

                {{-- Box Bantuan Prompt AI --}}
                <div class="card-hifi p-3 border">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <x-ikon nama="stars" class="text-primary" />
                            <strong style="font-size: 14px; color: #111827;">Bantuan Prompt AI untuk Format Excel</strong>
                        </div>
                        <button type="button" class="btn-hifi btn-hifi--ghost btn-hifi--sm" onclick="navigator.clipboard.writeText(document.getElementById('aiPromptText').value); alert('Prompt berhasil disalin ke clipboard!');">
                            <x-ikon nama="clipboard" /> Salin Prompt
                        </button>
                    </div>
                    <p class="field__hint mb-2" style="font-size: 13px;">
                        Punya rekap mentah dari rapat guru atau catatan kelulusan yang berantakan? Salin teks prompt di bawah ke ChatGPT/Claude/Gemini bersama data mentah Anda agar otomatis diubah menjadi format tabel yang cocok:
                    </p>
                    <textarea id="aiPromptText" class="select-hifi w-100 font-monospace" rows="6" readonly style="font-size: 12.5px; background: #ffffff;">{{ $aiPrompt }}</textarea>
                </div>
            </div>

            <div class="d-flex justify-content-end pt-3 border-top">
                <button type="button" class="btn-hifi btn-hifi--ghost" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endsection
