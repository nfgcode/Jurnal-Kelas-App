@extends('layouts.app')

@section('title', 'Kenaikan Kelas X–XI')

@section('content')
<div class="kenaikan-page space-y-4">
    {{-- Breadcrumb Navigasi --}}
    <nav class="breadcrumb-nav mb-2" aria-label="Breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.tahun-ajaran.index') }}">Tahun Ajaran</a></li>
            @if(!empty($coordinatorId))
                <li class="breadcrumb-item"><a href="{{ route('admin.transisi-terpadu.show', $coordinatorId) }}">Transisi Terpadu</a></li>
            @endif
            <li class="breadcrumb-item active" aria-current="page">Kenaikan Kelas X–XI</li>
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
        title="Kenaikan Kelas X–XI"
        description="Pengelolaan kenaikan kelas mandiri bagi siswa tingkat X dan XI. Siswa default naik ke tingkat berikutnya, dengan opsi pencatatan pengecualian tinggal kelas secara manual atau via spreadsheet resmi."
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
            <form method="GET" action="{{ route('admin.kenaikan.index') }}" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label fw-bold">Tahun Ajaran Sumber (Asal)</label>
                    <select name="tahun_asal" class="form-select" onchange="this.form.submit()">
                        @foreach ($tahunList as $t)
                            <option value="{{ $t->kode }}" {{ $t->kode === $tahunAsal ? 'selected' : '' }}>
                                {{ $t->kode }} {{ $t->aktif ? '(Aktif)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-bold">Tahun Ajaran Tujuan (Kenaikan Kelas)</label>
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
        {{-- Kartu Info Roster X & XI --}}
        <div class="col-md-4">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-header bg-transparent fw-bold">
                    Roster Siswa Tingkat X &amp; XI
                </div>
                <div class="card-body">
                    <div class="display-6 fw-bold text-primary mb-2">{{ number_format($totalSiswa, 0, ',', '.') }}</div>
                    <div class="d-flex justify-content-between text-muted small mb-3">
                        <span>Tingkat X: <strong>{{ $siswaCountX }}</strong></span>
                        <span>Tingkat XI: <strong>{{ $siswaCountXI }}</strong></span>
                    </div>
                    <p class="text-muted small mb-0">
                        Siswa aktif terdaftar pada tahun ajaran <strong>{{ $tahunAsal }}</strong>. Siswa tingkat XII tidak termasuk dalam alur kenaikan kelas ini.
                    </p>
                </div>
            </div>
        </div>

        {{-- Kartu Inisiasi & Upload --}}
        <div class="col-md-8">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-header bg-transparent fw-bold d-flex justify-content-between align-items-center">
                    <span>Mulai Kenaikan Kelas Baru</span>
                    <a href="{{ route('admin.kenaikan.template', ['tahun_asal' => $tahunAsal]) }}" class="btn btn-sm btn-outline-primary">
                        <x-ikon nama="download" /> Unduh Template XLSX
                    </a>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.kenaikan.inisiasi') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="tahun_asal" value="{{ $tahunAsal }}">
                        <input type="hidden" name="tahun_tujuan" value="{{ $tahunTujuan }}">
                        @if (request()->query('coordinator_id'))
                            <input type="hidden" name="coordinator_id" value="{{ request()->query('coordinator_id') }}">
                        @endif

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Pilih Metode Inisiasi <span class="text-danger">*</span></label>
                            <div class="d-flex gap-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="metode" id="metodeDefault" value="default" checked onchange="toggleMetodeUpload(false)">
                                    <label class="form-check-label" for="metodeDefault">
                                        Default Naik Kelas (Semua Siswa Naik Otomatis)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="metode" id="metodeUpload" value="upload" onchange="toggleMetodeUpload(true)">
                                    <label class="form-check-label" for="metodeUpload">
                                        Unggah Spreadsheet Pengecualian (XLSX / CSV)
                                    </label>
                                </div>
                            </div>
                            <div class="form-text small mt-1">
                                Pada metode default, semua siswa X dan XI otomatis dipetakan naik kelas ke rombel paralel yang sama, dan Anda dapat mengatur pengecualian siswa tinggal kelas secara manual pada halaman pratinjau.
                            </div>
                        </div>

                        <div id="sectionUploadBerkas" class="mb-3 d-none">
                            <label class="form-label fw-bold small">Unggah Berkas Spreadsheet (XLSX / CSV) <span class="text-danger">*</span></label>
                            <input type="file" name="berkas" id="inputBerkas" class="form-control" accept=".xlsx,.csv">
                            <div class="form-text small">
                                Berkas wajib memuat kolom <code>NIS</code> dan <code>Status Kenaikan</code> (NAIK / TINGGAL KELAS). Jika status TINGGAL KELAS, kolom <code>Alasan / Catatan</code> wajib diisi.
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center pt-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#aiPromptModal">
                                <x-ikon nama="stars" /> Bantuan Format AI
                            </button>
                            <button type="submit" class="btn btn-primary px-4" {{ $tahunTujuanList->isEmpty() ? 'disabled' : '' }}>
                                <x-ikon nama="arrow-right" /> Buat Alur &amp; Lanjut ke Pratinjau
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Riwayat Alur Kenaikan Kelas --}}
    <div class="card shadow-sm border-0">
        <div class="card-header bg-transparent fw-bold d-flex justify-content-between align-items-center">
            <span>Riwayat Sesi Alur Kenaikan Kelas</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-muted">
                        <tr>
                            <th>ID</th>
                            <th>Periode Asal &rarr; Tujuan</th>
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
                                <td>
                                    <strong>{{ $op->source_year }}</strong>
                                    <x-ikon nama="arrow-right" />
                                    <strong>{{ $op->target_year }}</strong>
                                </td>
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
                                        <a href="{{ route('admin.kenaikan.show', $op->id) }}" class="btn btn-sm btn-outline-success">
                                            <x-ikon nama="check2-all" /> Lihat Rekap
                                        </a>
                                    @else
                                        <a href="{{ route('admin.kenaikan.review', $op->id) }}" class="btn btn-sm btn-outline-primary">
                                            <x-ikon nama="pencil" /> Tinjau Pratinjau
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    Belum ada sesi kenaikan kelas yang dimulai.
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
                <p class="small text-muted mb-2">Salin instruksi prompt di bawah ini ke asisten AI Anda untuk memformat ulang data rapat dewan guru menjadi tabel TSV yang siap disalin ke lembar kerja:</p>
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

<script>
function toggleMetodeUpload(isUpload) {
    var section = document.getElementById('sectionUploadBerkas');
    var input = document.getElementById('inputBerkas');
    if (isUpload) {
        section.classList.remove('d-none');
        input.setAttribute('required', 'required');
    } else {
        section.classList.add('d-none');
        input.removeAttribute('required');
    }
}
</script>
@endsection
