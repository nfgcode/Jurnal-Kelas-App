@extends('layouts.app')

@section('title', 'Tambah Kelas Baru')

@section('content')
<div class="mb-4">
    <a href="{{ route('kelas.index') }}" class="text-decoration-none text-muted small">
        <i class="bi bi-arrow-left me-1"></i> Kembali ke Data Kelas
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="fw-semibold mb-0">
                    <i class="bi bi-plus-circle me-2 text-primary"></i>Tambah Kelas Baru
                </h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('kelas.store') }}">
                    @csrf

                    {{-- Nama Kelas --}}
                    <div class="mb-3">
                        <label for="nama" class="form-label fw-medium">Nama Kelas <span class="text-danger">*</span></label>
                        <input type="text"
                               id="nama"
                               name="nama"
                               class="form-control @error('nama') is-invalid @enderror"
                               value="{{ old('nama') }}"
                               placeholder="Contoh: XII RPL 1"
                               required>
                        @error('nama')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Tingkat --}}
                    <div class="mb-3">
                        <label for="tingkat" class="form-label fw-medium">Tingkat <span class="text-danger">*</span></label>
                        <select id="tingkat" name="tingkat" class="form-select @error('tingkat') is-invalid @enderror" required>
                            <option value="" disabled selected>Pilih Tingkat</option>
                            <option value="X" {{ old('tingkat') == 'X' ? 'selected' : '' }}>X</option>
                            <option value="XI" {{ old('tingkat') == 'XI' ? 'selected' : '' }}>XI</option>
                            <option value="XII" {{ old('tingkat') == 'XII' ? 'selected' : '' }}>XII</option>
                        </select>
                        @error('tingkat')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Jurusan --}}
                    <div class="mb-3">
                        <label for="jurusan" class="form-label fw-medium">Jurusan <span class="text-danger">*</span></label>
                        <input type="text"
                               id="jurusan"
                               name="jurusan"
                               class="form-control @error('jurusan') is-invalid @enderror"
                               value="{{ old('jurusan') }}"
                               placeholder="Contoh: RPL, TKJ, MM"
                               required>
                        @error('jurusan')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Tahun Ajaran --}}
                    <div class="mb-3">
                        <label for="tahun_ajaran" class="form-label fw-medium">Tahun Ajaran <span class="text-danger">*</span></label>
                        <input type="text"
                               id="tahun_ajaran"
                               name="tahun_ajaran"
                               class="form-control @error('tahun_ajaran') is-invalid @enderror"
                               value="{{ old('tahun_ajaran') }}"
                               placeholder="Contoh: 2025/2026"
                               required>
                        @error('tahun_ajaran')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Wali Kelas --}}
                    <div class="mb-4">
                        <label for="guru_id" class="form-label fw-medium">Wali Kelas <span class="text-danger">*</span></label>
                        <select id="guru_id" name="guru_id" class="form-select @error('guru_id') is-invalid @enderror" required>
                            <option value="" disabled selected>Pilih Wali Kelas</option>
                            @foreach ($gurus ?? [] as $guru)
                                <option value="{{ $guru->id }}" {{ old('guru_id') == $guru->id ? 'selected' : '' }}>
                                    {{ $guru->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('guru_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Buttons --}}
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Simpan
                        </button>
                        <a href="{{ route('kelas.index') }}" class="btn btn-secondary">
                            <i class="bi bi-x-lg me-1"></i> Batal
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
