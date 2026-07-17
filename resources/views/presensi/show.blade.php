@extends('layouts.app')

@section('title', 'Detail Presensi')

@section('content')
<div class="mb-4">
    <a href="{{ route('jurnal.show', $jurnal->id) }}" class="text-decoration-none text-muted small">
        <i class="bi bi-arrow-left me-1"></i> Kembali ke Detail Jurnal
    </a>
</div>

{{-- Jurnal Info --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="row g-3 align-items-center">
            <div class="col-md-4">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 40px; height: 40px; background: rgba(13,110,253,0.1);">
                        <i class="bi bi-calendar3 text-primary"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Tanggal</small>
                        <span class="fw-medium">{{ \Carbon\Carbon::parse($jurnal->tanggal)->format('d F Y') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 40px; height: 40px; background: rgba(25,135,84,0.1);">
                        <i class="bi bi-building text-success"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Kelas</small>
                        <span class="fw-medium">{{ $jurnal->jadwal->kelas->nama ?? '-' }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 40px; height: 40px; background: rgba(111,66,193,0.1);">
                        <i class="bi bi-book" style="color: #6f42c1;"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Mata Pelajaran</small>
                        <span class="fw-medium">{{ $jurnal->jadwal->mataPelajaran->nama ?? '-' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Summary Badges --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="card-body py-2">
                <span class="badge bg-success fs-5 mb-2 px-4 py-2">{{ $presensis->where('status', 'hadir')->count() }}</span>
                <p class="mb-0 fw-medium text-muted small">Hadir</p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="card-body py-2">
                <span class="badge bg-info fs-5 mb-2 px-4 py-2">{{ $presensis->where('status', 'sakit')->count() }}</span>
                <p class="mb-0 fw-medium text-muted small">Sakit</p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="card-body py-2">
                <span class="badge fs-5 mb-2 px-4 py-2" style="background-color: #e91e8f;">{{ $presensis->where('status', 'izin')->count() }}</span>
                <p class="mb-0 fw-medium text-muted small">Izin</p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="card-body py-2">
                <span class="badge bg-danger fs-5 mb-2 px-4 py-2">{{ $presensis->where('status', 'alpa')->count() }}</span>
                <p class="mb-0 fw-medium text-muted small">Alpa</p>
            </div>
        </div>
    </div>
</div>

{{-- Presensi Table --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h6 class="fw-semibold mb-0">
            <i class="bi bi-person-check me-2 text-success"></i>Rekap Presensi
        </h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width: 50px;">No</th>
                        <th>NIS</th>
                        <th>Nama Siswa</th>
                        <th class="text-center">Status</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($presensis as $index => $item)
                        <tr>
                            <td class="ps-3">{{ $index + 1 }}</td>
                            <td>{{ $item->siswa->nis ?? '-' }}</td>
                            <td class="fw-medium">{{ $item->siswa->nama ?? '-' }}</td>
                            <td class="text-center">
                                @switch($item->status)
                                    @case('hadir')
                                        <span class="badge bg-success px-3">Hadir</span>
                                        @break
                                    @case('sakit')
                                        <span class="badge bg-info px-3">Sakit</span>
                                        @break
                                    @case('izin')
                                        <span class="badge px-3" style="background-color: #e91e8f;">Izin</span>
                                        @break
                                    @case('alpa')
                                        <span class="badge bg-danger px-3">Alpa</span>
                                        @break
                                    @default
                                        <span class="badge bg-secondary px-3">{{ $item->status }}</span>
                                @endswitch
                            </td>
                            <td class="text-muted">{{ $item->keterangan ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-4">
                                <div class="text-muted">
                                    <i class="bi bi-person-check fs-2 d-block mb-2 opacity-25"></i>
                                    <p class="mb-0">Tidak ada data presensi.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
