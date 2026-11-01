@extends('layouts.app')

@section('title', 'Ekspor & Cadangkan Data')

@section('content')
<div class="cadangan-page">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><x-ikon nama="shield-lock" /> Ekspor & Cadangkan Data</h4>
            <div class="text-muted small">Pemisahan ekspor administratif tabel (CSV) dan arsip snapshot database MySQL (.SQL.GZ / .SQL).</div>
        </div>
        <div>
            <a href="{{ route('admin.manajemen-data.index') }}" class="btn btn-outline-secondary btn-sm">
                <x-ikon nama="arrow-left" /> Kembali ke Manajemen Data
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success d-flex align-items-center gap-2 mb-4" role="alert">
            <x-ikon nama="check-circle" />
            <div>{{ session('status') }}</div>
        </div>
    @endif

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

    <div class="row g-4 mb-4">
        {{-- Panel Buat Cadangan Database Manual --}}
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-dark text-white fw-bold py-3">
                    <x-ikon nama="database" /> Buat Cadangan Database Baru
                </div>
                <div class="card-body p-4">
                    <p class="small text-muted mb-3">
                        Snapshot konsisten InnoDB mencakup 31 tabel relasional inti aplikasi. Disimpan secara privat dan diverifikasi checksum SHA-256.
                    </p>
                    <form method="POST" action="{{ route('admin.manajemen-data.cadangan.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Pilih Format Cadangan <span class="text-danger">*</span></label>
                            <select name="format" class="form-select form-select-sm" required>
                                <option value="sql_gz" selected>MySQL Dump Terkompresi (.sql.gz) - Disarankan</option>
                                <option value="sql">MySQL Dump Teks Polos (.sql) - Alternatif</option>
                                <option value="zip_berkas">Arsip Berkas Penting (.zip) - Berkas Publik Ter-allowlist</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Catatan Cadangan (Opsional)</label>
                            <input type="text" name="catatan" class="form-control form-control-sm" placeholder="contoh: Sebelum pembaruan kurikulum semester baru">
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm fw-bold px-3">
                            <x-ikon nama="play-fill" /> Buat & Verifikasi Cadangan Sekarang
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Panel Ekspor Administratif CSV --}}
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-primary text-white fw-bold py-3">
                    <x-ikon nama="file-earmark-spreadsheet" /> Ekspor Administratif CSV Aman
                </div>
                <div class="card-body p-4">
                    <p class="small text-muted mb-3">
                        Unduh data tabel dalam format CSV untuk keperluan analisis administratif. Password hash, token sesi, dan kunci rahasia otomatis dihilangkan.
                    </p>
                    <form method="POST" action="{{ route('admin.manajemen-data.ekspor.csv') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Pilih Tabel untuk Diekspor <span class="text-danger">*</span></label>
                            <select name="tabel" class="form-select form-select-sm" required>
                                @foreach ($tabelList as $tbl)
                                    <option value="{{ $tbl }}">{{ $tbl }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="small text-muted mb-3" style="font-size: 0.8rem;">
                            <em>Catatan: Ekspor CSV ditujukan untuk pelaporan staf dan <strong>bukan</strong> untuk pemulihan bencana sistem.</em>
                        </div>
                        <button type="submit" class="btn btn-outline-primary btn-sm fw-bold px-3">
                            <x-ikon nama="download" /> Unduh Berkas CSV Aman
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Daftar Arsip Cadangan Tersimpan --}}
    <div class="card shadow-sm border-0">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-3">
            <h6 class="fw-bold mb-0"><x-ikon nama="archive" /> Riwayat Arsip Cadangan Tersimpan</h6>
            <span class="badge bg-light text-muted border">Retensi 12 Bulan Aktif</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th width="40">Pin</th>
                        <th>Waktu Pembuatan</th>
                        <th>Nama Berkas & Catatan</th>
                        <th>Format</th>
                        <th>Ukuran</th>
                        <th>Tipe</th>
                        <th>Status Validasi</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="small">
                    @forelse ($arsipList as $a)
                        <tr>
                            <td class="text-center">
                                <form method="POST" action="{{ route('admin.manajemen-data.cadangan.pin', $a->id) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-link p-0 text-decoration-none" title="{{ $a->penting ? 'Lepas tanda penting' : 'Tandai penting (Lindungi dari retensi)' }}">
                                        <x-ikon nama="{{ $a->penting ? 'pin-angle-fill' : 'pin-angle' }}" class="{{ $a->penting ? 'text-warning fs-5' : 'text-muted' }}" />
                                    </button>
                                </form>
                            </td>
                            <td>{{ \Illuminate\Support\Carbon::parse($a->created_at)->format('d/m/Y H:i') }}</td>
                            <td>
                                <div class="fw-bold">{{ $a->nama_berkas }}</div>
                                @if ($a->catatan)
                                    <div class="text-muted small">{{ $a->catatan }}</div>
                                @endif
                                <div class="text-secondary small font-monospace" style="font-size: 0.75rem;">SHA256: {{ substr($a->checksum_sha256, 0, 16) }}...</div>
                            </td>
                            <td><span class="badge bg-light text-dark border">{{ strtoupper($a->format) }}</span></td>
                            <td>{{ round($a->ukuran_bytes / 1024, 1) }} KB</td>
                            <td>
                                <span class="badge {{ $a->tipe === 'otomatis_bulanan' ? 'bg-info text-dark' : 'bg-secondary' }}">
                                    {{ $a->tipe === 'otomatis_bulanan' ? 'Otomatis' : 'Manual' }}
                                </span>
                            </td>
                            <td>
                                <span class="badge {{ $a->status_validasi === 'terverifikasi' ? 'bg-success' : 'bg-danger' }}">
                                    <x-ikon nama="check-circle" /> Terverifikasi
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <a href="{{ route('admin.manajemen-data.cadangan.download', $a->id) }}" class="btn btn-sm btn-outline-primary" title="Unduh Privat">
                                        <x-ikon nama="download" />
                                    </a>
                                    <form method="POST" action="{{ route('admin.manajemen-data.cadangan.destroy', $a->id) }}" onsubmit="return confirm('Apakah Anda yakin ingin menghapus arsip cadangan ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Cadangan" {{ $a->penting ? 'disabled' : '' }}>
                                            <x-ikon nama="trash" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">Belum ada arsip cadangan tersimpan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($arsipList->hasPages())
            <div class="card-footer bg-transparent py-3">
                {{ $arsipList->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
