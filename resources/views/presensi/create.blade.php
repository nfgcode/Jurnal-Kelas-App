@extends('layouts.app')

@section('title', 'Input Presensi')

@section('content')
<div class="mb-4">
    <a href="{{ route('jurnal.show', $jurnal->id) }}" class="text-decoration-none text-muted small">
        <i class="bi bi-arrow-left me-1"></i> Kembali ke Detail Jurnal
    </a>
</div>

{{-- Jurnal Info --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 40px; height: 40px; background: rgba(13,110,253,0.1);">
                        <i class="bi bi-calendar3 text-primary"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Tanggal</small>
                        <span class="fw-medium">{{ \Carbon\Carbon::parse($jurnal->tanggal)->format('d F Y') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 40px; height: 40px; background: rgba(25,135,84,0.1);">
                        <i class="bi bi-building text-success"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Kelas</small>
                        <span class="fw-medium">{{ $jurnal->jadwal->kelas->nama ?? '-' }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 40px; height: 40px; background: rgba(111,66,193,0.1);">
                        <i class="bi bi-book" style="color: #6f42c1;"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Materi</small>
                        <span class="fw-medium">{{ Str::limit($jurnal->materi, 50) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Presensi Form --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h6 class="fw-semibold mb-0">
            <i class="bi bi-person-check me-2 text-success"></i>Input Presensi
        </h6>
    </div>
    <div class="card-body p-0">
        <form method="POST" action="{{ route('presensi.store') }}">
            @csrf
            <input type="hidden" name="jurnal_id" value="{{ $jurnal->id }}">

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width: 50px;">No</th>
                            <th>Nama Siswa</th>
                            <th style="width: 100px;">NIS</th>
                            <th class="text-center" style="width: 320px;">Status</th>
                            <th style="width: 200px;">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($siswas ?? [] as $index => $siswa)
                            <tr>
                                <td class="ps-3">{{ $index + 1 }}</td>
                                <td class="fw-medium">{{ $siswa->nama }}</td>
                                <td>{{ $siswa->nis }}</td>
                                <td class="text-center">
                                    <input type="hidden" name="siswa_ids[]" value="{{ $siswa->id }}">
                                    <div class="d-flex justify-content-center gap-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio"
                                                   name="status[{{ $siswa->id }}]" value="hadir"
                                                   id="hadir_{{ $siswa->id }}" checked>
                                            <label class="form-check-label small text-success fw-medium" for="hadir_{{ $siswa->id }}">
                                                Hadir
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio"
                                                   name="status[{{ $siswa->id }}]" value="sakit"
                                                   id="sakit_{{ $siswa->id }}">
                                            <label class="form-check-label small text-info fw-medium" for="sakit_{{ $siswa->id }}">
                                                Sakit
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio"
                                                   name="status[{{ $siswa->id }}]" value="izin"
                                                   id="izin_{{ $siswa->id }}">
                                            <label class="form-check-label small fw-medium" for="izin_{{ $siswa->id }}" style="color: #e91e8f;">
                                                Izin
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio"
                                                   name="status[{{ $siswa->id }}]" value="alpa"
                                                   id="alpa_{{ $siswa->id }}">
                                            <label class="form-check-label small text-danger fw-medium" for="alpa_{{ $siswa->id }}">
                                                Alpa
                                            </label>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <input type="text"
                                           name="keterangan[{{ $siswa->id }}]"
                                           class="form-control form-control-sm"
                                           placeholder="Opsional...">
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="bi bi-people fs-2 d-block mb-2 opacity-25"></i>
                                        <p class="mb-0">Tidak ada siswa di kelas ini.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(isset($siswas) && count($siswas) > 0)
                <div class="card-footer bg-white border-top py-3 px-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Simpan Presensi
                    </button>
                    <a href="{{ route('jurnal.show', $jurnal->id) }}" class="btn btn-secondary ms-2">
                        <i class="bi bi-x-lg me-1"></i> Batal
                    </a>
                </div>
            @endif
        </form>
    </div>
</div>
@endsection
