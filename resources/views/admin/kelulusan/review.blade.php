@extends('layouts.app')

@section('title', 'Pratinjau Kelulusan Kelas XII')

@section('content')
<div class="kelulusan-review-page">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-1">Pratinjau Kelulusan Kelas XII</h4>
            <div class="text-muted small">
                Operasi #{{ $op->id }} &bull; Tahun Sumber: <strong>{{ $op->source_year }}</strong>
                @if ($op->target_year)
                    &bull; Tahun Tujuan Penempatan: <strong>{{ $op->target_year }}</strong>
                @endif
                &bull; Referensi: <strong>{{ $op->source_metadata['official_reference'] ?? '-' }}</strong>
                &bull; Tgl Efektif: <strong>{{ $op->source_metadata['effective_date'] ?? '-' }}</strong>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.kelulusan.index') }}" class="btn btn-outline-secondary btn-sm">
                <x-ikon nama="arrow-left" /> Kembali
            </a>
            <form method="POST" action="{{ route('admin.kelulusan.validate', $op->id) }}">
                @csrf
                <button type="submit" class="btn btn-outline-primary btn-sm">
                    <x-ikon nama="arrow-repeat" /> Validasi Ulang
                </button>
            </form>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success d-flex align-items-center gap-2 mb-3" role="alert">
            <x-ikon nama="check-circle" />
            <div>{{ session('status') }}</div>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger mb-3" role="alert">
            <div class="fw-bold mb-1">Perhatian:</div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Kartu Metrik Ringkasan --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="card shadow-sm border-0">
                <div class="card-body p-3">
                    <div class="text-muted small fw-bold text-uppercase">Total Roster XII</div>
                    <div class="fs-4 fw-bold text-dark">{{ number_format($summary['total'], 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card shadow-sm border-0 border-start border-success border-4">
                <div class="card-body p-3">
                    <div class="text-success small fw-bold text-uppercase">Siswa Lulus</div>
                    <div class="fs-4 fw-bold text-success">{{ number_format($summary['lulus'], 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card shadow-sm border-0 border-start border-warning border-4">
                <div class="card-body p-3">
                    <div class="text-warning small fw-bold text-uppercase">Tidak Lulus</div>
                    <div class="fs-4 fw-bold text-warning">{{ number_format($summary['tidak_lulus'], 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card shadow-sm border-0 border-start {{ $summary['masalah'] > 0 ? 'border-danger' : 'border-secondary' }} border-4">
                <div class="card-body p-3">
                    <div class="{{ $summary['masalah'] > 0 ? 'text-danger' : 'text-muted' }} small fw-bold text-uppercase">Belum Verifikasi / Masalah</div>
                    <div class="fs-4 fw-bold {{ $summary['masalah'] > 0 ? 'text-danger' : 'text-muted' }}">{{ number_format($summary['masalah'], 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Banner Status Kesiapan Gating --}}
    @if ($summary['masalah'] > 0 || $preview->validation !== 'valid')
        <div class="alert alert-warning mb-4 shadow-sm" role="alert">
            <div class="d-flex gap-2">
                <x-ikon nama="exclamation-triangle" class="fs-5 flex-shrink-0" />
                <div>
                    <h6 class="alert-heading fw-bold mb-1">Finalisasi Ditahan (Ada {{ $summary['masalah'] }} Siswa Memerlukan Tindak Lanjut)</h6>
                    <p class="small mb-0">
                        Finalisasi kelulusan hanya dapat diproses apabila seluruh siswa kelas XII di tahun ajaran ini telah terdaftar dengan keputusan terverifikasi dan siswa yang tidak lulus telah ditentukan kelas tujuannya. Silakan periksa daftar kelompok <strong>Masalah & Belum Terverifikasi</strong> di bawah ini.
                    </p>
                </div>
            </div>
        </div>
    @else
        <div class="alert alert-success mb-4 shadow-sm d-flex justify-content-between align-items-center" role="alert">
            <div class="d-flex gap-2">
                <x-ikon nama="check-circle" class="fs-5 flex-shrink-0" />
                <div>
                    <h6 class="alert-heading fw-bold mb-1">Seluruh Roster Terverifikasi Lengkap</h6>
                    <p class="small mb-0">Semua keputusan telah valid. Anda dapat melakukan finalisasi kelulusan sekarang.</p>
                </div>
            </div>
            <button type="button" class="btn btn-success fw-bold px-4" data-bs-toggle="modal" data-bs-target="#confirmExecutionModal">
                <x-ikon nama="check2-all" /> Finalisasi Kelulusan
            </button>
        </div>
    @endif

    {{-- Filter & Pencarian --}}
    <div class="card mb-3 shadow-sm border-0">
        <div class="card-body p-2">
            <form method="GET" action="{{ route('admin.kelulusan.review', $op->id) }}" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-transparent"><x-ikon nama="search" /></span>
                        <input type="text" name="q" class="form-control" placeholder="Cari NIS atau Nama Siswa..." value="{{ $q }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="semua" {{ $filterStatus === 'semua' ? 'selected' : '' }}>Semua Status ({{ $summary['total'] }})</option>
                        <option value="masalah" {{ $filterStatus === 'masalah' ? 'selected' : '' }}>Masalah & Belum Terverifikasi ({{ $summary['masalah'] }})</option>
                        <option value="tidak_lulus" {{ $filterStatus === 'tidak_lulus' ? 'selected' : '' }}>Tidak Lulus ({{ $summary['tidak_lulus'] }})</option>
                        <option value="lulus" {{ $filterStatus === 'lulus' ? 'selected' : '' }}>Lulus ({{ $summary['lulus'] }})</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-outline-secondary w-100">Cari</button>
                </div>
            </form>
        </div>
    </div>

    {{-- TABEL PENGELOMPOKAN --}}

    {{-- 1. Kelompok Masalah & Belum Terverifikasi --}}
    @if (!empty($grouped['masalah']))
        <div class="card mb-4 shadow-sm border-danger border-1">
            <div class="card-header bg-danger bg-opacity-10 text-danger fw-bold d-flex justify-content-between align-items-center">
                <span>
                    <x-ikon nama="exclamation-octagon" /> 1. Kesalahan & Belum Terverifikasi ({{ count($grouped['masalah']) }})
                </span>
                <span class="badge bg-danger">Wajib Diselesaikan</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>NIS</th>
                                <th>Nama Siswa</th>
                                <th>Keputusan</th>
                                <th>Detail Masalah / Catatan</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($grouped['masalah'] as $item)
                                <tr>
                                    <td><code>{{ $item['student_ref'] }}</code></td>
                                    <td><strong>{{ $item['nama'] }}</strong></td>
                                    <td>
                                        <span class="badge bg-secondary">{{ ucfirst($item['decision']) }}</span>
                                    </td>
                                    <td>
                                        @foreach ($item['errors'] as $err)
                                            <span class="badge bg-danger bg-opacity-75">{{ $err }}</span>
                                        @endforeach
                                        @if ($item['validation'] === 'unverified' && empty($item['errors']))
                                            <span class="badge bg-warning text-dark">Belum Terverifikasi</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editItemModal{{ $item['id'] }}">
                                            <x-ikon nama="pencil" /> Perbaiki
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- 2. Kelompok Siswa Tidak Lulus --}}
    @if (!empty($grouped['tidak_lulus']))
        <div class="card mb-4 shadow-sm border-warning border-1">
            <div class="card-header bg-warning bg-opacity-10 text-warning text-dark fw-bold d-flex justify-content-between align-items-center">
                <span>
                    <x-ikon nama="dash-circle" /> 2. Siswa Tidak Lulus ({{ count($grouped['tidak_lulus']) }})
                </span>
                <span class="badge bg-warning text-dark">Penempatan Tingkat XII</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>NIS</th>
                                <th>Nama Siswa</th>
                                <th>Keputusan</th>
                                <th>Kelas Tujuan (Tingkat XII)</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($grouped['tidak_lulus'] as $item)
                                <tr>
                                    <td><code>{{ $item['student_ref'] }}</code></td>
                                    <td><strong>{{ $item['nama'] }}</strong></td>
                                    <td><span class="badge bg-warning text-dark">Tidak Lulus</span></td>
                                    <td>
                                        @php
                                            $cObj = collect($targetClasses)->firstWhere('id', $item['target_class_id']);
                                        @endphp
                                        @if ($cObj)
                                            <span class="badge bg-info text-dark">{{ $cObj->nama_kelas }} ({{ $cObj->tingkat }})</span>
                                        @else
                                            <span class="text-danger small fw-bold">Belum ditentukan</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editItemModal{{ $item['id'] }}">
                                            <x-ikon nama="pencil" /> Ubah Kelas / Keputusan
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- 3. Kelompok Siswa Lulus --}}
    @if (!empty($grouped['lulus']))
        <div class="card mb-4 shadow-sm border-success border-1">
            <div class="card-header bg-success bg-opacity-10 text-success fw-bold d-flex justify-content-between align-items-center">
                <span>
                    <x-ikon nama="check-circle" /> 3. Siswa Lulus ({{ count($grouped['lulus']) }})
                </span>
                <span class="badge bg-success">Menjadi Alumni</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>NIS</th>
                                <th>Nama Siswa</th>
                                <th>Keputusan</th>
                                <th>Keterangan</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($grouped['lulus'] as $item)
                                <tr>
                                    <td><code>{{ $item['student_ref'] }}</code></td>
                                    <td><strong>{{ $item['nama'] }}</strong></td>
                                    <td><span class="badge bg-success">Lulus</span></td>
                                    <td><span class="text-muted small">Menjadi Alumni &bull; SK: {{ $op->source_metadata['official_reference'] ?? '-' }}</span></td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editItemModal{{ $item['id'] }}">
                                            <x-ikon nama="pencil" /> Ubah
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>

{{-- Modals untuk Edit Item --}}
@foreach ($items as $item)
    <div class="modal fade" id="editItemModal{{ $item['id'] }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.kelulusan.update-item', [$op->id, $item['id']]) }}">
                    @csrf
                    <div class="modal-header">
                        <h6 class="modal-title fw-bold">Tinjau Keputusan Siswa: {{ $item['nama'] }} ({{ $item['student_ref'] }})</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Keputusan Kelulusan</label>
                            <select name="decision" class="form-select" id="decisionSelect{{ $item['id'] }}" onchange="document.getElementById('targetClassDiv{{ $item['id'] }}').style.display = (this.value === 'tidak_lulus' ? 'block' : 'none');">
                                <option value="lulus" {{ $item['decision'] === 'lulus' ? 'selected' : '' }}>LULUS</option>
                                <option value="tidak_lulus" {{ $item['decision'] === 'tidak_lulus' ? 'selected' : '' }}>TIDAK LULUS (Tinggal Kelas XII)</option>
                                <option value="unverified" {{ $item['decision'] === 'unverified' ? 'selected' : '' }}>BELUM TERVERIFIKASI</option>
                            </select>
                        </div>
                        <div class="mb-3" id="targetClassDiv{{ $item['id'] }}" style="{{ $item['decision'] === 'tidak_lulus' ? 'display:block;' : 'display:none;' }}">
                            <label class="form-label fw-bold small">Kelas Tujuan Penempatan Tingkat XII (Tahun {{ $op->target_year ?? 'Tujuan' }})</label>
                            <select name="target_class_id" class="form-select">
                                <option value="">-- Pilih Kelas XII Tujuan --</option>
                                @foreach ($targetClasses as $tc)
                                    <option value="{{ $tc->id }}" {{ (string)$tc->id === (string)$item['target_class_id'] ? 'selected' : '' }}>
                                        {{ $tc->nama_kelas }} ({{ $tc->jurusan_kode }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Alasan Penyesuaian Administratif <span class="text-danger">*</span></label>
                            <input type="text" name="alasan" class="form-control" placeholder="Tuliskan alasan perubahan keputusan..." required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary btn-sm">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach

{{-- Modal Konfirmasi Akhir --}}
<div class="modal fade" id="confirmExecutionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.kelulusan.execute', $op->id) }}">
                @csrf
                <div class="modal-header bg-success text-white">
                    <h6 class="modal-title fw-bold">Konfirmasi Finalisasi Kelulusan Kelas XII</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small mb-3">
                        Anda akan memfinalisasi kelulusan untuk <strong>{{ $summary['total'] }}</strong> siswa tingkat akhir Tahun Ajaran <strong>{{ $op->source_year }}</strong>.
                    </p>
                    <ul class="small mb-3 ps-3">
                        <li><strong>{{ $summary['lulus'] }}</strong> siswa LULUS akan ditutup riwayat kelasnya dan tercatat resmi sebagai Alumni.</li>
                        @if ($summary['tidak_lulus'] > 0)
                            <li><strong>{{ $summary['tidak_lulus'] }}</strong> siswa TIDAK LULUS akan ditempatkan di kelas XII Tahun Ajaran {{ $op->target_year }}.</li>
                        @endif
                        <li>Proses ini dijalankan dalam transaksi atomik dengan kunci operasi akademik.</li>
                        <li>Riwayat jurnal dan presensi masa lalu tetap aman dan tidak terhapus.</li>
                    </ul>
                    <div class="alert alert-warning small mb-0">
                        Pastikan semua data sudah sesuai dengan Surat Keputusan resmi sekolah sebelum melanjutkan.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success fw-bold">
                        <x-ikon nama="check-lg" /> Ya, Eksekusi Finalisasi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
