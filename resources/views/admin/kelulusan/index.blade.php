@extends('layouts.app')

@section('title', 'Kelulusan Kelas XII')

@section('content')
<div class="kelulusan-page space-y-4">
    {{-- Breadcrumb Navigasi --}}
    <nav class="breadcrumb-nav mb-2" aria-label="Breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.tahun-ajaran.index') }}">Tahun Ajaran</a></li>
            @if(!empty($coordinatorId))
                <li class="breadcrumb-item"><a href="{{ route('admin.transisi-terpadu.show', $coordinatorId) }}">Transisi Terpadu</a></li>
            @endif
            <li class="breadcrumb-item active" aria-current="page">Kelulusan Kelas XII</li>
        </ol>
    </nav>

    @if(!empty($coordinatorId))
        <div class="card-hifi p-3 border-l-4 border-primary bg-primary-soft mb-3 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <span class="chip chip--solid-lembut">Sesi Terpadu</span>
                <span class="small text-primary fw-semibold">Terhubung dengan Koordinator #{{ $coordinatorId }}</span>
            </div>
            <a href="{{ route('admin.transisi-terpadu.show', $coordinatorId) }}" class="btn-hifi btn-hifi--ghost btn-hifi--sm">
                &larr; Kembali ke Transisi Terpadu
            </a>
        </div>
    @endif

    <x-page-head
        title="Kelulusan Kelas XII"
        description="Pengelolaan kelulusan siswa tingkat akhir berdasarkan hasil kelulusan resmi sekolah. Proses ini berdiri mandiri dan tidak otomatis menjalankan kenaikan kelas atau aktivasi periode baru."
    />

    @if (session('status'))
        <div class="alert alert-success d-flex align-items-center gap-2 mb-4" role="alert">
            <x-ikon nama="check-circle" />
            <div>{{ session('status') }}</div>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger mb-4" role="alert">
            <div class="fw-bold mb-1">Terjadi kesalahan:</div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Filter Periode & Ringkasan --}}
    <div class="card mb-4 shadow-sm border-0">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.kelulusan.index') }}" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label fw-bold">Tahun Ajaran Kelulusan (Sumber)</label>
                    <select name="tahun_asal" class="form-select" onchange="this.form.submit()">
                        @foreach ($tahunList as $t)
                            <option value="{{ $t->kode }}" {{ $t->kode === $tahunAsal ? 'selected' : '' }}>
                                {{ $t->kode }} {{ $t->aktif ? '(Aktif)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-bold">Tahun Ajaran Penempatan Ulang (Tujuan untuk Siswa Tidak Lulus)</label>
                    <select name="tahun_tujuan" class="form-select" onchange="this.form.submit()">
                        @if ($tahunTujuanList->isEmpty())
                            <option value="" disabled selected>Belum ada tahun tujuan nonaktif</option>
                        @else
                            @foreach ($tahunTujuanList as $t)
                                <option value="{{ $t->kode }}" {{ $t->kode === $tahunTujuan ? 'selected' : '' }}>
                                    {{ $t->kode }}
                                </option>
                            @endforeach
                        @endif
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-secondary w-100">
                        <x-ikon nama="filter" /> Saring
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-4 mb-4">
        {{-- Kartu Info Roster XII --}}
        <div class="col-md-4">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-header bg-transparent fw-bold">
                    Roster Siswa Kelas XII
                </div>
                <div class="card-body">
                    <div class="display-6 fw-bold text-primary mb-2">{{ number_format($siswaCount, 0, ',', '.') }}</div>
                    <p class="text-muted small mb-0">
                        Siswa aktif terdaftar di tingkat XII pada tahun ajaran <strong>{{ $tahunAsal }}</strong>. Seluruh siswa ini wajib diverifikasi keputusannya pada berkas resmi kelulusan.
                    </p>
                </div>
            </div>
        </div>

        {{-- Kartu Upload & Template --}}
        <div class="col-md-8">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-header bg-transparent fw-bold d-flex justify-content-between align-items-center">
                    <span>Mulai Verifikasi Kelulusan Baru</span>
                    <a href="{{ route('admin.kelulusan.template', ['tahun_asal' => $tahunAsal]) }}" class="btn btn-sm btn-outline-primary">
                        <x-ikon nama="download" /> Unduh Template XLSX
                    </a>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.kelulusan.upload') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="tahun_asal" value="{{ $tahunAsal }}">
                        <input type="hidden" name="tahun_tujuan" value="{{ $tahunTujuan }}">
                        @if (request()->query('coordinator_id'))
                            <input type="hidden" name="coordinator_id" value="{{ request()->query('coordinator_id') }}">
                        @endif

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small">Nomor SK / Referensi Resmi <span class="text-danger">*</span></label>
                                <input type="text" name="official_reference" class="form-control" placeholder="contoh: 421/045/SMK/2027" value="{{ old('official_reference') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small">Tanggal Efektif Kelulusan <span class="text-danger">*</span></label>
                                <input type="date" name="effective_date" class="form-control" value="{{ old('effective_date', today()->toDateString()) }}" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Unggah Berkas Spreadsheet Resmi (XLSX / CSV) <span class="text-danger">*</span></label>
                            <input type="file" name="berkas" class="form-control" accept=".xlsx,.csv" required>
                            <div class="form-text small">
                                Berkas wajib memuat kolom <code>NIS</code> dan <code>Status Kelulusan</code> (LULUS / TIDAK LULUS / BELUM TERVERIFIKASI). Kolom <code>Nama Siswa</code> digunakan sebagai pembanding.
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center pt-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#aiPromptModal">
                                <x-ikon nama="stars" /> Bantuan Format AI
                            </button>
                            <button type="submit" class="btn btn-primary px-4">
                                <x-ikon nama="upload" /> Unggah & Lanjutkan ke Pratinjau
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Riwayat Operasi Kelulusan --}}
    <div class="card shadow-sm border-0">
        <div class="card-header bg-transparent fw-bold">
            Riwayat & Draf Operasi Kelulusan
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Tahun Asal</th>
                            <th>Tahun Tujuan</th>
                            <th>Referensi Dokumen</th>
                            <th>Status Alur</th>
                            <th>Pelaksana</th>
                            <th>Waktu Dibuat</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($operations as $op)
                            <tr>
                                <td>#{{ $op->id }}</td>
                                <td><span class="badge bg-secondary">{{ $op->source_year }}</span></td>
                                <td>
                                    @if ($op->target_year)
                                        <span class="badge bg-light text-dark border">{{ $op->target_year }}</span>
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ $op->source_metadata['official_reference'] ?? '-' }}</strong>
                                    <div class="text-muted small">Tgl Efektif: {{ $op->source_metadata['effective_date'] ?? '-' }}</div>
                                </td>
                                <td>
                                    @if ($op->state === 'completed')
                                        <span class="badge bg-success">Selesai</span>
                                    @elseif ($op->state === 'ready')
                                        <span class="badge bg-primary">Siap Finalisasi</span>
                                    @elseif ($op->state === 'needs_attention')
                                        <span class="badge bg-warning text-dark">Perlu Tindak Lanjut</span>
                                    @elseif ($op->state === 'failed')
                                        <span class="badge bg-danger">Gagal</span>
                                    @else
                                        <span class="badge bg-secondary">{{ ucfirst($op->state) }}</span>
                                    @endif
                                </td>
                                <td>{{ $op->actor->nama ?? $op->actor->username ?? 'Admin' }}</td>
                                <td>{{ $op->created_at->format('d M Y H:i') }}</td>
                                <td class="text-end">
                                    @if ($op->state === 'completed')
                                        <a href="{{ route('admin.kelulusan.show', $op->id) }}" class="btn btn-sm btn-outline-success">
                                            <x-ikon nama="eye" /> Lihat Hasil
                                        </a>
                                    @else
                                        <a href="{{ route('admin.kelulusan.review', $op->id) }}" class="btn btn-sm btn-outline-primary">
                                            <x-ikon nama="arrow-right" /> Buka Draf
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    Belum ada catatan operasi kelulusan. Silakan unggah berkas resmi di atas.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($operations->hasPages())
                <div class="card-footer bg-transparent">
                    {{ $operations->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Modal Prompt AI --}}
<div class="modal fade" id="aiPromptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Prompt AI Pembantu Format Kelulusan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning small mb-3">
                    <strong>Peringatan Perlindungan Data:</strong> Jangan mengirimkan data pribadi siswa yang memuat informasi sensitif (seperti NIK, nomor telepon, alamat, atau data medis) ke layanan kecerdasan buatan pihak ketiga tanpa izin resmi.
                </div>
                <p class="small text-muted mb-2">Salin prompt di bawah ini ke asisten AI (seperti ChatGPT, Claude, atau Gemini) bersama data mentah sekolah Anda untuk menghasilkan tabel TSV yang rapi:</p>
                <textarea id="aiPromptText" class="form-control font-monospace small" rows="12" readonly>{{ $aiPrompt }}</textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-primary" onclick="navigator.clipboard.writeText(document.getElementById('aiPromptText').value); alert('Prompt berhasil disalin ke clipboard!');">
                    <x-ikon nama="clipboard" /> Salin Prompt
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
