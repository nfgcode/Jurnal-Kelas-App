@extends('layouts.app')

@section('title', 'Detail Kelas')

@section('content')
<div class="mb-4">
    <a href="{{ route('kelas.index') }}" class="text-decoration-none text-muted small">
        <i class="bi bi-arrow-left me-1"></i> Kembali ke Data Kelas
    </a>
</div>

<div class="row g-4">
    {{-- Kelas Info Card --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="fw-semibold mb-0">
                    <i class="bi bi-building me-2 text-primary"></i>Informasi Kelas
                </h6>
            </div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr>
                        <td class="text-muted ps-0" style="width: 120px;">Nama Kelas</td>
                        <td class="fw-medium">{{ $kelas->nama }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-0">Tingkat</td>
                        <td>{{ $kelas->tingkat }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-0">Jurusan</td>
                        <td>{{ $kelas->jurusan }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-0">Tahun Ajaran</td>
                        <td>{{ $kelas->tahun_ajaran }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-0">Wali Kelas</td>
                        <td>{{ $kelas->waliKelas->name ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-0">Jumlah Siswa</td>
                        <td>
                            <span class="badge bg-primary">{{ $kelas->siswas->count() ?? 0 }} siswa</span>
                        </td>
                    </tr>
                </table>

                <div class="mt-3 d-flex gap-2">
                    <a href="{{ route('kelas.edit', $kelas->id) }}" class="btn btn-warning btn-sm">
                        <i class="bi bi-pencil-square me-1"></i> Edit
                    </a>
                    <form action="{{ route('kelas.destroy', $kelas->id) }}" method="POST"
                          onsubmit="return confirm('Apakah Anda yakin ingin menghapus kelas ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">
                            <i class="bi bi-trash me-1"></i> Hapus
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Siswa List --}}
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="fw-semibold mb-0">
                    <i class="bi bi-people me-2 text-success"></i>Daftar Siswa
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
                                <th>Jenis Kelamin</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($kelas->siswas ?? [] as $index => $siswa)
                                <tr>
                                    <td class="ps-3">{{ $index + 1 }}</td>
                                    <td>{{ $siswa->nis }}</td>
                                    <td class="fw-medium">{{ $siswa->nama }}</td>
                                    <td>{{ $siswa->jenis_kelamin ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="bi bi-people fs-2 d-block mb-2 opacity-25"></i>
                                            <p class="mb-0">Belum ada siswa di kelas ini.</p>
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
