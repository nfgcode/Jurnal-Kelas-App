@extends('layouts.app')

@section('title', 'Tambah Jadwal')

@section('content')
<div class="mb-4">
    <a href="{{ route('jadwal.index') }}" class="text-decoration-none text-muted small">
        <i class="bi bi-arrow-left me-1"></i> Kembali ke Data Jadwal
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="fw-semibold mb-0">
                    <i class="bi bi-plus-circle me-2 text-primary"></i>Tambah Jadwal Baru
                </h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('jadwal.store') }}">
                    @csrf

                    {{-- Kelas --}}
                    <div class="mb-3">
                        <label for="kelas_id" class="form-label fw-medium">Kelas <span class="text-danger">*</span></label>
                        <select id="kelas_id" name="kelas_id" class="form-select @error('kelas_id') is-invalid @enderror" required>
                            <option value="" disabled selected>Pilih Kelas</option>
                            @foreach ($kelas ?? [] as $k)
                                <option value="{{ $k->id }}" {{ old('kelas_id') == $k->id ? 'selected' : '' }}>
                                    {{ $k->nama }}
                                </option>
                            @endforeach
                        </select>
                        @error('kelas_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Mata Pelajaran --}}
                    <div class="mb-3">
                        <label for="mata_pelajaran_id" class="form-label fw-medium">Mata Pelajaran <span class="text-danger">*</span></label>
                        <select id="mata_pelajaran_id" name="mata_pelajaran_id" class="form-select @error('mata_pelajaran_id') is-invalid @enderror" required>
                            <option value="" disabled selected>Pilih Mata Pelajaran</option>
                            @foreach ($mataPelajaran ?? [] as $mp)
                                <option value="{{ $mp->id }}" {{ old('mata_pelajaran_id') == $mp->id ? 'selected' : '' }}>
                                    {{ $mp->nama }}
                                </option>
                            @endforeach
                        </select>
                        @error('mata_pelajaran_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Guru --}}
                    <div class="mb-3">
                        <label for="guru_id" class="form-label fw-medium">Guru <span class="text-danger">*</span></label>
                        <select id="guru_id" name="guru_id" class="form-select @error('guru_id') is-invalid @enderror" required>
                            <option value="" disabled selected>Pilih Guru</option>
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

                    {{-- Hari --}}
                    <div class="mb-3">
                        <label for="hari" class="form-label fw-medium">Hari <span class="text-danger">*</span></label>
                        <select id="hari" name="hari" class="form-select @error('hari') is-invalid @enderror" required>
                            <option value="" disabled selected>Pilih Hari</option>
                            @foreach (['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'] as $hari)
                                <option value="{{ $hari }}" {{ old('hari') == $hari ? 'selected' : '' }}>{{ $hari }}</option>
                            @endforeach
                        </select>
                        @error('hari')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Jam --}}
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="jam_mulai" class="form-label fw-medium">Jam Mulai <span class="text-danger">*</span></label>
                            <input type="time"
                                   id="jam_mulai"
                                   name="jam_mulai"
                                   class="form-control @error('jam_mulai') is-invalid @enderror"
                                   value="{{ old('jam_mulai') }}"
                                   required>
                            @error('jam_mulai')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="jam_selesai" class="form-label fw-medium">Jam Selesai <span class="text-danger">*</span></label>
                            <input type="time"
                                   id="jam_selesai"
                                   name="jam_selesai"
                                   class="form-control @error('jam_selesai') is-invalid @enderror"
                                   value="{{ old('jam_selesai') }}"
                                   required>
                            @error('jam_selesai')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Buttons --}}
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Simpan
                        </button>
                        <a href="{{ route('jadwal.index') }}" class="btn btn-secondary">
                            <i class="bi bi-x-lg me-1"></i> Batal
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
