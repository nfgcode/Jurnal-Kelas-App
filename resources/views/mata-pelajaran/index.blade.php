@extends('layouts.app')

@section('title', 'Data Mata Pelajaran')

@section('content')
{{-- Header --}}
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-semibold text-dark mb-1">Data Mata Pelajaran</h5>
        <p class="text-muted small mb-0">Kelola data mata pelajaran yang tersedia</p>
    </div>
    <a href="{{ route('mata-pelajaran.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Tambah Mata Pelajaran
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
                        <th style="width: 120px;">Kode</th>
                        <th>Nama</th>
                        <th>Deskripsi</th>
                        <th class="text-center" style="width: 130px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($mataPelajaran as $index => $item)
                        <tr>
                            <td class="ps-3">{{ $index + 1 }}</td>
                            <td><span class="badge bg-light text-dark border">{{ $item->kode }}</span></td>
                            <td class="fw-medium">{{ $item->nama }}</td>
                            <td class="text-muted">{{ Str::limit($item->deskripsi, 60) }}</td>
                            <td class="text-center">
                                <a href="{{ route('mata-pelajaran.edit', $item->id) }}" class="btn btn-warning btn-sm me-1" title="Edit">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <form action="{{ route('mata-pelajaran.destroy', $item->id) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Apakah Anda yakin ingin menghapus mata pelajaran ini?')">
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
                            <td colspan="5" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="bi bi-book fs-1 d-block mb-2 opacity-25"></i>
                                    <p class="mb-1">Belum ada data mata pelajaran.</p>
                                    <a href="{{ route('mata-pelajaran.create') }}" class="btn btn-sm btn-outline-primary mt-2">
                                        <i class="bi bi-plus-lg me-1"></i> Tambah Mata Pelajaran
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if(method_exists($mataPelajaran, 'links') && $mataPelajaran->hasPages())
        <div class="card-footer bg-white border-top py-3">
            {{ $mataPelajaran->links() }}
        </div>
    @endif
</div>
@endsection
