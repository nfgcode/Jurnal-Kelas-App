@extends('layouts.app')

@section('title', 'Impor Data')

@push('styles')
<style>
    .panduan-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 12px;
    }
    .panduan-item {
        background: var(--n-100);
        border: 1px solid var(--n-200);
        border-radius: var(--radius-card);
        padding: 12px 14px;
    }
    .panduan-item__header {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 6px;
    }
    .panduan-item__no {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: var(--p-500, #2563eb);
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        flex-shrink: 0;
    }
    .panduan-item__judul {
        font-size: 12.5px;
        font-weight: 600;
        color: var(--n-900);
    }
    .panduan-item__isi {
        font-size: 11px;
        color: var(--n-600);
        line-height: 1.5;
        margin: 0;
    }
    .prompt-box {
        background: var(--n-100);
        border: 1px solid var(--n-300);
        border-radius: var(--radius-card);
        padding: 14px;
    }
    .prompt-textarea {
        width: 100%;
        height: 200px;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 11px;
        line-height: 1.5;
        background: #fff;
        border: 1px solid var(--n-300);
        border-radius: 6px;
        padding: 10px;
        color: var(--n-900);
        resize: vertical;
    }
    .banner-privasi {
        background: #fef3c7;
        border: 1px solid #f59e0b;
        color: #92400e;
        border-radius: 8px;
        padding: 10px 14px;
        font-size: 11.5px;
        line-height: 1.5;
    }
</style>
@endpush

@section('content')
<div class="impor-excel-view space-y-5">
    <style>
        .impor-excel-view .page-head {
            margin-top: 8px !important;
            margin-bottom: 24px !important;
        }
        .impor-excel-view .page-head__title {
            font-size: 22px !important;
            font-weight: 700 !important;
            line-height: 1.25 !important;
            color: #111827 !important;
            margin-bottom: 6px !important;
        }
        .impor-excel-view .page-head__sub {
            font-size: 14px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
            color: #4b5563 !important;
            margin: 0 !important;
        }
        .impor-excel-view .breadcrumb-nav {
            font-size: 13px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
        }
        .impor-excel-view .breadcrumb-nav a {
            color: #4b5563 !important;
        }
        .impor-excel-view .breadcrumb-nav a:hover {
            color: var(--primary, #3b5d32) !important;
        }
        .impor-excel-view .btn-hifi {
            font-size: 14px !important;
            font-weight: 600 !important;
            line-height: 1.4 !important;
        }
        .action-card-btn {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            text-align: left;
            width: 100%;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .action-card-btn:hover {
            border-color: var(--primary, #3b5d32);
            box-shadow: 0 4px 12px rgba(59, 93, 50, 0.08);
            transform: translateY(-1px);
        }
    </style>

    {{-- Breadcrumb Navigasi --}}
    <nav class="breadcrumb-nav mb-3" aria-label="Breadcrumb">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Dashboard</a>
            <span class="text-muted">&rsaquo;</span>
            <a href="{{ route('admin.akun.index') }}" class="text-decoration-none">Manajemen Akun</a>
            <span class="text-muted">&rsaquo;</span>
            <span class="text-dark fw-semibold" aria-current="page">Impor Data Siswa &amp; Guru</span>
        </div>
    </nav>

    {{-- Bento Hero Card Header (Fase 1) --}}
    <div class="card-hifi p-4 mb-4 border-l-4" style="border-left-color: var(--primary, #3b5d32);">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <h2 class="page-head__title mb-0" style="font-size: 21px !important;">Impor Massal Siswa &amp; Guru</h2>
                    <span class="chip chip--green">{{ $jumlahKelas }} Rombel Siap</span>
                </div>
                <p class="page-head__sub">
                    Unduh template resmi, sesuaikan data dengan panduan/AI, lalu unggah berkas untuk mendaftarkan akun sekaligus.
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.akun.index') }}" class="btn-hifi btn-hifi--ghost">
                    <x-ikon nama="arrow-left" /> Daftar Akun
                </a>
            </div>
        </div>
    </div>

    @if ($jumlahKelas === 0)
        <div class="card-hifi p-4 mb-4 border-l-4" style="border-left-color: #b91c1c; background: #fff5f5;">
            <div class="d-flex align-items-center gap-3">
                <x-ikon nama="exclamation-circle" class="text-danger fs-4" />
                <div>
                    <h5 class="fw-bold text-danger mb-1" style="font-size: 15px;">Belum Ada Rombel Kelas Terdaftar</h5>
                    <p class="mb-0 text-muted small">
                        Impor data siswa membutuhkan nama kelas yang valid. Silakan <a href="{{ route('kelas.create') }}" class="fw-bold text-danger">tambahkan kelas terlebih dahulu</a> sebelum mengunggah.
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- 2 Bento Action Cards (Pemicu Modal Panduan & Template) --}}
    <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px;" class="mb-4">
        {{-- Kartu Pemicu Modal Panduan --}}
        <button type="button" class="action-card-btn" data-bs-toggle="modal" data-bs-target="#modalPanduanImpor">
            <div class="d-flex align-items-center gap-3">
                <div style="width: 44px; height: 44px; border-radius: 8px; background: rgba(59, 93, 50, 0.12); color: var(--primary, #3b5d32); display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0;">
                    <x-ikon nama="book" />
                </div>
                <div>
                    <h4 class="fw-bold text-dark mb-1" style="font-size: 15px;">Panduan &amp; Aturan Format</h4>
                    <p class="text-muted small mb-0">6 langkah persiapan data dan tata kelola akun tahunan</p>
                </div>
            </div>
            <span class="chip chip--neutral">Buka Panduan &rsaquo;</span>
        </button>

        {{-- Kartu Pemicu Modal Template & AI --}}
        <button type="button" class="action-card-btn" data-bs-toggle="modal" data-bs-target="#modalTemplateExcelAI">
            <div class="d-flex align-items-center gap-3">
                <div style="width: 44px; height: 44px; border-radius: 8px; background: rgba(22, 101, 52, 0.12); color: #166534; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0;">
                    <x-ikon nama="file-earmark-spreadsheet" />
                </div>
                <div>
                    <h4 class="fw-bold text-dark mb-1" style="font-size: 15px;">Template Excel &amp; Bantuan AI</h4>
                    <p class="text-muted small mb-0">Unduh template resmi XLSX dan salin prompt format AI</p>
                </div>
            </div>
            <span class="chip chip--solid-lembut" style="color: #166534;">Unduh Template &rsaquo;</span>
        </button>
    </div>

    {{-- Kartu Utama: Unggah Berkas Terisi & Custom Dropzone --}}
    <x-card title="Unggah Berkas Excel" class="border-l-4 mb-4" style="border-left-color: var(--primary, #3b5d32);" id="unggah">
        <form method="POST" action="{{ route('admin.impor.pratinjau') }}" enctype="multipart/form-data">
            @csrf

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="field">
                        <label class="field__label" for="jenisSelect">Kategori Data Pengguna<span class="field__req"> *</span></label>
                        <select class="select-hifi w-100" name="jenis" id="jenisSelect" required>
                            <option value="siswa" @selected(old('jenis', request('jenis')) === 'siswa')>Data Siswa</option>
                            <option value="guru" @selected(old('jenis', request('jenis')) === 'guru')>Data Guru</option>
                        </select>
                        <span class="field__hint">Pilih jenis akun yang sesuai dengan isi kolom pada berkas spreadsheet.</span>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="field">
                        <label class="field__label" for="password_bawaan">Password Bawaan Cadangan (Opsional)</label>
                        <input class="select-hifi w-100" type="password" name="password_bawaan" id="password_bawaan"
                               placeholder="Minimal 8 karakter..." minlength="8" autocomplete="new-password">
                        <span class="field__hint">Digunakan khusus jika kolom password pada berkas spreadsheet kosong.</span>
                    </div>
                </div>
            </div>

            {{-- Custom Interactive Dropzone --}}
            <div class="field mb-4">
                <label class="field__label mb-2">Berkas Spreadsheet Excel (.xlsx atau .csv)<span class="field__req"> *</span></label>
                <div class="file-upload-box" id="dropzoneImporBox" style="border: 2px dashed #cbd5e1; border-radius: 12px; padding: 32px 20px; text-align: center; cursor: pointer; background: #fafafa; transition: all 0.2s;" onclick="document.getElementById('berkasImporInput').click()">
                    <input type="file" name="berkas" id="berkasImporInput" accept=".xlsx,.csv" required style="display: none;" onchange="handleFileSelectImpor(this)">
                    <div style="font-size: 38px; color: var(--primary, #3b5d32); margin-bottom: 8px;">
                        <x-ikon nama="cloud-arrow-up" />
                    </div>
                    <div class="fw-bold text-dark mb-1" id="dropzoneImporTitle" style="font-size: 15px;">Klik untuk memilih berkas atau seret berkas ke sini</div>
                    <p class="text-muted small mb-0" id="dropzoneImporDesc">Mendukung format resmi .xlsx dan .csv (Maksimal 10 MB)</p>
                </div>
            </div>

            <div class="p-3 mb-4 rounded" style="background: rgba(0,0,0,0.03); font-size: 13px;">
                <label class="d-flex align-items-center gap-2 cursor-pointer mb-0">
                    <input type="checkbox" name="perbarui" value="1" @checked(old('perbarui')) style="width: 16px; height: 16px;">
                    <span class="fw-semibold text-dark">Perbarui data yang sudah ada di database</span>
                </label>
                <span class="text-muted small d-block mt-1 ps-4">
                    Jika dicentang, data siswa/guru yang sudah terdaftar akan diperbarui berdasarkan kecocokan email. Jika tidak dicentang, data duplikat akan ditolak.
                </span>
            </div>

            <div class="d-flex justify-content-end">
                <button class="btn-hifi" type="submit" style="padding: 10px 24px;">
                    <x-ikon nama="file-earmark-arrow-up" /> Periksa Berkas &amp; Tinjau Pratinjau
                </button>
            </div>
        </form>
    </x-card>
</div>

{{-- MODAL 1: Panduan 6 Langkah & Siklus Tahunan --}}
<div class="modal fade" id="modalPanduanImpor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content card-hifi p-4">
            <div class="d-flex justify-content-between align-items-center pb-3 border-bottom mb-3">
                <div class="d-flex align-items-center gap-2">
                    <div style="width: 32px; height: 32px; border-radius: 6px; background: rgba(59, 93, 50, 0.12); color: var(--primary, #3b5d32); display: flex; align-items: center; justify-content: center;">
                        <x-ikon nama="book" />
                    </div>
                    <h4 class="card-hifi__title mb-0" style="font-size: 17px !important;">Panduan Persiapan Data Excel</h4>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body p-0">
                <div class="panduan-grid mb-4">
                    <div class="panduan-item">
                        <div class="panduan-item__header">
                            <span class="panduan-item__no">1</span>
                            <span class="panduan-item__judul">Unduh Template Resmi</span>
                        </div>
                        <p class="panduan-item__isi">Gunakan berkas template Excel resmi. Jangan mengubah nama atau urutan kolom header baris pertama.</p>
                    </div>
                    <div class="panduan-item">
                        <div class="panduan-item__header">
                            <span class="panduan-item__no">2</span>
                            <span class="panduan-item__judul">Susun Baris Data</span>
                        </div>
                        <p class="panduan-item__isi">Hapus 2 baris contoh sebelum mengunggah. Setiap baris mewakili 1 akun pengguna.</p>
                    </div>
                    <div class="panduan-item">
                        <div class="panduan-item__header">
                            <span class="panduan-item__no">3</span>
                            <span class="panduan-item__judul">Verifikasi Identitas &amp; Rombel</span>
                        </div>
                        <p class="panduan-item__isi">Pastikan NIS/NIP bersifat unik, pertahankan angka 0 di depan, serta nama kelas persis seperti yang terdaftar.</p>
                    </div>
                    <div class="panduan-item">
                        <div class="panduan-item__header">
                            <span class="panduan-item__no">4</span>
                            <span class="panduan-item__judul">Unggah Berkas</span>
                        </div>
                        <p class="panduan-item__isi">Pilih berkas (.xlsx/.csv) dan tentukan password bawaan bila kolom password dikosongkan.</p>
                    </div>
                    <div class="panduan-item">
                        <div class="panduan-item__header">
                            <span class="panduan-item__no">5</span>
                            <span class="panduan-item__judul">Tinjau Pratinjau Sistem</span>
                        </div>
                        <p class="panduan-item__isi">Sistem akan memvalidasi tiap baris data tanpa menyentuh database terlebih dahulu.</p>
                    </div>
                    <div class="panduan-item">
                        <div class="panduan-item__header">
                            <span class="panduan-item__no">6</span>
                            <span class="panduan-item__judul">Konfirmasi Simpan</span>
                        </div>
                        <p class="panduan-item__isi">Penyimpanan dilakukan secara aman dan transaksional setelah semua kendala validasi selesai.</p>
                    </div>
                </div>

                <div class="p-3 rounded border" style="background: rgba(0,0,0,0.02);">
                    <h6 class="fw-bold mb-2 text-dark" style="font-size: 13px;">Catatan Siklus Administrasi Data Tahunan:</h6>
                    <ul class="small text-muted mb-0 ps-3" style="font-size: 12px; line-height: 1.6">
                        <li><strong>Calon Siswa Baru (Kelas X):</strong> Siapkan berkas dari hasil PPDB dan pastikan rombel kelas X tujuan telah dibuat di Data Kelas sebelum impor.</li>
                        <li><strong>Pemeriksaan Siswa Berjalan (Kelas XI &amp; XII):</strong> Tinjau status keaktifan dan kelengkapan penugasan rombel kelas berjalan.</li>
                        <li class="text-danger fw-semibold">Penting: Mengunggah data siswa Kelas XII tidak memproses kelulusan secara otomatis. Kelulusan siswa dan pencatatan alumni dikelola terpisah melalui modul Transisi Akademik.</li>
                    </ul>
                </div>
            </div>

            <div class="d-flex justify-content-end pt-3 border-top mt-3">
                <button type="button" class="btn-hifi btn-hifi--ghost" data-bs-dismiss="modal">Tutup Panduan</button>
            </div>
        </div>
    </div>
</div>

{{-- MODAL 2: Template Excel Resmi & Generator Prompt AI --}}
<div class="modal fade" id="modalTemplateExcelAI" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content card-hifi p-4">
            <div class="d-flex justify-content-between align-items-center pb-3 border-bottom mb-3">
                <div class="d-flex align-items-center gap-2">
                    <div style="width: 32px; height: 32px; border-radius: 6px; background: rgba(22, 101, 52, 0.12); color: #166534; display: flex; align-items: center; justify-content: center;">
                        <x-ikon nama="file-earmark-spreadsheet" />
                    </div>
                    <h4 class="card-hifi__title mb-0" style="font-size: 17px !important;">Unduh Template Resmi &amp; Panduan Prompt AI</h4>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body p-0">
                <div class="banner-privasi mb-3">
                    <strong>Peringatan Perlindungan Data Siswa:</strong> {{ $peringatanPrivasi }}
                </div>

                {{-- Tabs Pemilihan Template Siswa vs Guru --}}
                <ul class="nav nav-tabs mb-3" id="templateTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-bold" id="siswa-tmpl-tab" data-bs-toggle="tab" data-bs-target="#siswaTmplPane" type="button" role="tab">
                            <x-ikon nama="people" /> Template Data Siswa (7 Kolom)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold" id="guru-tmpl-tab" data-bs-toggle="tab" data-bs-target="#guruTmplPane" type="button" role="tab">
                            <x-ikon nama="person-badge" /> Template Data Guru (10 Kolom)
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="templateTabsContent">
                    {{-- Tab Template Siswa --}}
                    <div class="tab-pane fade show active" id="siswaTmplPane" role="tabpanel">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 p-3 rounded mb-3" style="background: rgba(59, 93, 50, 0.05); border: 1px solid rgba(59, 93, 50, 0.15);">
                            <div>
                                <h6 class="fw-bold mb-1 text-dark">Template Resmi Excel Siswa (.XLSX)</h6>
                                <p class="text-muted small mb-0">Format terstruktur berisi kolom NIS, Nama, Email, Jenis Kelamin, Kelas, No HP, dan Password.</p>
                            </div>
                            <a href="{{ route('admin.impor.template', ['jenis' => 'siswa']) }}" class="btn-hifi btn-hifi--sm">
                                <x-ikon nama="download" /> Unduh Template Siswa
                            </a>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-7">
                                <div class="tbl-wrap">
                                    <table class="tbl">
                                        <thead>
                                            <tr><th>Kolom</th><th>Status</th><th>Penjelasan</th></tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($kolomSiswa as $def)
                                                <tr>
                                                    <td class="is-strong font-monospace">{{ $def['judul'] }}</td>
                                                    <td>
                                                        <x-chip :tone="$def['wajib'] ? 'red' : 'neutral'" :label="$def['wajib'] ? 'Wajib' : 'Opsional'" />
                                                    </td>
                                                    <td class="is-muted small">{{ $def['petunjuk'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="prompt-box h-100 d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="fw-bold small text-dark"><x-ikon nama="robot" /> Prompt Generator AI Siswa</span>
                                        <button type="button" class="btn-hifi btn-hifi--ghost btn-hifi--sm" onclick="salinPrompt('promptSiswaText', 'btnSalinSiswa', 'statusSalinSiswa')" id="btnSalinSiswa">
                                            <x-ikon nama="clipboard" /> Salin Prompt
                                        </button>
                                    </div>
                                    <textarea id="promptSiswaText" class="prompt-textarea flex-grow-1" readonly>{{ $promptSiswa }}</textarea>
                                    <span id="statusSalinSiswa" class="text-success small fw-semibold mt-1" style="display: none;"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Tab Template Guru --}}
                    <div class="tab-pane fade" id="guruTmplPane" role="tabpanel">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 p-3 rounded mb-3" style="background: rgba(22, 101, 52, 0.05); border: 1px solid rgba(22, 101, 52, 0.15);">
                            <div>
                                <h6 class="fw-bold mb-1 text-dark">Template Resmi Excel Guru (.XLSX)</h6>
                                <p class="text-muted small mb-0">Format terstruktur berisi kolom NIP, Nama, Email, Jenis Kelamin, Mapel, dan Password.</p>
                            </div>
                            <a href="{{ route('admin.impor.template', ['jenis' => 'guru']) }}" class="btn-hifi btn-hifi--sm">
                                <x-ikon nama="download" /> Unduh Template Guru
                            </a>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-7">
                                <div class="tbl-wrap">
                                    <table class="tbl">
                                        <thead>
                                            <tr><th>Kolom</th><th>Status</th><th>Penjelasan</th></tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($kolomGuru as $def)
                                                <tr>
                                                    <td class="is-strong font-monospace">{{ $def['judul'] }}</td>
                                                    <td>
                                                        <x-chip :tone="$def['wajib'] ? 'red' : 'neutral'" :label="$def['wajib'] ? 'Wajib' : 'Opsional'" />
                                                    </td>
                                                    <td class="is-muted small">{{ $def['petunjuk'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="prompt-box h-100 d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="fw-bold small text-dark"><x-ikon nama="robot" /> Prompt Generator AI Guru</span>
                                        <button type="button" class="btn-hifi btn-hifi--ghost btn-hifi--sm" onclick="salinPrompt('promptGuruText', 'btnSalinGuru', 'statusSalinGuru')" id="btnSalinGuru">
                                            <x-ikon nama="clipboard" /> Salin Prompt
                                        </button>
                                    </div>
                                    <textarea id="promptGuruText" class="prompt-textarea flex-grow-1" readonly>{{ $promptGuru }}</textarea>
                                    <span id="statusSalinGuru" class="text-success small fw-semibold mt-1" style="display: none;"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end pt-3 border-top mt-3">
                <button type="button" class="btn-hifi btn-hifi--ghost" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function handleFileSelectImpor(input) {
    const box = document.getElementById('dropzoneImporBox');
    const title = document.getElementById('dropzoneImporTitle');
    const desc = document.getElementById('dropzoneImporDesc');

    if (input.files && input.files[0]) {
        const file = input.files[0];
        const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
        box.style.borderColor = 'var(--primary, #3b5d32)';
        box.style.background = 'rgba(59, 93, 50, 0.04)';
        title.innerHTML = '<span class="text-success">✓ Berkas Terpilih:</span> ' + file.name;
        desc.textContent = 'Ukuran berkas: ' + sizeMb + ' MB • Siap diperiksa sistem';
    } else {
        box.style.borderColor = '#cbd5e1';
        box.style.background = '#fafafa';
        title.textContent = 'Klik untuk memilih berkas atau seret berkas ke sini';
        desc.textContent = 'Mendukung format resmi .xlsx dan .csv (Maksimal 10 MB)';
    }
}

function salinPrompt(textareaId, btnId, statusId) {
    const textarea = document.getElementById(textareaId);
    const btn = document.getElementById(btnId);
    const status = document.getElementById(statusId);
    if (!textarea) return;

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(textarea.value).then(() => {
            tampilkanSukses(btn, status);
        }).catch(() => {
            salinFallback(textarea, btn, status);
        });
    } else {
        salinFallback(textarea, btn, status);
    }
}

function salinFallback(textarea, btn, status) {
    try {
        textarea.select();
        textarea.setSelectionRange(0, 99999);
        const sukses = document.execCommand('copy');
        if (sukses) {
            tampilkanSukses(btn, status);
        } else {
            tampilkanGagal(btn, status);
        }
    } catch (err) {
        tampilkanGagal(btn, status);
    }
}

function tampilkanSukses(btn, status) {
    if (status) {
        status.textContent = '✓ Prompt AI berhasil disalin!';
        status.className = 'text-success small fw-semibold';
        status.style.display = 'inline-block';
        setTimeout(() => { status.style.display = 'none'; }, 3500);
    }
    if (btn) {
        const textAwal = btn.innerHTML;
        btn.innerHTML = '<span style="color: #10b981">✓ Tersalin!</span>';
        setTimeout(() => { btn.innerHTML = textAwal; }, 2500);
    }
}

function tampilkanGagal(btn, status) {
    if (status) {
        status.textContent = 'Pilih dan salin teks manual dari kotak teks.';
        status.className = 'text-danger small fw-semibold';
        status.style.display = 'inline-block';
    }
}
</script>
@endpush
