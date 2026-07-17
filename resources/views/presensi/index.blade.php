@extends('layouts.app')

@section('title', 'Data Presensi')

@section('content')
{{-- Header --}}
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-semibold text-dark mb-1">Data Presensi</h5>
        <p class="text-muted small mb-0">Rekap data kehadiran siswa</p>
    </div>
</div>

{{-- Flash Message --}}
@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

{{-- Table Card --}}
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width: 50px;">No</th>
                        <th>Tanggal</th>
                        <th>Kelas</th>
                        <th>Siswa</th>
                        <th>Status</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($presensi as $index => $item)
                        <tr>
                            <td class="ps-3">{{ $index + 1 }}</td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-calendar3 me-1"></i>{{ \Carbon\Carbon::parse($item->jurnal->tanggal ?? '')->format('d/m/Y') }}
                                </span>
                            </td>
                            <td>{{ $item->jurnal->jadwal->kelas->nama ?? '-' }}</td>
                            <td class="fw-medium">{{ $item->siswa->nama ?? '-' }}</td>
                            <td>
                                @switch($item->status)
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
                                        <span class="badge bg-secondary">{{ $item->status }}</span>
                                @endswitch
                            </td>
                            <td class="text-muted">{{ $item->keterangan ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="bi bi-person-check fs-1 d-block mb-2 opacity-25"></i>
                                    <p class="mb-0">Belum ada data presensi.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if(method_exists($presensi, 'links') && $presensi->hasPages())
        <div class="card-footer bg-white border-top py-3">
            {{ $presensi->links() }}
        </div>
    @endif
</div>
@endsection
