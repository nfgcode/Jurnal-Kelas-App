@extends('layouts.app')

@section('title', 'Transisi Akademik Terpadu')

@section('content')
<div class="transisi-terpadu-page space-y-4">
    {{-- Breadcrumb Navigasi --}}
    <nav class="breadcrumb-nav mb-2" aria-label="Breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.tahun-ajaran.index') }}">Tahun Ajaran</a></li>
            <li class="breadcrumb-item active" aria-current="page">Transisi Akademik Terpadu</li>
        </ol>
    </nav>

    <x-page-head
        title="Transisi Akademik Terpadu"
        description="Pusat orkestrasi transisi tahunan sekolah. Mengkoordinasikan alur Kelulusan Kelas XII, Kenaikan Kelas X–XI, dan Penerimaan Siswa Baru secara terpadu, bertahap, dan aman dengan urutan kanonikal tetap."
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

    {{-- Setup Sesi Koordinasi Baru --}}
    <div class="card mb-4 shadow-sm border-0">
        <div class="card-header bg-transparent fw-bold d-flex justify-content-between align-items-center">
            <span>Setup Sesi Transisi Terpadu Baru</span>
            <span class="badge bg-primary">Urutan Kanonikal Tetap</span>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.transisi-terpadu.store') }}" class="row g-3">
                @csrf
                <div class="col-md-6">
                    <label class="form-label fw-bold">Tahun Ajaran Sumber (Asal) <span class="text-danger">*</span></label>
                    <select name="tahun_asal" class="form-select" required>
                        @foreach ($tahunList as $t)
                            <option value="{{ $t->kode }}" {{ $t->kode === $tahunAsal ? 'selected' : '' }}>
                                {{ $t->kode }} {{ $t->aktif ? '(Aktif Saat Ini)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Tahun Ajaran Sasaran (Tujuan) <span class="text-danger">*</span></label>
                    <select name="tahun_tujuan" class="form-select" required>
                        @if ($tahunTujuanList->isEmpty())
                            <option value="" disabled selected>Belum ada tahun tujuan nonaktif mendatang</option>
                        @else
                            @foreach ($tahunTujuanList as $t)
                                <option value="{{ $t->kode }}" {{ $t->kode === $tahunTujuan ? 'selected' : '' }}>
                                    {{ $t->kode }} (Mendatang / Nonaktif)
                                </option>
                            @endforeach
                        @endif
                    </select>
                    <div class="form-text small">Tahun tujuan wajib nonaktif dan tanggal mulainya harus setelah selesai tahun sumber.</div>
                </div>

                <div class="col-12 mt-3">
                    <label class="form-label fw-bold">Pilih Tahapan Koordinasi (Minimal 2 Tahap) <span class="text-danger">*</span></label>
                    <div class="card bg-light border-0 p-3">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="stages[]" value="graduation" id="stageGrad" checked>
                                    <label class="form-check-label fw-bold" for="stageGrad">
                                        Tahap 1: Kelulusan XII
                                    </label>
                                    <div class="text-muted small">Pencatatan alumni &amp; tinggal kelas XII (Periode Mei).</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="stages[]" value="promotion" id="stageProm" checked>
                                    <label class="form-check-label fw-bold" for="stageProm">
                                        Tahap 2: Kenaikan X–XI
                                    </label>
                                    <div class="text-muted small">Promosi tingkat &amp; tinggal kelas X/XI (Periode Juni).</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="stages[]" value="intake" id="stageIntake" checked>
                                    <label class="form-check-label fw-bold" for="stageIntake">
                                        Tahap 3: Penerimaan Siswa
                                    </label>
                                    <div class="text-muted small">Registrasi siswa baru tingkat X &amp; akun login (Periode Juli).</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 d-flex justify-content-end mt-4">
                    <button type="submit" class="btn btn-primary px-4 fw-bold" {{ $tahunTujuanList->isEmpty() ? 'disabled' : '' }}>
                        <x-ikon nama="diagram-3" /> Inisiasi Sesi Transisi Terpadu
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Riwayat Sesi Koordinator --}}
    <div class="card shadow-sm border-0">
        <div class="card-header bg-transparent fw-bold d-flex justify-content-between align-items-center">
            <span>Daftar Sesi Transisi Terpadu</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-muted">
                        <tr>
                            <th>ID Sesi</th>
                            <th>Periode Asal &rarr; Tujuan</th>
                            <th>Pembuat Sesi</th>
                            <th>Waktu Pembuatan</th>
                            <th class="text-end">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sessions as $ses)
                            <tr>
                                <td class="fw-bold">#{{ $ses->id }}</td>
                                <td>
                                    <strong>{{ $ses->source_year ?? '-' }}</strong>
                                    <x-ikon nama="arrow-right" />
                                    <strong>{{ $ses->target_year ?? '-' }}</strong>
                                </td>
                                <td>{{ $ses->actor_name ?? ('User #' . $ses->actor_id) }}</td>
                                <td class="small text-muted">{{ \Illuminate\Support\Carbon::parse($ses->created_at)->format('d/m/Y H:i') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.transisi-terpadu.show', $ses->id) }}" class="btn btn-sm btn-outline-primary">
                                        <x-ikon nama="arrow-right" /> Buka Dashboard Sesi
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    Belum ada sesi transisi terpadu yang dibuat.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($sessions->hasPages())
                <div class="p-3 border-top">
                    {{ $sessions->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
