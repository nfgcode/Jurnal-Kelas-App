@extends('layouts.app')

@section('title', 'Wizard Impor Data')

@section('content')
<div class="impor-wizard-page">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><x-ikon nama="upload" /> Wizard Impor Data</h4>
            <div class="text-muted small">Pilih kategori, tentukan mode sinkronisasi, dan unggah berkas template XLSX/CSV.</div>
        </div>
        <div>
            <a href="{{ route('admin.manajemen-data.index') }}" class="btn btn-outline-secondary btn-sm">
                <x-ikon nama="arrow-left" /> Kembali ke Manajemen Data
            </a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger mb-4" role="alert">
            <div class="fw-bold mb-1">Perhatian:</div>
            <ul class="mb-0 ps-3">
                @foreach ($errors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('admin.manajemen-data.impor.pratinjau') }}" enctype="multipart/form-data">
                        @csrf

                        {{-- Langkah 1: Kategori Data --}}
                        <div class="mb-4">
                            <label class="form-label fw-bold">1. Pilih Kategori Data <span class="text-danger">*</span></label>
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <input type="radio" class="btn-check" name="kategori" id="kat_siswa" value="siswa" checked>
                                    <label class="btn btn-outline-primary w-100 text-start p-3" for="kat_siswa">
                                        <div class="fw-bold fs-6 mb-1"><x-ikon nama="people" /> Data Siswa</div>
                                        <div class="small text-muted">Akun login siswa dan penempatan kelas aktif.</div>
                                    </label>
                                </div>
                                <div class="col-sm-6">
                                    <input type="radio" class="btn-check" name="kategori" id="kat_guru" value="guru">
                                    <label class="btn btn-outline-primary w-100 text-start p-3" for="kat_guru">
                                        <div class="fw-bold fs-6 mb-1"><x-ikon nama="person-badge" /> Data Guru</div>
                                        <div class="small text-muted">Akun guru, mapel yang diampu, dan perwalian kelas.</div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        {{-- Langkah 2: Mode Impor --}}
                        <div class="mb-4">
                            <label class="form-label fw-bold">2. Pilih Mode Impor <span class="text-danger">*</span></label>
                            <div class="form-check p-3 border rounded mb-2">
                                <input class="form-check-input" type="radio" name="mode" id="mode_tambah" value="tambah" checked>
                                <label class="form-check-label ps-2" for="mode_tambah">
                                    <strong class="text-success">Tambah Baru (Default)</strong>
                                    <div class="small text-muted">Hanya mendaftarkan entitas baru. Baris yang NIP/NIS-nya sudah ada di database akan ditolak sebagai konflik demi melindungi data lama.</div>
                                </label>
                            </div>

                            <div class="form-check p-3 border rounded mb-2">
                                <input class="form-check-input" type="radio" name="mode" id="mode_perbarui" value="perbarui">
                                <label class="form-check-label ps-2" for="mode_perbarui">
                                    <strong class="text-primary">Perbarui Data (Update Matched)</strong>
                                    <div class="small text-muted">Hanya memperbarui record yang cocok dengan NIP/NIS di database. Baris yang tidak terdaftar akan dilewati.</div>
                                </label>
                            </div>

                            <div class="form-check p-3 border rounded border-warning">
                                <input class="form-check-input" type="radio" name="mode" id="mode_ganti" value="ganti_keseluruhan">
                                <label class="form-check-label ps-2" for="mode_ganti">
                                    <strong class="text-warning-emphasis">Ganti Keseluruhan (Safe Synchronization)</strong>
                                    <div class="small text-muted">Menyelaraskan daftar aktif. Record database yang <strong>tidak ada</strong> dalam berkas akan di-deaktivasi (status <code>nonaktif</code>). <em>BUKAN TRUNCATE, riwayat historis tetap aman.</em></div>
                                </label>
                            </div>
                        </div>

                        {{-- Langkah 3: Unggah Berkas --}}
                        <div class="mb-4">
                            <label class="form-label fw-bold">3. Unggah Berkas Spreadsheet <span class="text-danger">*</span></label>
                            <input type="file" name="berkas" class="form-control" accept=".xlsx,.csv" required>
                            <div class="form-text small">Format diterima: <code>.xlsx</code> atau <code>.csv</code> (maksimal 10 MB, 1.000 baris).</div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                            <button type="submit" class="btn btn-primary fw-bold px-4">
                                <x-ikon nama="eye" /> Periksa & Pratinjau Berkas &rarr;
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent fw-bold small text-uppercase">
                    <x-ikon nama="download" /> Unduh Template Resmi
                </div>
                <div class="card-body p-3">
                    <p class="small text-muted mb-3">Pastikan berkas yang diunggah sesuai struktur kolom template resmi agar tidak ditolak validasi:</p>
                    <div class="d-grid gap-2">
                        <a href="{{ route('admin.impor.template', ['jenis' => 'siswa']) }}" class="btn btn-outline-primary btn-sm text-start">
                            <x-ikon nama="file-earmark-excel" /> Unduh Template Siswa (.xlsx)
                        </a>
                        <a href="{{ route('admin.impor.template', ['jenis' => 'guru']) }}" class="btn btn-outline-primary btn-sm text-start">
                            <x-ikon nama="file-earmark-excel" /> Unduh Template Guru (.xlsx)
                        </a>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 bg-light">
                <div class="card-body p-3">
                    <h6 class="fw-bold small text-muted text-uppercase mb-2"><x-ikon nama="shield-check" /> Keamanan Kredensial</h6>
                    <p class="small text-muted mb-0">
                        Sistem tidak lagi menggunakan satu password default bersama. Jika kolom password dikosongkan, sistem akan otomatis menghasilkan kata sandi acak kuat berkeamanan tinggi yang dapat diatur ulang nanti oleh admin pada menu Manajemen Akun.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
