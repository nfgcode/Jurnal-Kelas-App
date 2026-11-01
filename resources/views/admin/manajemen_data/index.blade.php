@extends('layouts.app')

@section('title', 'Manajemen Data')

@section('content')
<div class="manajemen-data-page">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><x-ikon nama="folder" /> Hub Manajemen Data</h4>
            <div class="text-muted small">
                Pusat impor spreadsheet multi-mode, ekspor data administratif, dan manajemen arsip cadangan sistem.
            </div>
        </div>
        <div>
            <a href="{{ route('admin.manajemen-data.panduan') }}" class="btn btn-outline-primary btn-sm">
                <x-ikon nama="question-circle" /> Panduan & Format Template
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success d-flex align-items-center gap-2 mb-4" role="alert">
            <x-ikon nama="check-circle" />
            <div>{{ session('status') }}</div>
        </div>
    @endif

    {{-- Banner Status Kesehatan Cadangan --}}
    <div class="card shadow-sm border-0 mb-4 {{ $kesehatan['is_overdue'] ? 'bg-danger-subtle border-start border-danger border-4' : 'bg-light' }}">
        <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <x-ikon nama="{{ $kesehatan['is_overdue'] ? 'exclamation-triangle' : 'shield-check' }}" class="fs-2 {{ $kesehatan['is_overdue'] ? 'text-danger' : 'text-success' }}" />
                    <div>
                        <div class="fw-bold fs-6">
                            Status Kesehatan Cadangan Sistem:
                            <span class="badge {{ $kesehatan['is_overdue'] ? 'bg-danger' : 'bg-success' }}">
                                {{ $kesehatan['is_overdue'] ? 'Perlu Perhatian (Overdue >35 Hari)' : 'Sehat & Terjadwal' }}
                            </span>
                        </div>
                        <div class="small text-muted">
                            Cadangan Terakhir: <strong>{{ $kesehatan['last_backup_at'] ? \Illuminate\Support\Carbon::parse($kesehatan['last_backup_at'])->format('d M Y H:i') : 'Belum pernah dibuat' }}</strong>
                            &bull; Jadwal Otomatis Berikutnya: <strong>{{ \Illuminate\Support\Carbon::parse($kesehatan['next_scheduled_at'])->format('d M Y H:i') }}</strong>
                            &bull; Total Arsip: <strong>{{ $kesehatan['total_arsip'] }} berkas</strong> ({{ round($kesehatan['total_bytes'] / 1048576, 2) }} MB)
                        </div>
                        <div class="text-secondary small mt-1" style="font-size: 0.8rem;">
                            <em>Catatan: Jadwal otomatis terdaftar di scheduler aplikasi, namun keberjalanan aktual bergantung pada eksekusi berkala cron host (<code>schedule:run</code>).</em>
                        </div>
                    </div>
                </div>
                <div>
                    <a href="{{ route('admin.manajemen-data.cadangan.index') }}" class="btn btn-sm btn-primary">
                        Buka Manajemen Cadangan
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Dua Kartu Utama: Impor Data & Ekspor/Cadangkan Data --}}
    <div class="row g-4">
        {{-- Kartu 1: Impor Data --}}
        <div class="col-md-6">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="fw-bold mb-0"><x-ikon nama="upload" /> Impor Data Spreadsheet</h5>
                </div>
                <div class="card-body d-flex flex-column p-4">
                    <p class="text-muted small mb-3">
                        Unggah dan sinkronkan data siswa atau guru dari berkas XLSX/CSV dengan tiga mode impor yang fleksibel dan aman:
                    </p>
                    <ul class="small text-muted ps-3 mb-4">
                        <li><strong>Tambah Baru:</strong> Hanya mendaftarkan entitas baru tanpa menimpa data existing.</li>
                        <li><strong>Perbarui Data:</strong> Memperbarui record database yang cocok dengan NIP/NIS.</li>
                        <li><strong>Ganti Keseluruhan (Safe Sync):</strong> Menyelaraskan daftar aktif dan menonaktifkan record yang hilang tanpa menghapus riwayat historis.</li>
                    </ul>
                    <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                        <span class="badge bg-light text-dark border">Format: XLSX / CSV</span>
                        <a href="{{ route('admin.manajemen-data.impor.index') }}" class="btn btn-primary fw-bold">
                            Mulai Wizard Impor &rarr;
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Kartu 2: Ekspor & Cadangkan Data --}}
        <div class="col-md-6">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-header bg-dark text-white py-3">
                    <h5 class="fw-bold mb-0"><x-ikon nama="download" /> Ekspor & Cadangkan Data</h5>
                </div>
                <div class="card-body d-flex flex-column p-4">
                    <p class="text-muted small mb-3">
                        Pemisahan tegas antara ekspor data untuk analisis administratif dan cadangan database penuh:
                    </p>
                    <ul class="small text-muted ps-3 mb-4">
                        <li><strong>Ekspor Administratif (CSV/XLSX):</strong> Unduhan data per tabel tanpa memuat password hash, token, atau kredensial sensitif.</li>
                        <li><strong>Cadangan Database (.SQL.GZ / .SQL):</strong> Snapshot konsisten InnoDB terkompresi dengan manifest dan checksum SHA-256.</li>
                        <li><strong>Arsip Berkas Penting (ZIP):</strong> Pencadangan aset berkas publik sekolah dengan filter allowlist ketat.</li>
                    </ul>
                    <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                        <span class="badge bg-light text-dark border">{{ $totalTabel }} Tabel Database Terlindungi</span>
                        <a href="{{ route('admin.manajemen-data.cadangan.index') }}" class="btn btn-dark fw-bold">
                            Kelola Cadangan & Ekspor &rarr;
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
