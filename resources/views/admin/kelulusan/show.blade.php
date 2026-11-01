@extends('layouts.app')

@section('title', 'Hasil Kelulusan Kelas XII')

@section('content')
<div class="kelulusan-show-page">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">Hasil Kelulusan Kelas XII</h4>
            <div class="text-muted small">
                Operasi #{{ $op->id }} &bull; Tahun Sumber: <strong>{{ $op->source_year }}</strong>
                @if ($op->target_year)
                    &bull; Tahun Tujuan Penempatan: <strong>{{ $op->target_year }}</strong>
                @endif
                &bull; Referensi: <strong>{{ $op->source_metadata['official_reference'] ?? '-' }}</strong>
                &bull; Status: <span class="badge bg-success">Selesai</span>
            </div>
        </div>
        <div class="d-flex gap-2">
            @php
                $attachedCoordId = \Illuminate\Support\Facades\DB::table('academic_workflow_stages')
                    ->where('operation_id', $op->id)
                    ->value('coordinator_id');
            @endphp
            @if ($attachedCoordId)
                <a href="{{ route('admin.transisi-terpadu.show', $attachedCoordId) }}" class="btn btn-primary btn-sm fw-bold">
                    <x-ikon nama="diagram-3" /> Kembali ke Transisi Terpadu
                </a>
            @endif
            <a href="{{ route('admin.kelulusan.index') }}" class="btn btn-outline-secondary btn-sm">
                <x-ikon nama="arrow-left" /> Kembali ke Daftar
            </a>
            <a href="{{ route('admin.alumni.index') }}" class="btn btn-outline-primary btn-sm">
                <x-ikon nama="people" /> Buka Menu Alumni
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success d-flex align-items-center gap-2 mb-4" role="alert">
            <x-ikon nama="check-circle" />
            <div>{{ session('status') }}</div>
        </div>
    @endif

    {{-- Ringkasan Metrik --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 border-start border-success border-4">
                <div class="card-body p-3">
                    <div class="text-success small fw-bold text-uppercase">Total Lulus Menjadi Alumni</div>
                    <div class="display-6 fw-bold text-success">{{ number_format($lulusItems->count(), 0, ',', '.') }}</div>
                    <div class="text-muted small mt-1">Dicatat pada data alumni Tahun {{ $op->source_year }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 border-start border-warning border-4">
                <div class="card-body p-3">
                    <div class="text-warning small fw-bold text-uppercase">Tidak Lulus (Tinggal Kelas XII)</div>
                    <div class="display-6 fw-bold text-warning">{{ number_format($tidakLulusItems->count(), 0, ',', '.') }}</div>
                    <div class="text-muted small mt-1">Ditempatkan di kelas XII Tahun {{ $op->target_year ?? '-' }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 border-start border-primary border-4">
                <div class="card-body p-3">
                    <div class="text-primary small fw-bold text-uppercase">Pelaksana & Waktu Selesai</div>
                    <div class="fw-bold text-dark mt-1">{{ $op->actor->nama ?? $op->actor->username ?? 'Administrator' }}</div>
                    <div class="text-muted small">{{ $op->completed_at ? $op->completed_at->format('d M Y H:i:s') : '-' }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Daftar Alumni Baru --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-transparent fw-bold d-flex justify-content-between align-items-center">
            <span>Daftar Alumni Kelulusan {{ $op->source_year }} ({{ $alumni->count() }})</span>
            <a href="{{ route('admin.alumni.index') }}" class="btn btn-sm btn-outline-primary">
                Kelola Alumni
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th>Kelas Terakhir</th>
                            <th>Tanggal Kelulusan</th>
                            <th>Keterangan Dokumen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($alumni as $alm)
                            <tr>
                                <td><code>{{ $alm->siswa_nis }}</code></td>
                                <td><strong>{{ $alm->siswa->nama ?? '-' }}</strong></td>
                                <td>{{ $alm->kelasTerakhir->nama_kelas ?? '-' }}</td>
                                <td>{{ $alm->tanggal_lulus ? $alm->tanggal_lulus->format('d M Y') : '-' }}</td>
                                <td><span class="text-muted small">{{ $alm->keterangan ?? '-' }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    Tidak ada data siswa lulus.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Daftar Siswa Tidak Lulus --}}
    @if ($tidakLulusItems->isNotEmpty())
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-transparent fw-bold">
                Daftar Siswa Tidak Lulus & Penempatan Kelas Baru ({{ $tidakLulusItems->count() }})
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>NIS</th>
                                <th>Kelas Penempatan Baru (Tahun {{ $op->target_year }})</th>
                                <th>Tingkat</th>
                                <th>Status Akademik</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tidakLulusItems as $tl)
                                <tr>
                                    <td><code>{{ $tl->student_ref }}</code></td>
                                    <td>
                                        <strong>{{ $tl->targetClass->nama_kelas ?? '-' }}</strong>
                                    </td>
                                    <td><span class="badge bg-secondary">XII</span></td>
                                    <td><span class="badge bg-warning text-dark">Tinggal Kelas</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- Audit Log Operasi --}}
    <div class="card shadow-sm border-0">
        <div class="card-header bg-transparent fw-bold">
            Jejak Audit Administratif
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Waktu</th>
                            <th>Aksi / Tahap</th>
                            <th>Perubahan Status</th>
                            <th>Hasil</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($audits as $audit)
                            <tr>
                                <td class="small">{{ $audit->created_at }}</td>
                                <td class="fw-bold small">{{ $audit->event }}</td>
                                <td class="small">
                                    <code>{{ $audit->previous_state ?? 'awal' }}</code> &rarr; <code>{{ $audit->next_state }}</code>
                                </td>
                                <td>
                                    <span class="badge {{ $audit->outcome === 'success' ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $audit->outcome }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
