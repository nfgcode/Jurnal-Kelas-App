@extends('layouts.app')

@section('title', 'Data Kelas')

@section('content')
{{-- Header --}}
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-semibold text-dark mb-1">Data Kelas</h5>
        <p class="text-muted small mb-0">Kelola data kelas yang tersedia</p>
    </div>
    <a href="{{ route('kelas.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Tambah Kelas
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
                        <th>Nama Kelas</th>
                        <th>Tingkat</th>
                        <th>Jurusan</th>
                        <th>Tahun Ajaran</th>
                        <th>Wali Kelas</th>
                        <th class="text-center" style="width: 150px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($kelas as $index => $item)
                        <tr>
                            <td class="ps-3">{{ $index + 1 }}</td>
                            <td class="fw-medium">{{ $item->nama }}</td>
                            <td>{{ $item->tingkat }}</td>
                            <td>{{ $item->jurusan }}</td>
                            <td>{{ $item->tahun_ajaran }}</td>
                            <td>{{ $item->waliKelas->name ?? '-' }}</td>
                            <td class="text-center">
                                <a href="{{ route('kelas.show', $item->id) }}" class="btn btn-info btn-sm me-1" title="Lihat">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('kelas.edit', $item->id) }}" class="btn btn-warning btn-sm me-1" title="Edit">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <form action="{{ route('kelas.destroy', $item->id) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Apakah Anda yakin ingin menghapus kelas ini?')">
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
                                    <i class="bi bi-building fs-1 d-block mb-2 opacity-25"></i>
                                    <p class="mb-1">Belum ada data kelas.</p>
                                    <a href="{{ route('kelas.create') }}" class="btn btn-sm btn-outline-primary mt-2">
                                        <i class="bi bi-plus-lg me-1"></i> Tambah Kelas Baru
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if(method_exists($kelas, 'links') && $kelas->hasPages())
        <div class="card-footer bg-white border-top py-3">
            {{ $kelas->links() }}
        </div>
    @endif
</div>
@endsection
