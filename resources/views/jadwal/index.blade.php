@extends('layouts.app')

@section('title', 'Data Jadwal')

@section('content')
{{-- Header --}}
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-semibold text-dark mb-1">Data Jadwal</h5>
        <p class="text-muted small mb-0">Kelola jadwal pelajaran</p>
    </div>
    <a href="{{ route('jadwal.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Tambah Jadwal
    </a>
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
                        <th>Hari</th>
                        <th>Jam</th>
                        <th>Kelas</th>
                        <th>Mata Pelajaran</th>
                        <th>Guru</th>
                        <th class="text-center" style="width: 130px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($jadwal as $index => $item)
                        <tr>
                            <td class="ps-3">{{ $index + 1 }}</td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ $item->hari }}</span>
                            </td>
                            <td>{{ $item->jam_mulai }} - {{ $item->jam_selesai }}</td>
                            <td class="fw-medium">{{ $item->kelas->nama ?? '-' }}</td>
                            <td>{{ $item->mataPelajaran->nama ?? '-' }}</td>
                            <td>{{ $item->guru->name ?? '-' }}</td>
                            <td class="text-center">
                                <a href="{{ route('jadwal.edit', $item->id) }}" class="btn btn-warning btn-sm me-1" title="Edit">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <form action="{{ route('jadwal.destroy', $item->id) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Apakah Anda yakin ingin menghapus jadwal ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm" title="Hapus">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="bi bi-calendar3 fs-1 d-block mb-2 opacity-25"></i>
                                    <p class="mb-1">Belum ada data jadwal.</p>
                                    <a href="{{ route('jadwal.create') }}" class="btn btn-sm btn-outline-primary mt-2">
                                        <i class="bi bi-plus-lg me-1"></i> Tambah Jadwal Baru
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if(method_exists($jadwal, 'links') && $jadwal->hasPages())
        <div class="card-footer bg-white border-top py-3">
            {{ $jadwal->links() }}
        </div>
    @endif
</div>
@endsection
