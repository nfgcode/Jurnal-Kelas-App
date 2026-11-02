@extends('layouts.app')

@section('title', 'Penerimaan Siswa Baru Tingkat X')

@section('content')
<div class="intake-view space-y-5">
    <style>
        .intake-view .page-head {
            margin-top: 8px !important;
            margin-bottom: 24px !important;
        }
        .intake-view .page-head__title {
            font-size: 22px !important;
            font-weight: 700 !important;
            line-height: 1.25 !important;
            color: #111827 !important;
            margin-bottom: 6px !important;
        }
        .intake-view .page-head__sub {
            font-size: 14px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
            color: #4b5563 !important;
            margin: 0 !important;
        }
        .intake-view .breadcrumb-nav {
            font-size: 13px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
        }
        .intake-view .breadcrumb-nav a {
            color: #4b5563 !important;
        }
        .intake-view .breadcrumb-nav a:hover {
            color: var(--primary, #3b5d32) !important;
        }
        .intake-view .card-hifi__title {
            font-size: 18.5px !important;
            font-weight: 700 !important;
            line-height: 1.3 !important;
            color: #111827 !important;
        }
        .intake-view .field__label {
            font-size: 14px !important;
            font-weight: 600 !important;
            line-height: 1.4 !important;
            color: #1f2937 !important;
        }
        .intake-view .field__hint {
            font-size: 13px !important;
            font-weight: 400 !important;
            line-height: 1.4 !important;
            color: #4b5563 !important;
        }
        .intake-view .kpi-card__num {
            font-size: 26px !important;
            font-weight: 700 !important;
            line-height: 1.2 !important;
            color: #111827 !important;
        }
        .intake-view .btn-hifi {
            font-size: 14px !important;
            font-weight: 600 !important;
            line-height: 1.4 !important;
        }
        .intake-view .tbl th {
            font-size: 12.5px !important;
            font-weight: 600 !important;
            line-height: 1.4 !important;
            padding: 10px 14px !important;
        }
        .intake-view .tbl td {
            font-size: 13.5px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
            padding: 10px 14px !important;
        }
        .intake-view .file-upload-box {
            border: 2px dashed #cbd5e1;
            border-radius: var(--radius-card, 12px);
            background: #fafbfa;
            padding: 22px 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }
        .intake-view .file-upload-box:hover {
            border-color: var(--primary, #3b5d32);
            background: #ffffff;
            box-shadow: 0 4px 14px rgba(59, 93, 50, 0.06);
        }
        .intake-view .file-upload-box.has-file {
            border-color: var(--primary, #3b5d32);
            border-style: solid;
            background: #fdfefe;
        }
        .intake-view .file-upload-box input[type="file"] {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
            z-index: 2;
        }
        .intake-view .file-upload-icon {
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
            <span class="text-dark fw-semibold" aria-current="page">Penerimaan Siswa Baru Tingkat X</span>
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
                        Penerimaan siswa baru ini merupakan tahap ke-3 (tahap final) pada alur transisi terpadu Anda.
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
            <h2 class="page-head__title">Penerimaan Siswa Baru Tingkat X</h2>
            <p class="page-head__sub">
                Pendaftaran dan penempatan rombel siswa baru kelas X tahun ajaran mendatang secara aman, rapi, dan terisolasi.
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
                <span>Terjadi kendala pada pengajuan siswa baru:</span>
            </div>
            <ul class="mb-0 ps-3 text-danger" style="font-size: 13.5px; line-height: 1.5;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Filter Periode Target --}}
    <div class="card-hifi p-4 mb-4">
        <form method="GET" action="{{ route('admin.intake.index') }}" id="formFilterIntake">
            <div class="field">
                <label class="field__label" for="tahun_tujuan">Tahun Ajaran Tujuan Penerimaan Siswa Baru</label>
                <select name="tahun_tujuan" id="tahun_tujuan" class="select-hifi" onchange="document.getElementById('formFilterIntake').submit()">
                    @foreach ($tahunList as $t)
                        <option value="{{ $t->kode }}" @selected($t->kode === $tahunTujuan)>
                            {{ $t->kode }} {{ $t->aktif ? '(Aktif Saat Ini)' : '(Mendatang / Nonaktif)' }}
                        </option>
                    @endforeach
                </select>
                <span class="field__hint">Pilih tahun ajaran sasaran tempat siswa baru kelas X akan didaftarkan.</span>
            </div>
        </form>
    </div>

    {{-- Bento Baris 2: Info Rombel X (Kiri) + Form Upload (Kanan) --}}
    <div style="display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 2fr); gap: 16px; align-items: stretch;" class="mb-4">
        {{-- Kartu Kesiapan Rombel Tingkat X --}}
        <div class="card-hifi p-4 d-flex flex-column justify-content-between">
            <div>
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="chip chip--neutral fw-bold">Rombel Kelas X Siap</span>
                    <div style="font-size: 20px; color: var(--primary, #3b5d32);">
                        <x-ikon nama="people" />
                    </div>
                </div>
                <div class="kpi-card__num mb-1 text-primary">{{ $rombelXList->count() }}</div>
                <div class="fw-semibold text-dark mb-2" style="font-size: 14.5px;">Rombel Kelas X Terdaftar</div>
                
                @if ($rombelXList->isEmpty())
                    <div class="card-hifi p-3 mb-2 border-l-4" style="border-left-color: #b45309; background: #fffbeb;">
                        <strong style="color: #92400e; font-size: 13.5px;">Rombel Belum Tersedia:</strong>
                        <p class="mb-0 text-muted" style="font-size: 12.5px;">Belum ada rombel kelas X pada tahun {{ $tahunTujuan }}. Buat rombel kelas X terlebih dahulu sebelum mengimpor siswa baru.</p>
                    </div>
                @else
                    <p class="field__hint mb-2">
                        Siswa baru wajib ditempatkan pada salah satu rombel kelas X yang tersedia berikut:
                    </p>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        @foreach ($rombelXList as $rombel)
                            <span class="chip chip--neutral">
                                <strong>{{ $rombel->nama_kelas }}</strong> ({{ $rombel->jurusan_kode }})
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="pt-3 mt-3 border-top d-flex align-items-center gap-2 text-muted" style="font-size: 13px;">
                <x-ikon nama="shield-check" />
                <span>NIS dan NISN divalidasi keunikan datanya secara otomatis.</span>
            </div>
        </div>

        {{-- Kartu Form Upload Siswa Baru --}}
        <div class="card-hifi p-4 d-flex flex-column justify-content-between">
            <div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h3 class="card-hifi__title mb-0" style="font-size: 18px !important;">Mulai Pendaftaran Siswa Baru</h3>
                        <span class="field__hint">Unggah berkas pendaftaran peserta didik baru hasil PPDB.</span>
                    </div>
                    <button type="button" class="btn-hifi btn-hifi--ghost btn-hifi--sm" data-bs-toggle="modal" data-bs-target="#templateIntakeModal">
                        <x-ikon nama="file-earmark-excel" /> Template Excel
                    </button>
                </div>

                <form method="POST" action="{{ route('admin.intake.upload') }}" enctype="multipart/form-data" id="formUploadIntake">
                    @csrf
                    <input type="hidden" name="tahun_tujuan" value="{{ $tahunTujuan }}">
                    @if (request()->query('coordinator_id'))
                        <input type="hidden" name="coordinator_id" value="{{ request()->query('coordinator_id') }}">
                    @endif

                    @php
                        $targetObj = collect($tahunList)->firstWhere('kode', $tahunTujuan);
                        $defaultDate = $targetObj && $targetObj->mulai ? $targetObj->mulai->toDateString() : today()->toDateString();
                    @endphp

                    <div class="mb-3">
                        <input type="hidden" name="official_reference" value="{{ old('official_reference', 'PPDB-' . str_replace('/', '-', $tahunTujuan)) }}">
                        <div class="field">
                            <label class="field__label" for="effective_date">
                                Tanggal Masuk Efektif<span class="field__req"> *</span>
                            </label>
                            <input type="date" id="effective_date" name="effective_date" class="select-hifi" value="{{ old('effective_date', $defaultDate) }}" required>
                            <span class="field__hint">Tanggal resmi pembukaan tahun ajaran baru atau mulai masuk sekolah.</span>
                        </div>
                    </div>

                    <div class="field mb-3">
                        <label class="field__label mb-2">Berkas Spreadsheet Siswa Baru (XLSX / CSV)<span class="field__req"> *</span></label>
                        <div class="file-upload-box" id="dropzoneIntakeBox">
                            <input type="file" name="berkas" id="inputBerkasIntake" accept=".xlsx,.csv" required onchange="handleFileSelectIntake(this)">
                            <div class="file-upload-icon">
                                <x-ikon nama="cloud-arrow-up" />
                            </div>
                            <div class="fw-bold text-dark mb-1" id="fileNameIntake" style="font-size: 14.5px;">
                                Klik untuk memilih file Excel atau seret ke sini
                            </div>
                            <div class="text-muted small" id="fileHintIntake">
                                Format: .xlsx atau .csv (kolom NIS, NISN, Nama Lengkap, Email, Kelas X Tujuan)
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="pt-3 border-top d-flex justify-content-end align-items-center">
                <button type="submit" form="formUploadIntake" class="btn-hifi" style="padding: 10px 24px;" {{ $rombelXList->isEmpty() ? 'disabled' : '' }}>
                    <x-ikon nama="upload" /> Unggah &amp; Lanjut ke Pratinjau
                </button>
            </div>
        </div>
    </div>

    {{-- Riwayat Sesi Alur Penerimaan --}}
    <x-card title="Riwayat Sesi Alur Penerimaan Siswa Baru" flush>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th style="width: 180px;">Tahun Ajaran Sasaran</th>
                        <th style="width: 140px;">Status Alur</th>
                        <th style="width: 140px;">Validasi Data</th>
                        <th>Dibuat Oleh</th>
                        <th style="width: 160px;">Waktu Dibuat</th>
                        <th class="is-num" style="width: 160px;">Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($operations as $op)
                        <tr>
                            <td class="is-strong">#{{ $op->id }}</td>
                            <td><span class="chip chip--solid-lembut">{{ $op->target_year }}</span></td>
                            <td>
                                @if ($op->state === 'completed')
                                    <span class="chip chip--green">Selesai</span>
                                @elseif ($op->state === 'ready')
                                    <span class="chip chip--solid-lembut">Siap Finalisasi</span>
                                @elseif ($op->state === 'failed')
                                    <span class="chip chip--neutral" style="color: #b91c1c; background: #fee2e2;">Gagal</span>
                                @else
                                    <span class="chip chip--neutral">{{ ucfirst($op->state) }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($op->validation === 'valid')
                                    <span class="chip chip--green">Valid</span>
                                @elseif ($op->validation === 'unverified')
                                    <span class="chip chip--neutral" style="color: #b45309; background: #fef3c7;">Belum Verifikasi</span>
                                @else
                                    <span class="chip chip--neutral" style="color: #b91c1c; background: #fee2e2;">Perlu Perbaikan</span>
                                @endif
                            </td>
                            <td>{{ $op->actor?->nama ?? $op->actor?->username ?? 'Admin' }}</td>
                            <td class="is-muted is-nowrap">{{ $op->created_at->format('d/m/Y H:i') }}</td>
                            <td class="is-num tbl__aksi">
                                @if ($op->state === 'completed')
                                    <a href="{{ route('admin.intake.show', $op->id) }}" class="btn-hifi btn-hifi--sm btn-hifi--ghost">
                                        <x-ikon nama="check2-all" /> Lihat Rekap
                                    </a>
                                @else
                                    <a href="{{ route('admin.intake.review', $op->id) }}" class="btn-hifi btn-hifi--sm">
                                        <x-ikon nama="pencil" /> Tinjau Pratinjau
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-state text-center py-4 text-muted" style="font-size: 13.5px;">
                                Belum ada sesi penerimaan siswa baru yang dimulai. Silakan buat alur baru di atas.
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

{{-- Script Interaksi Dropzone Intake --}}
<script>
    function handleFileSelectIntake(input) {
        const box = document.getElementById('dropzoneIntakeBox');
        const nameDisplay = document.getElementById('fileNameIntake');
        const hintDisplay = document.getElementById('fileHintIntake');

        if (input.files && input.files[0]) {
            const file = input.files[0];
            const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
            box.classList.add('has-file');
            nameDisplay.innerHTML = `<span class="text-primary"><x-ikon nama="file-earmark-check" /> ${file.name}</span>`;
            hintDisplay.innerHTML = `Ukuran: ${sizeMb} MB &bull; Siap diverifikasi`;
        } else {
            box.classList.remove('has-file');
            nameDisplay.textContent = 'Klik untuk memilih file Excel atau seret ke sini';
            hintDisplay.textContent = 'Format: .xlsx atau .csv (kolom NIS, NISN, Nama Lengkap, Email, Kelas X Tujuan)';
        }
    }
</script>

{{-- Modal Template Excel & Bantuan AI Intake --}}
<div class="modal fade" id="templateIntakeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content card-hifi p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center gap-2">
                    <div style="font-size: 22px; color: var(--primary, #3b5d32);"><x-ikon nama="file-earmark-spreadsheet" /></div>
                    <h4 class="card-hifi__title mb-0" style="font-size: 18px !important;">Template Berkas Penerimaan Siswa Baru</h4>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="mb-4">
                <p class="field__hint mb-3" style="font-size: 13.5px; color: #374151;">
                    Gunakan berkas template resmi yang memuat daftar rombel kelas tingkat X tahun <strong>{{ $tahunTujuan }}</strong> yang sah.
                </p>

                {{-- Box Download Template --}}
                <div class="card-hifi p-3 mb-4 d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 border" style="background: #fafbfa;">
                    <div>
                        <div class="fw-bold text-dark mb-1" style="font-size: 14.5px;">Unduh Template Excel (.XLSX) Resmi</div>
                        <div class="text-muted small">Kolom otomatis terstandar: <code>nis</code>, <code>nisn</code>, <code>nama_siswa</code>, <code>jenis_kelamin</code>, <code>email</code>, dan <code>nama_kelas_tujuan</code>.</div>
                    </div>
                    <a href="{{ route('admin.intake.template', ['tahun_tujuan' => $tahunTujuan]) }}" class="btn-hifi text-nowrap">
                        <x-ikon nama="download" /> Unduh File Template
                    </a>
                </div>

                {{-- Box Bantuan Prompt AI --}}
                <div class="card-hifi p-3 border">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <x-ikon nama="stars" class="text-primary" />
                            <strong style="font-size: 14px; color: #111827;">Bantuan Prompt AI untuk Format Siswa Baru</strong>
                        </div>
                        <button type="button" class="btn-hifi btn-hifi--ghost btn-hifi--sm" onclick="navigator.clipboard.writeText(document.getElementById('aiPromptIntakeText').value); alert('Prompt berhasil disalin ke clipboard!');">
                            <x-ikon nama="clipboard" /> Salin Prompt
                        </button>
                    </div>
                    <p class="field__hint mb-2" style="font-size: 13px;">
                        Salin teks prompt di bawah ke ChatGPT/Claude/Gemini bersama data mentah calon siswa baru hasil PPDB untuk otomatis menghasilkan tabel yang rapi:
                    </p>
                    <textarea id="aiPromptIntakeText" class="select-hifi w-100 font-monospace" rows="6" readonly style="font-size: 12.5px; background: #ffffff;">{{ $aiPrompt }}</textarea>
                </div>
            </div>

            <div class="d-flex justify-content-end pt-3 border-top">
                <button type="button" class="btn-hifi btn-hifi--ghost" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endsection
