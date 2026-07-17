@extends('layouts.app')

@section('title', 'Detail Jurnal')

@section('content')
<div class="mb-4">
    <a href="{{ route('jurnal.index') }}" class="text-decoration-none text-muted small">
        <i class="bi bi-arrow-left me-1"></i> Kembali ke Data Jurnal
    </a>
</div>

<div class="row g-4">
    {{-- Jurnal Detail Card --}}
    <div class="col-lg-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-semibold mb-0">
                    <i class="bi bi-journal-text me-2 text-primary"></i>Detail Jurnal
                </h6>
                <div class="d-flex gap-2">
                    <a href="{{ route('jurnal.edit', $jurnal->id) }}" class="btn btn-warning btn-sm">
                        <i class="bi bi-pencil-square me-1"></i> Edit
                    </a>
                    <a href="{{ route('presensi.create', ['jurnal_id' => $jurnal->id]) }}" class="btn btn-success btn-sm">
                        <i class="bi bi-person-check me-1"></i> Input Presensi
                    </a>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="text-muted small d-block">Tanggal</label>
                            <span class="fw-medium">
                                <i class="bi bi-calendar3 me-1 text-primary"></i>
                                {{ \Carbon\Carbon::parse($jurnal->tanggal)->format('d F Y') }}
                            </span>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted small d-block">Kelas</label>
                            <span class="fw-medium">{{ $jurnal->jadwal->kelas->nama ?? '-' }}</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="text-muted small d-block">Mata Pelajaran</label>
                            <span class="fw-medium">{{ $jurnal->jadwal->mataPelajaran->nama ?? '-' }}</span>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted small d-block">Guru</label>
                            <span class="fw-medium">{{ $jurnal->jadwal->guru->name ?? '-' }}</span>
                        </div>
                    </div>
                    <div class="col-12">
                        <hr class="my-0">
                    </div>
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label class="text-muted small d-block mb-1">Materi</label>
                            <p class="mb-0">{{ $jurnal->materi }}</p>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label class="text-muted small d-block mb-1">Kegiatan</label>
                            <p class="mb-0">{{ $jurnal->kegiatan }}</p>
                        </div>
                    </div>
                    @if($jurnal->catatan)
                        <div class="col-md-12">
                            <div class="mb-0">
                                <label class="text-muted small d-block mb-1">Catatan</label>
                                <p class="mb-0 fst-italic text-muted">{{ $jurnal->catatan }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Presensi Data --}}
    <div class="col-lg-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-semibold mb-0">
                    <i class="bi bi-person-check me-2 text-success"></i>Data Presensi
                </h6>
                @if(isset($jurnal->presensis) && $jurnal->presensis->count() > 0)
                    <div class="d-flex gap-2">
                        <span class="badge bg-success">Hadir: {{ $jurnal->presensis->where('status', 'hadir')->count() }}</span>
                        <span class="badge bg-info">Sakit: {{ $jurnal->presensis->where('status', 'sakit')->count() }}</span>
                        <span class="badge" style="background-color: #e91e8f;">Izin: {{ $jurnal->presensis->where('status', 'izin')->count() }}</span>
                        <span class="badge bg-danger">Alpa: {{ $jurnal->presensis->where('status', 'alpa')->count() }}</span>
                    </div>
                @endif
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3" style="width: 50px;">No</th>
                                <th>NIS</th>
                                <th>Nama Siswa</th>
                                <th>Status</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($jurnal->presensis ?? [] as $index => $presensi)
                                <tr>
                                    <td class="ps-3">{{ $index + 1 }}</td>
                                    <td>{{ $presensi->siswa->nis ?? '-' }}</td>
                                    <td class="fw-medium">{{ $presensi->siswa->nama ?? '-' }}</td>
                                    <td>
                                        @switch($presensi->status)
                                            @case('hadir')
                                                <span class="badge bg-success">Hadir</span>
                                                @break
                                            @case('sakit')
                                                <span class="badge bg-info">Sakit</span>
                                                @break
                                            @case('izin')
                                                <span class="badge" style="background-color: #e91e8f;">Izin</span>
                                                @break
                                            @case('alpa')
                                                <span class="badge bg-danger">Alpa</span>
                                                @break
                                            @default
                                                <span class="badge bg-secondary">{{ $presensi->status }}</span>
                                        @endswitch
                                    </td>
                                    <td class="text-muted">{{ $presensi->keterangan ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="bi bi-person-check fs-2 d-block mb-2 opacity-25"></i>
                                            <p class="mb-1">Belum ada data presensi untuk jurnal ini.</p>
                                            <a href="{{ route('presensi.create', ['jurnal_id' => $jurnal->id]) }}" class="btn btn-sm btn-outline-success mt-2">
                                                <i class="bi bi-plus-lg me-1"></i> Input Presensi
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
