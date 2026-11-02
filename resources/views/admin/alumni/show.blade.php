@extends('layouts.app')

@section('title', 'Detail Alumni · ' . $siswa->nama)

@section('content')
<div class="alumni-show-view space-y-5">
    <style>
        .alumni-show-view .page-head {
            margin-top: 8px !important;
            margin-bottom: 24px !important;
        }
        .alumni-show-view .page-head__title {
            font-size: 22px !important;
            font-weight: 700 !important;
            line-height: 1.25 !important;
            color: #111827 !important;
            margin-bottom: 6px !important;
        }
        .alumni-show-view .page-head__sub {
            font-size: 14px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
            color: #4b5563 !important;
            margin: 0 !important;
        }
        .alumni-show-view .breadcrumb-nav {
            font-size: 13px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
        }
        .alumni-show-view .breadcrumb-nav a {
            color: #4b5563 !important;
        }
        .alumni-show-view .breadcrumb-nav a:hover {
            color: var(--primary, #3b5d32) !important;
        }
        .alumni-show-view .btn-hifi {
            font-size: 14px !important;
            font-weight: 600 !important;
            line-height: 1.4 !important;
        }
        .alumni-show-view .tbl th {
            font-size: 12.5px !important;
            font-weight: 600 !important;
            line-height: 1.4 !important;
            padding: 10px 14px !important;
        }
        .alumni-show-view .tbl td {
            font-size: 13.5px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
            padding: 10px 14px !important;
        }
        .bio-list {
            display: flex;
            flex-direction: column;
        }
        .bio-row {
            display: flex;
            align-items: center;
            padding: 13px 0;
            border-bottom: 1px dashed #e5e7eb;
            font-size: 14px;
        }
        .bio-row:last-child {
            border-bottom: none;
            padding-bottom: 4px;
        }
        .bio-row:first-child {
            padding-top: 4px;
        }
        .bio-label {
            width: 38%;
            color: #6b7280;
            font-weight: 500;
            flex-shrink: 0;
        }
        .bio-val {
            width: 62%;
            color: #111827;
            font-weight: 500;
        }
    </style>

    {{-- Breadcrumb Navigasi --}}
    <nav class="breadcrumb-nav mb-3" aria-label="Breadcrumb">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Dashboard</a>
            <span class="text-muted">&rsaquo;</span>
            <a href="{{ route('admin.alumni.index') }}" class="text-decoration-none">Data Alumni</a>
            <span class="text-muted">&rsaquo;</span>
            <span class="text-dark fw-semibold" aria-current="page">{{ $siswa->nama }}</span>
        </div>
    </nav>

    {{-- Bento Hero Card Header --}}
    <div class="card-hifi p-4 mb-4 border-l-4" style="border-left-color: var(--primary, #3b5d32);">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div class="pe-md-2">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <h2 class="page-head__title mb-0" style="font-size: 21px !important;">{{ $siswa->nama }}</h2>
                    <x-chip :tone="$evaluasi['tone_siklus_hidup']" :label="$evaluasi['label_siklus_hidup']" />
                </div>
                <div class="page-head__sub d-flex flex-wrap align-items-center gap-2">
                    <span>NIS: <strong>{{ $siswa->nis }}</strong></span>
                    @if ($siswa->nisn)
                        <span class="text-muted">&bull;</span>
                        <span>NISN: <strong>{{ $siswa->nisn }}</strong></span>
                    @endif
                    <span class="text-muted">&bull;</span>
                    <span>Alumni Angkatan:</span>
                    <span class="chip chip--solid-lembut fw-bold" style="font-size: 13px; color: var(--primary, #3b5d32);">{{ $alumni->tahun_lulus }}</span>
                </div>
            </div>
            <div class="d-flex gap-2 flex-shrink-0 pe-md-1">
                <a href="{{ route('admin.alumni.index') }}" class="btn-hifi btn-hifi--ghost">
                    <x-ikon nama="arrow-left" /> Kembali ke Daftar Alumni
                </a>
            </div>
        </div>
    </div>

    {{-- Alert Kelayakan Penghapusan & Keamanan Data Bento --}}
    @if ($evaluasi['memiliki_dependensi'])
        <div class="card-hifi p-4 mb-4 border-l-4" style="border-left-color: #b91c1c; background: #fff5f5;">
            <div class="d-flex align-items-start gap-3">
                <div style="font-size: 24px; color: #b91c1c;">
                    <x-ikon nama="shield-lock" />
                </div>
                <div>
                    <h4 class="fw-bold text-danger mb-1" style="font-size: 16px;">DATA HISTORIS DILINDUNGI (RESTRICT)</h4>
                    <p class="mb-2 text-muted" style="font-size: 13.5px; line-height: 1.5;">
                        Data alumni ini memiliki rekam jejak pendidikan historis yang dilindungi. Penghapusan fisik dinonaktifkan demi menjaga keutuhan absensi kelas, jurnal pembelajaran guru, dan arsip legal sekolah.
                    </p>
                    <ul class="mb-0 ps-3 text-muted small" style="line-height: 1.6;">
                        @foreach ($evaluasi['alasan_pemblokiran'] as $alasan)
                            <li>{{ $alasan }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @else
        <div class="card-hifi p-4 mb-4 border-l-4 border-primary bg-primary-soft">
            <div class="d-flex align-items-start gap-3">
                <div style="font-size: 24px; color: var(--primary, #3b5d32);">
                    <x-ikon nama="shield-check" />
                </div>
                <div>
                    <h4 class="fw-bold text-dark mb-1" style="font-size: 16px;">Status Kebijakan Retensi</h4>
                    <p class="mb-0 text-muted" style="font-size: 13.5px;">{{ $evaluasi['status_kelayakan'] }}</p>
                </div>
            </div>
        </div>
    @endif

    {{-- Kartu Utama: Biodata Siswa & Kelulusan (Gaya List Bersih Bergaris Putus-Putus Sesuai Referensi) --}}
    <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px;" class="mb-4">
        {{-- Kartu 1: Biodata & Informasi Pribadi --}}
        <div>
            <x-card title="Biodata Siswa" class="h-100 border-l-4" style="border-left-color: var(--primary, #3b5d32);">
                <div class="bio-list">
                    <div class="bio-row">
                        <span class="bio-label">Nama Lengkap</span>
                        <span class="bio-val fw-bold text-dark">{{ $siswa->nama }}</span>
                    </div>
                    <div class="bio-row">
                        <span class="bio-label">Nomor Induk Siswa (NIS)</span>
                        <span class="bio-val font-monospace fw-bold">{{ $siswa->nis }}</span>
                    </div>
                    <div class="bio-row">
                        <span class="bio-label">NISN</span>
                        <span class="bio-val font-monospace">{{ $siswa->nisn ?? '—' }}</span>
                    </div>
                    <div class="bio-row">
                        <span class="bio-label">Jenis Kelamin</span>
                        <span class="bio-val">{{ $siswa->jenis_kelamin === 'L' ? 'Laki-laki' : ($siswa->jenis_kelamin === 'P' ? 'Perempuan' : '—') }}</span>
                    </div>
                    <div class="bio-row">
                        <span class="bio-label">Nomor Telepon / WA</span>
                        <span class="bio-val">{{ $siswa->no_hp ?? '—' }}</span>
                    </div>
                    <div class="bio-row">
                        <span class="bio-label">Alamat Tinggal</span>
                        <span class="bio-val text-muted">{{ $siswa->alamat ?? '—' }}</span>
                    </div>
                </div>
            </x-card>
        </div>

        {{-- Kartu 2: Catatan Status Kelulusan Sekolah --}}
        <div>
            <x-card title="Status Kelulusan Sekolah" class="h-100 border-l-4" style="border-left-color: #166534;">
                <div class="bio-list">
                    <div class="bio-row">
                        <span class="bio-label">Tahun Angkatan</span>
                        <span class="bio-val">
                            <span class="chip chip--solid-lembut fw-bold" style="font-size: 13px; color: var(--primary, #3b5d32);">{{ $alumni->tahun_lulus }}</span>
                        </span>
                    </div>
                    <div class="bio-row">
                        <span class="bio-label">Tanggal Kelulusan</span>
                        <span class="bio-val fw-semibold">{{ $alumni->tanggal_lulus ? $alumni->tanggal_lulus->format('d F Y') : '—' }}</span>
                    </div>
                    <div class="bio-row">
                        <span class="bio-label">Rombel Kelas Terakhir</span>
                        <span class="bio-val"><span class="chip chip--neutral">{{ $alumni->kelasTerakhir?->nama_kelas ?? '—' }}</span></span>
                    </div>
                    <div class="bio-row">
                        <span class="bio-label">Kompetensi Keahlian</span>
                        <span class="bio-val fw-semibold">{{ $alumni->kelasTerakhir?->jurusan?->nama ?? $alumni->kelasTerakhir?->jurusan_kode ?? '—' }}</span>
                    </div>
                    <div class="bio-row">
                        <span class="bio-label">Status Siswa</span>
                        <span class="bio-val">
                            <span class="chip chip--green">Alumni Resmi Terdaftar</span>
                        </span>
                    </div>
                    <div class="bio-row">
                        <span class="bio-label">Predikat / Keterangan</span>
                        <span class="bio-val text-muted">{{ $alumni->keterangan ?? 'Lulus Reguler' }}</span>
                    </div>
                </div>
            </x-card>
        </div>
    </div>

    {{-- Card 2: Riwayat Akademik (Garis Waktu Kelas X, XI, XII) --}}
    <x-card title="Riwayat Akademik" class="mb-4" flush>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th style="width: 140px;">Tahun Ajaran</th>
                        <th style="width: 120px;">Tingkat</th>
                        <th>Rombel Kelas</th>
                        <th style="width: 140px;">Status</th>
                        <th style="width: 140px;">Keanggotaan</th>
                        <th>Periode</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($riwayatKelas as $rk)
                        <tr>
                            <td class="is-strong">{{ $rk->tahun_ajaran_kode }}</td>
                            <td><span class="chip chip--neutral">Tingkat {{ $rk->tingkat ?? '—' }}</span></td>
                            <td class="fw-semibold">{{ $rk->kelas?->nama_kelas ?? '—' }}</td>
                            <td>
                                <x-chip :tone="$rk->status === 'lulus' ? 'green' : ($rk->status === 'aktif' ? 'green' : 'neutral')" :label="ucfirst($rk->status)" />
                            </td>
                            <td>
                                <x-chip :tone="$rk->aktif ? 'green' : 'neutral'" :label="$rk->aktif ? 'Aktif' : 'Selesai'" />
                            </td>
                            <td class="is-muted is-nowrap small">
                                {{ $rk->tanggal_mulai ? $rk->tanggal_mulai->format('d/m/Y') : 'Awal tahun' }} s/d {{ $rk->tanggal_selesai ? $rk->tanggal_selesai->format('d/m/Y') : 'Selesai' }}
                            </td>
                            <td class="is-muted small">{{ $rk->catatan ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-state text-center py-4 text-muted">Belum ada catatan riwayat akademik untuk siswa ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    {{-- Card 3: Akun Portal & Kebijakan Arsip Sekolah (Di Bawah) --}}
    <x-card title="Akun Portal Siswa & Status Arsip Data" class="mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 p-2">
            <div class="d-flex align-items-center gap-3">
                <div style="width: 44px; height: 44px; border-radius: 8px; background: rgba(30, 58, 138, 0.1); color: #1e3a8a; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0;">
                    <x-ikon nama="shield-check" />
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="fw-bold text-dark" style="font-size: 15px;">Akun Portal Siswa:</span>
                        @if ($siswa->akun)
                            <x-chip :tone="$siswa->akun->status === 'aktif' ? 'green' : 'neutral'" :label="ucfirst($siswa->akun->status)" />
                            <span class="text-muted small">&bull; Username: <strong>{{ $siswa->akun->username }}</strong></span>
                        @else
                            <span class="text-muted small">Belum memiliki akun login</span>
                        @endif
                    </div>
                    <p class="text-muted small mb-0">
                        Durasi kelulusan: <strong>{{ $evaluasi['usia_inaktif_teks'] }}</strong> &bull; Seluruh data presensi dan nilai tetap terlindungi secara permanen di basis data sekolah.
                    </p>
                </div>
            </div>

            @if ($siswa->akun)
                <form method="POST" action="{{ route('admin.alumni.akun', $alumni->id ?? $alumni) }}" class="d-inline"
                      onsubmit="return confirm('Ubah status akun login alumni menjadi {{ $siswa->akun->status === 'aktif' ? 'nonaktif' : 'aktif' }}?');">
                    @csrf
                    <button type="submit" class="btn-hifi {{ $siswa->akun->status === 'aktif' ? 'btn-hifi--ghost' : '' }}" style="padding: 9px 20px;">
                        <x-ikon nama="{{ $siswa->akun->status === 'aktif' ? 'lock' : 'unlock' }}" />
                        {{ $siswa->akun->status === 'aktif' ? 'Nonaktifkan Akun' : 'Aktifkan Akun' }}
                    </button>
                </form>
            @endif
        </div>
    </x-card>
</div>
@endsection
