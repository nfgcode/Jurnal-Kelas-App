@extends('layouts.app')

@section('title', 'Pratinjau Impor Data')

@section('content')
<div class="impor-pratinjau-page">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-1"><x-ikon nama="eye" /> Pratinjau Terklasifikasi Impor</h4>
            <div class="text-muted small">
                Kategori: <strong>{{ ucfirst($kategori) }}</strong>
                &bull; Mode: <span class="badge {{ $mode === 'ganti_keseluruhan' ? 'bg-warning text-dark' : ($mode === 'perbarui' ? 'bg-primary' : 'bg-success') }}">{{ strtoupper(str_replace('_', ' ', $mode)) }}</span>
                &bull; Berkas: <strong>{{ $filename }}</strong>
            </div>
        </div>
        <div>
            <a href="{{ route('admin.manajemen-data.impor.index') }}" class="btn btn-outline-secondary btn-sm">
                <x-ikon nama="arrow-left" /> Batalkan & Pilih Ulang
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

    {{-- Ringkasan Kartu Metrik --}}
    <div class="row g-3 mb-4">
        <div class="col-md-2 col-4">
            <div class="card shadow-sm border-0 bg-light text-center p-2">
                <div class="small text-muted">Total Baris</div>
                <div class="fs-4 fw-bold">{{ $preview['ringkas']['total'] }}</div>
            </div>
        </div>
        <div class="col-md-2 col-4">
            <div class="card shadow-sm border-0 border-start border-success border-4 p-2 text-center">
                <div class="small text-success fw-bold">Baru</div>
                <div class="fs-4 fw-bold text-success">{{ $preview['ringkas']['baru'] }}</div>
            </div>
        </div>
        <div class="col-md-2 col-4">
            <div class="card shadow-sm border-0 border-start border-primary border-4 p-2 text-center">
                <div class="small text-primary fw-bold">Diperbarui</div>
                <div class="fs-4 fw-bold text-primary">{{ $preview['ringkas']['diperbarui'] }}</div>
            </div>
        </div>
        <div class="col-md-2 col-4">
            <div class="card shadow-sm border-0 border-start border-secondary border-4 p-2 text-center">
                <div class="small text-secondary fw-bold">Dikecualikan</div>
                <div class="fs-4 fw-bold text-secondary">{{ $preview['ringkas']['dikecualikan'] }}</div>
            </div>
        </div>
        <div class="col-md-2 col-4">
            <div class="card shadow-sm border-0 border-start border-danger border-4 p-2 text-center">
                <div class="small text-danger fw-bold">Konflik / Eror</div>
                <div class="fs-4 fw-bold text-danger">{{ $preview['ringkas']['konflik'] }}</div>
            </div>
        </div>
        @if ($mode === 'ganti_keseluruhan')
            <div class="col-md-2 col-4">
                <div class="card shadow-sm border-0 border-start border-warning border-4 p-2 text-center bg-warning-subtle">
                    <div class="small text-warning-emphasis fw-bold">Akan Dinonaktifkan</div>
                    <div class="fs-4 fw-bold text-warning-emphasis">{{ $preview['ringkas']['dinonaktifkan'] }}</div>
                </div>
            </div>
        @endif
    </div>

    {{-- Form Konfirmasi Commit --}}
    <form method="POST" action="{{ route('admin.manajemen-data.impor.commit') }}">
        @csrf
        <input type="hidden" name="temp_path" value="{{ $tempPath }}">
        <input type="hidden" name="kategori" value="{{ $kategori }}">
        <input type="hidden" name="mode" value="{{ $mode }}">
        <input type="hidden" name="fingerprint" value="{{ $preview['fingerprint'] }}">

        {{-- Peringatan Khusus Mode Ganti Keseluruhan --}}
        @if ($mode === 'ganti_keseluruhan')
            <div class="alert alert-warning shadow-sm border-warning mb-4" role="alert">
                <div class="d-flex gap-3 align-items-center">
                    <x-ikon nama="exclamation-triangle" class="fs-2 text-warning flex-shrink-0" />
                    <div>
                        <h6 class="alert-heading fw-bold mb-1">Perhatian Mode Ganti Keseluruhan (Safe Synchronization)</h6>
                        <p class="small mb-2">
                            Sebanyak <strong>{{ $preview['ringkas']['dinonaktifkan'] }} pengguna</strong> yang saat ini aktif di database tidak tercantum dalam berkas ini. Mereka akan <strong>dinonaktifkan (status: nonaktif)</strong>, namun seluruh riwayat presensi, jurnal, dan kelas tetap tersimpan utuh.
                        </p>
                        <div class="input-group input-group-sm" style="max-width: 450px;">
                            <span class="input-group-text bg-white small">Ketik konfirmasi:</span>
                            <input type="text" name="konfirmasi_ganti" class="form-control" placeholder="SAYA_YAKIN_GANTI_KESELURUHAN" required>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Tabel Pratinjau Baris --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-3">
                <h6 class="fw-bold mb-0">Rincian Baris dan Klasifikasi Status</h6>
                <div class="small text-muted">Centang kotak untuk mengecualikan baris secara manual</div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th width="40" class="text-center">Kecualikan</th>
                            <th width="50">Baris</th>
                            <th>Status</th>
                            <th>Identitas</th>
                            <th>Nama Lengkap</th>
                            <th>Email</th>
                            <th>Keterangan / Pesan Validasi</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        @foreach ($preview['baris'] as $b)
                            @php
                                $idKey = $kategori === 'guru' ? 'nip' : 'nis';
                                $hasError = ! empty($b['error']);
                                $bgRow = $hasError ? 'table-danger' : ($b['status_klasifikasi'] === 'baru' ? 'table-success-subtle' : '');
                            @endphp
                            <tr class="{{ $bgRow }}">
                                <td class="text-center">
                                    <input type="checkbox" name="exclusions[]" value="{{ $b['nomor'] }}" {{ $b['dikecualikan'] || $hasError ? 'checked' : '' }}>
                                </td>
                                <td>{{ $b['nomor'] }}</td>
                                <td>
                                    @if ($hasError)
                                        <span class="badge bg-danger">Konflik / Eror</span>
                                    @elseif ($b['status_klasifikasi'] === 'baru')
                                        <span class="badge bg-success">Tambah Baru</span>
                                    @elseif ($b['status_klasifikasi'] === 'diperbarui')
                                        <span class="badge bg-primary">Perbarui</span>
                                    @else
                                        <span class="badge bg-secondary">Tidak Berubah</span>
                                    @endif
                                </td>
                                <td><strong>{{ $b['data'][$idKey] ?? '-' }}</strong></td>
                                <td>{{ $b['data']['nama'] ?? '-' }}</td>
                                <td>{{ $b['data']['email'] ?? '-' }}</td>
                                <td>
                                    @if ($hasError)
                                        <ul class="text-danger ps-3 mb-0 small">
                                            @foreach ($b['error'] as $err)
                                                <li>{{ $err }}</li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <span class="text-muted small">Siap diproses</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-transparent d-flex justify-content-between align-items-center py-3">
                <div class="small text-muted">
                    Baris yang dicentang "Kecualikan" atau memiliki pesan konflik tidak akan dimasukkan ke dalam commit database.
                </div>
                <button type="submit" class="btn btn-primary fw-bold px-4">
                    <x-ikon nama="check-circle" /> Konfirmasi & Commit Impor Data &rarr;
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
