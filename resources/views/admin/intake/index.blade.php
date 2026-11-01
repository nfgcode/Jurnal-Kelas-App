@extends('layouts.app')

@section('title', 'Penerimaan Siswa Baru Tingkat X')

@section('content')
<div class="intake-page space-y-4">
    {{-- Breadcrumb Navigasi --}}
    <nav class="breadcrumb-nav mb-2" aria-label="Breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.tahun-ajaran.index') }}">Tahun Ajaran</a></li>
            @if(!empty($coordinatorId))
                <li class="breadcrumb-item"><a href="{{ route('admin.transisi-terpadu.show', $coordinatorId) }}">Transisi Terpadu</a></li>
            @endif
            <li class="breadcrumb-item active" aria-current="page">Penerimaan Siswa Baru Tingkat X</li>
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
        title="Penerimaan Siswa Baru Tingkat X"
        description="Workflow pendaftaran dan penempatan siswa baru tingkat X ke rombel tujuan pada tahun ajaran yang dipilih secara atomik, aman, dan terisolasi."
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

    {{-- Filter Periode Target --}}
    <div class="card mb-4 shadow-sm border-0">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.intake.index') }}" class="row g-3 align-items-end">
                <div class="col-md-10">
                    <label class="form-label fw-bold">Tahun Ajaran Tujuan Penerimaan Siswa Baru</label>
                    <select name="tahun_tujuan" class="form-select" onchange="this.form.submit()">
                        @foreach ($tahunList as $t)
                            <option value="{{ $t->kode }}" {{ $t->kode === $tahunTujuan ? 'selected' : '' }}>
                                {{ $t->kode }} {{ $t->aktif ? '(Aktif Saat Ini)' : '(Mendatang / Nonaktif)' }}
                            </option>
                        @endforeach
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
        {{-- Kartu Kesiapan Rombel Tingkat X --}}
        <div class="col-md-5">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-header bg-transparent fw-bold d-flex justify-content-between align-items-center">
                    <span>Rombel Tingkat X Terdaftar ({{ $rombelXList->count() }})</span>
                    <span class="badge bg-primary">Tahun {{ $tahunTujuan }}</span>
                </div>
                <div class="card-body">
                    @if ($rombelXList->isEmpty())
                        <div class="alert alert-warning small mb-0">
                            <strong>Perhatian:</strong> Belum ada rombel kelas tingkat X yang terdaftar pada tahun ajaran <strong>{{ $tahunTujuan }}</strong>. Siapkan rombel kelas X terlebih dahulu melalui menu Kenaikan &amp; Kelulusan sebelum mengimpor siswa baru.
                        </div>
                    @else
                        <p class="text-muted small mb-3">
                            Setiap siswa baru wajib ditempatkan pada salah satu rombel kelas tingkat X yang sah berikut:
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach ($rombelXList as $rombel)
                                <span class="badge bg-light text-dark border p-2">
                                    <strong>{{ $rombel->nama_kelas }}</strong>
                                    <span class="text-muted">({{ $rombel->jurusan_kode }})</span>
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Kartu Unggah Berkas & Template --}}
        <div class="col-md-7">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-header bg-transparent fw-bold d-flex justify-content-between align-items-center">
                    <span>Mulai Pendaftaran Siswa Baru</span>
                    <a href="{{ route('admin.intake.template', ['tahun_tujuan' => $tahunTujuan]) }}" class="btn btn-sm btn-outline-primary">
                        <x-ikon nama="download" /> Unduh Template XLSX
                    </a>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.intake.upload') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="tahun_tujuan" value="{{ $tahunTujuan }}">
                        @if (request()->query('coordinator_id'))
                            <input type="hidden" name="coordinator_id" value="{{ request()->query('coordinator_id') }}">
                        @endif

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small">Nomor SK / Referensi Penerimaan <span class="text-danger">*</span></label>
                                <input type="text" name="official_reference" class="form-control" placeholder="contoh: PPDB-SMK-2027" value="{{ old('official_reference', 'PPDB-' . str_replace('/', '-', $tahunTujuan)) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small">Tanggal Efektif Masuk <span class="text-danger">*</span></label>
                                @php
                                    $targetObj = collect($tahunList)->firstWhere('kode', $tahunTujuan);
                                    $defaultDate = $targetObj && $targetObj->mulai ? $targetObj->mulai->toDateString() : today()->toDateString();
                                @endphp
                                <input type="date" name="effective_date" class="form-control" value="{{ old('effective_date', $defaultDate) }}" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Unggah Berkas Calon Siswa (XLSX / CSV) <span class="text-danger">*</span></label>
                            <input type="file" name="berkas" class="form-control" accept=".xlsx,.csv" required>
                            <div class="form-text small">
                                Berkas wajib memuat kolom: <code>NIS</code>, <code>NISN</code> (10 digit), <code>Nama Lengkap</code>, <code>Email</code>, dan <code>Kelas X Tujuan</code>.
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center pt-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#aiPromptModal">
                                <x-ikon nama="stars" /> Bantuan Format AI
                            </button>
                            <button type="submit" class="btn btn-primary px-4" {{ $rombelXList->isEmpty() ? 'disabled' : '' }}>
                                <x-ikon nama="upload" /> Unggah &amp; Lanjut ke Pratinjau
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Riwayat Sesi Alur Penerimaan --}}
    <div class="card shadow-sm border-0">
        <div class="card-header bg-transparent fw-bold">
            Riwayat Sesi Alur Penerimaan Siswa Baru
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-muted">
                        <tr>
                            <th>ID</th>
                            <th>Tahun Ajaran Sasaran</th>
                            <th>Status State</th>
                            <th>Validasi</th>
                            <th>Dibuat Oleh</th>
                            <th>Waktu</th>
                            <th class="text-end">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($operations as $op)
                            <tr>
                                <td class="fw-bold">#{{ $op->id }}</td>
                                <td><strong>{{ $op->target_year }}</strong></td>
                                <td>
                                    <span class="badge {{ $op->state === 'completed' ? 'bg-success' : ($op->state === 'ready' ? 'bg-primary' : 'bg-secondary') }}">
                                        {{ ucfirst($op->state) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $op->validation === 'valid' ? 'bg-success' : ($op->validation === 'unverified' ? 'bg-warning text-dark' : 'bg-danger') }}">
                                        {{ ucfirst($op->validation) }}
                                    </span>
                                </td>
                                <td>{{ $op->actor?->nama ?? '-' }}</td>
                                <td class="small text-muted">{{ $op->created_at->format('d/m/Y H:i') }}</td>
                                <td class="text-end">
                                    @if ($op->state === 'completed')
                                        <a href="{{ route('admin.intake.show', $op->id) }}" class="btn btn-sm btn-outline-success">
                                            <x-ikon nama="check2-all" /> Lihat Rekap
                                        </a>
                                    @else
                                        <a href="{{ route('admin.intake.review', $op->id) }}" class="btn btn-sm btn-outline-primary">
                                            <x-ikon nama="pencil" /> Tinjau Pratinjau
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    Belum ada sesi penerimaan siswa baru yang dimulai.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($operations->hasPages())
                <div class="p-3 border-top">
                    {{ $operations->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Modal Prompt AI --}}
<div class="modal fade" id="aiPromptModal" tabindex="-1" aria-labelledby="aiPromptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center gap-2" id="aiPromptModalLabel">
                    <x-ikon nama="stars" /> Bantuan Format AI (Prompt Generator)
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning small mb-3">
                    <strong>Peringatan Perlindungan Privasi:</strong> Jangan pernah mengunggah data identitas sensitif (seperti NIK, nomor telepon pribadi, alamat rumah, atau data medis) ke layanan AI publik. Gunakan hanya Nama dan NIS atau gunakan data anonim.
                </div>
                <p class="small text-muted mb-2">Salin instruksi prompt di bawah ini ke asisten AI Anda untuk memformat ulang data berkas calon peserta didik baru menjadi tabel TSV yang siap disalin ke lembar kerja:</p>
                <div class="position-relative">
                    <textarea class="form-control font-monospace small bg-light" rows="12" readonly id="aiPromptText">{{ $aiPrompt }}</textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('aiPromptText').value)">
                    Salin Teks Prompt
                </button>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endsection
