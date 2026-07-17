@extends('layouts.app')

@section('title', 'Edit Jurnal')

@section('content')
<div class="mb-4">
    <a href="{{ route('jurnal.index') }}" class="text-decoration-none text-muted small">
        <i class="bi bi-arrow-left me-1"></i> Kembali ke Data Jurnal
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="fw-semibold mb-0">
                    <i class="bi bi-pencil-square me-2 text-warning"></i>Edit Jurnal
                </h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('jurnal.update', $jurnal->id) }}">
                    @csrf
                    @method('PUT')

                    {{-- Jadwal --}}
                    <div class="mb-3">
                        <label for="jadwal_id" class="form-label fw-medium">Jadwal <span class="text-danger">*</span></label>
                        <select id="jadwal_id" name="jadwal_id" class="form-select @error('jadwal_id') is-invalid @enderror" required>
                            <option value="" disabled>Pilih Jadwal</option>
                            @foreach ($jadwals ?? [] as $jadwal_item)
                                <option value="{{ $jadwal_item->id }}" {{ old('jadwal_id', $jurnal->jadwal_id) == $jadwal_item->id ? 'selected' : '' }}>
                                    {{ $jadwal_item->kelas->nama ?? '' }} - {{ $jadwal_item->mataPelajaran->nama ?? '' }} - {{ $jadwal_item->hari }}
                                </option>
                            @endforeach
                        </select>
                        @error('jadwal_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Tanggal --}}
                    <div class="mb-3">
                        <label for="tanggal" class="form-label fw-medium">Tanggal <span class="text-danger">*</span></label>
                        <input type="date"
                               id="tanggal"
                               name="tanggal"
                               class="form-control @error('tanggal') is-invalid @enderror"
                               value="{{ old('tanggal', $jurnal->tanggal) }}"
                               required>
                        @error('tanggal')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Materi --}}
                    <div class="mb-3">
                        <label for="materi" class="form-label fw-medium">Materi <span class="text-danger">*</span></label>
                        <textarea id="materi"
                                  name="materi"
                                  class="form-control @error('materi') is-invalid @enderror"
                                  rows="3"
                                  placeholder="Tuliskan materi pembelajaran..."
                                  required>{{ old('materi', $jurnal->materi) }}</textarea>
                        @error('materi')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Kegiatan --}}
                    <div class="mb-3">
                        <label for="kegiatan" class="form-label fw-medium">Kegiatan <span class="text-danger">*</span></label>
                        <textarea id="kegiatan"
                                  name="kegiatan"
                                  class="form-control @error('kegiatan') is-invalid @enderror"
                                  rows="3"
                                  placeholder="Tuliskan kegiatan yang dilakukan..."
                                  required>{{ old('kegiatan', $jurnal->kegiatan) }}</textarea>
                        @error('kegiatan')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Catatan --}}
                    <div class="mb-4">
                        <label for="catatan" class="form-label fw-medium">Catatan <span class="text-muted fw-normal">(opsional)</span></label>
                        <textarea id="catatan"
                                  name="catatan"
                                  class="form-control @error('catatan') is-invalid @enderror"
                                  rows="2"
                                  placeholder="Catatan tambahan...">{{ old('catatan', $jurnal->catatan) }}</textarea>
                        @error('catatan')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Buttons --}}
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Simpan Perubahan
                        </button>
                        <a href="{{ route('jurnal.index') }}" class="btn btn-secondary">
                            <i class="bi bi-x-lg me-1"></i> Batal
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
