@extends('layouts.app')

@section('title', 'Kenaikan Kelas X–XI')

@section('content')
<div class="kenaikan-view space-y-5">
    <style>
        .kenaikan-view .page-head {
            margin-top: 8px !important;
            margin-bottom: 24px !important;
        }
        .kenaikan-view .page-head__title {
            font-size: 22px !important;
            font-weight: 700 !important;
            line-height: 1.25 !important;
            color: #111827 !important;
            margin-bottom: 6px !important;
        }
        .kenaikan-view .page-head__sub {
            font-size: 14px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
            color: #4b5563 !important;
            margin: 0 !important;
        }
        .kenaikan-view .breadcrumb-nav {
            font-size: 13px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
        }
        .kenaikan-view .breadcrumb-nav a {
            color: #4b5563 !important;
        }
        .kenaikan-view .breadcrumb-nav a:hover {
            color: var(--primary, #3b5d32) !important;
        }
        .kenaikan-view .card-hifi__title {
            font-size: 18.5px !important;
            font-weight: 700 !important;
            line-height: 1.3 !important;
            color: #111827 !important;
        }
        .kenaikan-view .field__label {
            font-size: 14px !important;
            font-weight: 600 !important;
            line-height: 1.4 !important;
            color: #1f2937 !important;
        }
        .kenaikan-view .field__hint {
            font-size: 13px !important;
            font-weight: 400 !important;
            line-height: 1.4 !important;
            color: #4b5563 !important;
        }
        .kenaikan-view .kpi-card__num {
            font-size: 26px !important;
            font-weight: 700 !important;
            line-height: 1.2 !important;
            color: #111827 !important;
        }
        .kenaikan-view .btn-hifi {
            font-size: 14px !important;
            font-weight: 600 !important;
            line-height: 1.4 !important;
        }
        .kenaikan-view .tbl th {
            font-size: 12.5px !important;
            font-weight: 600 !important;
            line-height: 1.4 !important;
            padding: 10px 14px !important;
        }
        .kenaikan-view .tbl td {
            font-size: 13.5px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
            padding: 10px 14px !important;
        }
        .kenaikan-view .file-upload-box {
            border: 2px dashed #cbd5e1;
            border-radius: var(--radius-card, 12px);
            background: #fafbfa;
            padding: 22px 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }
        .kenaikan-view .file-upload-box:hover {
            border-color: var(--primary, #3b5d32);
            background: #ffffff;
            box-shadow: 0 4px 14px rgba(59, 93, 50, 0.06);
        }
        .kenaikan-view .file-upload-box.has-file {
            border-color: var(--primary, #3b5d32);
            border-style: solid;
            background: #fdfefe;
        }
        .kenaikan-view .file-upload-box input[type="file"] {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
            z-index: 2;
        }
        .kenaikan-view .file-upload-icon {
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
        .kenaikan-view .method-card {
            border: 2px solid #e5e7eb;
            border-radius: var(--radius-card, 12px);
            padding: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            user-select: none;
            background: #fafbfa;
        }
        .kenaikan-view .method-card:hover {
            border-color: var(--primary, #3b5d32);
            background: #ffffff;
        }
        .kenaikan-view .method-card.is-selected {
            border-color: var(--primary, #3b5d32);
            background: #fdfefe;
            box-shadow: 0 4px 12px rgba(59, 93, 50, 0.08);
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
            <span class="text-dark fw-semibold" aria-current="page">Kenaikan Kelas X–XI</span>
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
                        Proses kenaikan kelas ini merupakan tahap ke-2 pada alur transisi terpadu Anda.
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
            <h2 class="page-head__title">Kenaikan Kelas X–XI</h2>
            <p class="page-head__sub">
                Pengelolaan kenaikan tingkat siswa X ke XI dan XI ke XII pada rombel tujuan. Siswa default naik kelas otomatis, dengan opsi penanganan siswa tinggal kelas.
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
                <span>Terjadi kendala pada pengajuan kenaikan kelas:</span>
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
        <form method="GET" action="{{ route('admin.kenaikan.index') }}" id="formFilterKenaikan">
            <div class="form-grid form-grid--2 mb-2">
                <div class="field">
                    <label class="field__label" for="tahun_asal">Tahun Ajaran Sumber (Asal)</label>
                    <select name="tahun_asal" id="tahun_asal" class="select-hifi" onchange="document.getElementById('formFilterKenaikan').submit()" required>
                        @foreach ($tahunList as $t)
                            <option value="{{ $t->kode }}" @selected($t->kode === $tahunAsal)>
                                {{ $t->kode }} {{ $t->aktif ? '(Aktif Saat Ini)' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <span class="field__hint">Periode asal siswa yang akan dipromosikan tingkatnya.</span>
                </div>
                <div class="field">
                    <label class="field__label" for="tahun_tujuan">Tahun Ajaran Tujuan (Kenaikan Kelas)</label>
                    <select name="tahun_tujuan" id="tahun_tujuan" class="select-hifi" onchange="document.getElementById('formFilterKenaikan').submit()">
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
                    <span class="field__hint">Tahun ajaran baru tempat rombel tingkat XI &amp; XII berada.</span>
                </div>
            </div>
        </form>
    </div>

    {{-- Bento Baris 2: Info Roster (Kiri) + Form Inisiasi (Kanan) --}}
    <div style="display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 2fr); gap: 16px; align-items: stretch;" class="mb-4">
        {{-- Kartu Info Roster X & XI --}}
        <div class="card-hifi p-4 d-flex flex-column justify-content-between">
            <div>
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="chip chip--neutral fw-bold">Roster Siswa Tingkat X &amp; XI</span>
                    <div style="font-size: 20px; color: var(--primary, #3b5d32);">
                        <x-ikon nama="arrow-up-circle" />
                    </div>
                </div>
                <div class="kpi-card__num mb-1 text-primary">{{ number_format($totalSiswa, 0, ',', '.') }}</div>
                <div class="fw-semibold text-dark mb-2" style="font-size: 14.5px;">Total Siswa Tingkat X &amp; XI</div>
                <div class="d-flex justify-content-between text-muted small p-2 rounded mb-3" style="background: rgba(0,0,0,0.03);">
                    <span>Kelas X: <strong class="text-dark">{{ $siswaCountX }}</strong> siswa</span>
                    <span>Kelas XI: <strong class="text-dark">{{ $siswaCountXI }}</strong> siswa</span>
                </div>
                <p class="field__hint mb-0">
                    Siswa terdaftar aktif pada tahun ajaran <strong>{{ $tahunAsal }}</strong>. Siswa tingkat XII otomatis dikecualikan karena masuk dalam alur kelulusan.
                </p>
            </div>
            <div class="pt-3 mt-3 border-top d-flex align-items-center gap-2 text-muted" style="font-size: 13px;">
                <x-ikon nama="shield-check" />
                <span>Rombel paralel terpetakan otomatis secara konsisten.</span>
            </div>
        </div>

        {{-- Kartu Form Inisiasi Kenaikan --}}
        <div class="card-hifi p-4 d-flex flex-column justify-content-between">
            <div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h3 class="card-hifi__title mb-0" style="font-size: 18px !important;">Mulai Kenaikan Kelas Baru</h3>
                        <span class="field__hint">Pilih cara pemetaan siswa atau unggah berkas pengecualian.</span>
                    </div>
                    <button type="button" class="btn-hifi btn-hifi--ghost btn-hifi--sm" data-bs-toggle="modal" data-bs-target="#templateKenaikanModal">
                        <x-ikon nama="file-earmark-excel" /> Template Excel
                    </button>
                </div>

                <form method="POST" action="{{ route('admin.kenaikan.inisiasi') }}" enctype="multipart/form-data" id="formInisiasiKenaikan">
                    @csrf
                    <input type="hidden" name="tahun_asal" value="{{ $tahunAsal }}">
                    <input type="hidden" name="tahun_tujuan" value="{{ $tahunTujuan }}">
                    @if (request()->query('coordinator_id'))
                        <input type="hidden" name="coordinator_id" value="{{ request()->query('coordinator_id') }}">
                    @endif

                    <div class="field mb-3">
                        <label class="field__label mb-2">Pilih Cara Pemetaan Kenaikan Kelas<span class="field__req"> *</span></label>
                        <div class="grid-row grid-row--2 mb-2" style="gap: 12px;">
                            <label class="method-card is-selected m-0 d-flex align-items-start gap-2" id="cardMetodeDefault" for="metodeDefault">
                                <input type="radio" name="metode" id="metodeDefault" value="default" checked style="width: 18px; height: 18px; accent-color: var(--primary, #3b5d32); margin-top: 2px;" onchange="switchMetode('default')">
                                <div>
                                    <strong class="text-dark d-block" style="font-size: 14px;">Otomatis Naik Kelas</strong>
                                    <span class="text-muted" style="font-size: 12.5px; line-height: 1.4;">Semua siswa langsung dipetakan naik ke kelas paralel berikutnya. Anda bisa tandai siswa tinggal kelas nanti di pratinjau.</span>
                                </div>
                            </label>

                            <label class="method-card m-0 d-flex align-items-start gap-2" id="cardMetodeUpload" for="metodeUpload">
                                <input type="radio" name="metode" id="metodeUpload" value="upload" style="width: 18px; height: 18px; accent-color: var(--primary, #3b5d32); margin-top: 2px;" onchange="switchMetode('upload')">
                                <div>
                                    <strong class="text-dark d-block" style="font-size: 14px;">Unggah File Excel</strong>
                                    <span class="text-muted" style="font-size: 12.5px; line-height: 1.4;">Unggah berkas spreadsheet jika daftar siswa tinggal kelas sudah disiapkan terlebih dahulu.</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- Dropzone Upload (Tampil jika memilih Unggah Excel) --}}
                    <div id="sectionUploadBerkas" class="field mb-3 d-none">
                        <label class="field__label mb-2">Berkas Spreadsheet Pengecualian Kenaikan (XLSX / CSV)<span class="field__req"> *</span></label>
                        <div class="file-upload-box" id="dropzoneKenaikanBox">
                            <input type="file" name="berkas" id="inputBerkasKenaikan" accept=".xlsx,.csv" onchange="handleFileSelectKenaikan(this)">
                            <div class="file-upload-icon">
                                <x-ikon nama="cloud-arrow-up" />
                            </div>
                            <div class="fw-bold text-dark mb-1" id="fileNameKenaikan" style="font-size: 14.5px;">
                                Klik untuk memilih file Excel atau seret ke sini
                            </div>
                            <div class="text-muted small" id="fileHintKenaikan">
                                Format: .xlsx atau .csv (kolom NIS dan Status Kenaikan)
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="pt-3 border-top d-flex justify-content-end align-items-center">
                <button type="submit" form="formInisiasiKenaikan" class="btn-hifi" style="padding: 10px 24px;" {{ $tahunTujuanList->isEmpty() ? 'disabled' : '' }}>
                    <x-ikon nama="arrow-right" /> Buat Alur &amp; Lanjut ke Pratinjau
                </button>
            </div>
        </div>
    </div>

    {{-- Riwayat Alur Kenaikan Kelas --}}
    <x-card title="Riwayat Sesi Alur Kenaikan Kelas" flush>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th style="width: 180px;">Periode Transisi</th>
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
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="chip chip--neutral">{{ $op->source_year }}</span>
                                    <x-ikon nama="arrow-right" />
                                    <span class="chip chip--solid-lembut">{{ $op->target_year }}</span>
                                </div>
                            </td>
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
                                    <a href="{{ route('admin.kenaikan.show', $op->id) }}" class="btn-hifi btn-hifi--sm btn-hifi--ghost">
                                        <x-ikon nama="check2-all" /> Lihat Rekap
                                    </a>
                                @else
                                    <a href="{{ route('admin.kenaikan.review', $op->id) }}" class="btn-hifi btn-hifi--sm">
                                        <x-ikon nama="pencil" /> Tinjau Pratinjau
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-state text-center py-4 text-muted" style="font-size: 13.5px;">
                                Belum ada sesi kenaikan kelas yang dimulai. Silakan buat alur baru di atas.
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

{{-- Script Interaksi Radio & Dropzone --}}
<script>
    function switchMetode(type) {
        const cardDef = document.getElementById('cardMetodeDefault');
        const cardUpl = document.getElementById('cardMetodeUpload');
        const sectionUpload = document.getElementById('sectionUploadBerkas');
        const inputBerkas = document.getElementById('inputBerkasKenaikan');

        if (type === 'default') {
            cardDef.classList.add('is-selected');
            cardUpl.classList.remove('is-selected');
            sectionUpload.classList.add('d-none');
            inputBerkas.removeAttribute('required');
        } else {
            cardUpl.classList.add('is-selected');
            cardDef.classList.remove('is-selected');
            sectionUpload.classList.remove('d-none');
            inputBerkas.setAttribute('required', 'required');
        }
    }

    function handleFileSelectKenaikan(input) {
        const box = document.getElementById('dropzoneKenaikanBox');
        const nameDisplay = document.getElementById('fileNameKenaikan');
        const hintDisplay = document.getElementById('fileHintKenaikan');

        if (input.files && input.files[0]) {
            const file = input.files[0];
            const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
            box.classList.add('has-file');
            nameDisplay.innerHTML = `<span class="text-primary"><x-ikon nama="file-earmark-check" /> ${file.name}</span>`;
            hintDisplay.innerHTML = `Ukuran: ${sizeMb} MB &bull; Siap diverifikasi`;
        } else {
            box.classList.remove('has-file');
            nameDisplay.textContent = 'Klik untuk memilih file Excel atau seret ke sini';
            hintDisplay.textContent = 'Format: .xlsx atau .csv (kolom NIS dan Status Kenaikan)';
        }
    }
</script>

{{-- Modal Template Excel & Bantuan AI Kenaikan --}}
<div class="modal fade" id="templateKenaikanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content card-hifi p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center gap-2">
                    <div style="font-size: 22px; color: var(--primary, #3b5d32);"><x-ikon nama="file-earmark-spreadsheet" /></div>
                    <h4 class="card-hifi__title mb-0" style="font-size: 18px !important;">Template Berkas Kenaikan Kelas X–XI</h4>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="mb-4">
                <p class="field__hint mb-3" style="font-size: 13.5px; color: #374151;">
                    Gunakan berkas template resmi yang memuat data siswa aktif tingkat X dan XI tahun <strong>{{ $tahunAsal }}</strong> untuk menandai siswa yang tinggal kelas.
                </p>

                {{-- Box Download Template --}}
                <div class="card-hifi p-3 mb-4 d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 border" style="background: #fafbfa;">
                    <div>
                        <div class="fw-bold text-dark mb-1" style="font-size: 14.5px;">Unduh Template Excel (.XLSX) Resmi</div>
                        <div class="text-muted small">Kolom otomatis terisi: <code>nis</code>, <code>nama_siswa</code>, <code>kelas_asal</code>, dan <code>status_kenaikan</code>.</div>
                    </div>
                    <a href="{{ route('admin.kenaikan.template', ['tahun_asal' => $tahunAsal]) }}" class="btn-hifi text-nowrap">
                        <x-ikon nama="download" /> Unduh File Template
                    </a>
                </div>

                {{-- Box Bantuan Prompt AI --}}
                <div class="card-hifi p-3 border">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <x-ikon nama="stars" class="text-primary" />
                            <strong style="font-size: 14px; color: #111827;">Bantuan Prompt AI untuk Format Kenaikan</strong>
                        </div>
                        <button type="button" class="btn-hifi btn-hifi--ghost btn-hifi--sm" onclick="navigator.clipboard.writeText(document.getElementById('aiPromptKenaikanText').value); alert('Prompt berhasil disalin ke clipboard!');">
                            <x-ikon nama="clipboard" /> Salin Prompt
                        </button>
                    </div>
                    <p class="field__hint mb-2" style="font-size: 13px;">
                        Salin teks prompt di bawah ke ChatGPT/Claude/Gemini bersama catatan rapat dewan guru Anda untuk otomatis menghasilkan data kenaikan yang cocok:
                    </p>
                    <textarea id="aiPromptKenaikanText" class="select-hifi w-100 font-monospace" rows="6" readonly style="font-size: 12.5px; background: #ffffff;">{{ $aiPrompt }}</textarea>
                </div>
            </div>

            <div class="d-flex justify-content-end pt-3 border-top">
                <button type="button" class="btn-hifi btn-hifi--ghost" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endsection
