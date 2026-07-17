@extends('layouts.app')

@section('title', 'Tambah Mata Pelajaran')

@section('content')
<div class="mb-4">
    <a href="{{ route('mata-pelajaran.index') }}" class="text-decoration-none text-muted small">
        <i class="bi bi-arrow-left me-1"></i> Kembali ke Data Mata Pelajaran
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="fw-semibold mb-0">
                    <i class="bi bi-plus-circle me-2 text-primary"></i>Tambah Mata Pelajaran Baru
                </h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('mata-pelajaran.store') }}">
                    @csrf

                    {{-- Kode --}}
                    <div class="mb-3">
                        <label for="kode" class="form-label fw-medium">Kode <span class="text-danger">*</span></label>
                        <input type="text"
                               id="kode"
                               name="kode"
                               class="form-control @error('kode') is-invalid @enderror"
                               value="{{ old('kode') }}"
                               placeholder="Contoh: MTK, BIG, FIS"
                               required>
                        @error('kode')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Nama --}}
                    <div class="mb-3">
                        <label for="nama" class="form-label fw-medium">Nama <span class="text-danger">*</span></label>
                        <input type="text"
                               id="nama"
                               name="nama"
                               class="form-control @error('nama') is-invalid @enderror"
                               value="{{ old('nama') }}"
                               placeholder="Contoh: Matematika"
                               required>
                        @error('nama')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Deskripsi --}}
                    <div class="mb-4">
                        <label for="deskripsi" class="form-label fw-medium">Deskripsi</label>
                        <textarea id="deskripsi"
                                  name="deskripsi"
                                  class="form-control @error('deskripsi') is-invalid @enderror"
                                  rows="4"
                                  placeholder="Deskripsi singkat mata pelajaran...">{{ old('deskripsi') }}</textarea>
                        @error('deskripsi')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Buttons --}}
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Simpan
                        </button>
                        <a href="{{ route('mata-pelajaran.index') }}" class="btn btn-secondary">
                            <i class="bi bi-x-lg me-1"></i> Batal
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
