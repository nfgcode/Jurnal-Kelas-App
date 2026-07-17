@extends('layouts.app')

@section('title', 'Data Jurnal')

@section('content')
{{-- Header --}}
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-semibold text-dark mb-1">Data Jurnal</h5>
        <p class="text-muted small mb-0">Kelola jurnal kegiatan pembelajaran</p>
    </div>
    <a href="{{ route('jurnal.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Tambah Jurnal
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
                        <th>Tanggal</th>
                        <th>Kelas</th>
                        <th>Mata Pelajaran</th>
                        <th>Materi</th>
                        <th>Guru</th>
                        <th class="text-center" style="width: 220px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($jurnals as $index => $item)
                        <tr>
                            <td class="ps-3">{{ $index + 1 }}</td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-calendar3 me-1"></i>{{ \Carbon\Carbon::parse($item->tanggal)->format('d/m/Y') }}
                                </span>
                            </td>
                            <td class="fw-medium">{{ $item->jadwal->kelas->nama ?? '-' }}</td>
                            <td>{{ $item->jadwal->mataPelajaran->nama ?? '-' }}</td>
                            <td class="text-muted">{{ Str::limit($item->materi, 40) }}</td>
                            <td>{{ $item->jadwal->guru->name ?? '-' }}</td>
                            <td class="text-center">
                                <a href="{{ route('jurnal.show', $item->id) }}" class="btn btn-info btn-sm" title="Lihat">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('jurnal.edit', $item->id) }}" class="btn btn-warning btn-sm" title="Edit">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <a href="{{ route('presensi.create', ['jurnal_id' => $item->id]) }}" class="btn btn-success btn-sm" title="Presensi">
                                    <i class="bi bi-person-check"></i>
                                </a>
                                <form action="{{ route('jurnal.destroy', $item->id) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Apakah Anda yakin ingin menghapus jurnal ini?')">
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
                                    <i class="bi bi-journal-text fs-1 d-block mb-2 opacity-25"></i>
                                    <p class="mb-1">Belum ada data jurnal.</p>
                                    <a href="{{ route('jurnal.create') }}" class="btn btn-sm btn-outline-primary mt-2">
                                        <i class="bi bi-plus-lg me-1"></i> Tambah Jurnal Baru
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if(method_exists($jurnals, 'links') && $jurnals->hasPages())
        <div class="card-footer bg-white border-top py-3">
            {{ $jurnals->links() }}
        </div>
    @endif
</div>
@endsection
