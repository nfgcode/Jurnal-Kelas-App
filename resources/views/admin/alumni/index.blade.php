@extends('layouts.app')

@section('title', 'Data Alumni')

@section('content')
<div class="alumni-index-view space-y-5">
    <style>
        .alumni-index-view .page-head {
            margin-top: 8px !important;
            margin-bottom: 24px !important;
        }
        .alumni-index-view .page-head__title {
            font-size: 22px !important;
            font-weight: 700 !important;
            line-height: 1.25 !important;
            color: #111827 !important;
            margin-bottom: 6px !important;
        }
        .alumni-index-view .page-head__sub {
            font-size: 14px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
            color: #4b5563 !important;
            margin: 0 !important;
        }
        .alumni-index-view .breadcrumb-nav {
            font-size: 13px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
        }
        .alumni-index-view .breadcrumb-nav a {
            color: #4b5563 !important;
        }
        .alumni-index-view .breadcrumb-nav a:hover {
            color: var(--primary, #3b5d32) !important;
        }
        .alumni-index-view .kpi-card__num {
            font-size: 24px !important;
            font-weight: 700 !important;
            line-height: 1.2 !important;
        }
        .alumni-index-view .kpi-card__label {
            font-size: 13px !important;
            font-weight: 500 !important;
            line-height: 1.3 !important;
            color: #4b5563 !important;
        }
        .alumni-index-view .btn-hifi {
            font-size: 14px !important;
            font-weight: 600 !important;
            line-height: 1.4 !important;
        }
        .alumni-index-view .tbl th {
            font-size: 12.5px !important;
            font-weight: 600 !important;
            line-height: 1.4 !important;
            padding: 10px 14px !important;
        }
        .alumni-index-view .tbl td {
            font-size: 13.5px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
            padding: 10px 14px !important;
        }
    </style>

    {{-- Breadcrumb Navigasi --}}
    <nav class="breadcrumb-nav mb-3" aria-label="Breadcrumb">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Dashboard</a>
            <span class="text-muted">&rsaquo;</span>
            <a href="{{ route('admin.transisi.index') }}" class="text-decoration-none">Transisi Akademik</a>
            <span class="text-muted">&rsaquo;</span>
            <span class="text-dark fw-semibold" aria-current="page">Data Alumni</span>
        </div>
    </nav>

    {{-- Bento Hero Card Header --}}
    <div class="card-hifi p-4 mb-4 border-l-4" style="border-left-color: var(--primary, #3b5d32);">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <h2 class="page-head__title mb-0" style="font-size: 21px !important;">Buku Induk Data Alumni</h2>
                    <span class="chip chip--green">{{ number_format($statistik['total'], 0, ',', '.') }} Terdaftar</span>
                </div>
                <p class="page-head__sub">
                    Pusat rekam jejak kelulusan siswa, status akun, dan peninjauan kebijakan retensi data sekolah.
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.transisi.index') }}" class="btn-hifi btn-hifi--ghost">
                    <x-ikon nama="arrow-left" /> Menu Transisi
                </a>
            </div>
        </div>
    </div>

    {{-- Bento Stat Cards: Total, Alumni Terbaru, Tahun Ajaran Terbaru --}}
    <div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px;" class="mb-4">
        {{-- Total Alumni --}}
        <div class="card-hifi p-3 d-flex align-items-center gap-3">
            <div style="width: 48px; height: 48px; border-radius: 8px; background: rgba(59, 93, 50, 0.12); color: var(--primary, #3b5d32); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 20px;">
                <x-ikon nama="people" />
            </div>
            <div>
                <div class="kpi-card__num text-dark">{{ number_format($statistik['total'], 0, ',', '.') }}</div>
                <div class="kpi-card__label">Total Alumni Terdaftar</div>
            </div>
        </div>

        {{-- Jumlah Alumni Terbaru --}}
        <div class="card-hifi p-3 d-flex align-items-center gap-3">
            <div style="width: 48px; height: 48px; border-radius: 8px; background: rgba(22, 101, 52, 0.12); color: #166534; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 20px;">
                <x-ikon nama="mortarboard" />
            </div>
            <div>
                <div class="kpi-card__num" style="color: #166534;">{{ number_format($statistik['alumni_terbaru_count'] ?? 0, 0, ',', '.') }}</div>
                <div class="kpi-card__label">Alumni Angkatan Terbaru ({{ $statistik['tahun_lulus_terbaru'] ?? '—' }})</div>
            </div>
        </div>

        {{-- Tahun Ajaran Terbaru / Aktif --}}
        <div class="card-hifi p-3 d-flex align-items-center gap-3">
            <div style="width: 48px; height: 48px; border-radius: 8px; background: rgba(30, 58, 138, 0.1); color: #1e3a8a; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 20px;">
                <x-ikon nama="calendar3" />
            </div>
            <div>
                <div class="kpi-card__num" style="color: #1e3a8a;">{{ $statistik['tahun_ajaran_aktif'] ?? '—' }}</div>
                <div class="kpi-card__label">Tahun Ajaran Terbaru</div>
            </div>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="card-hifi p-3 mb-4">
        <form class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center gap-2" method="GET">
            <x-query-hidden />

            <div class="flex-grow-1">
                <input class="select-hifi w-100" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                       placeholder="Cari nama atau NIS alumni...">
            </div>

            <div style="width: 220px;">
                <select class="select-hifi w-100" name="tahun_lulus" onchange="this.form.submit()">
                    <option value="">Semua Tahun Lulus</option>
                    @foreach ($tahunLulusList as $th)
                        <option value="{{ $th }}" @selected(($filters['tahun_lulus'] ?? null) === $th)>
                            Tahun Lulus {{ $th }}
                        </option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn-hifi btn-hifi--ghost">Cari</button>
        </form>
    </div>

    {{-- Tabel Alumni --}}
    <x-card title="Daftar Alumni & Evaluasi Retensi" flush>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th style="width: 140px;">NIS</th>
                        <th>Nama Siswa</th>
                        <th style="width: 150px;">Rombel Terakhir</th>
                        <th style="width: 150px;">Tahun Lulus</th>
                        <th style="width: 150px;">Tanggal Lulus</th>
                        <th style="width: 180px;">Siklus Hidup</th>
                        <th style="width: 140px;">Akun Login</th>
                        <th class="is-num" style="width: 160px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($alumni as $item)
                        @php
                            $eval = $item->evaluasi;
                            $siswa = $item->siswa;
                            $akun = $siswa?->akun;
                        @endphp
                        <tr>
                            <td class="is-strong font-monospace">{{ $item->siswa_nis }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="avatar avatar--xs">{{ method_exists($siswa, 'inisial') ? $siswa->inisial() : strtoupper(substr($siswa?->nama ?? 'A', 0, 2)) }}</span>
                                    <strong>{{ $siswa?->nama ?? '—' }}</strong>
                                </div>
                            </td>
                            <td><span class="chip chip--neutral">{{ $item->kelasTerakhir?->nama_kelas ?? '—' }}</span></td>
                            <td><span class="chip chip--solid-lembut fw-bold" style="font-size: 13px; color: var(--primary, #3b5d32);">{{ $item->tahun_lulus }}</span></td>
                            <td class="text-muted">{{ $item->tanggal_lulus ? $item->tanggal_lulus->format('d/m/Y') : '—' }}</td>
                            <td>
                                <x-chip :tone="$eval['tone_siklus_hidup']" :label="$eval['label_siklus_hidup']" />
                                <span class="d-block small text-muted mt-1">Inaktif: {{ $eval['usia_inaktif_teks'] }}</span>
                            </td>
                            <td>
                                @if ($akun)
                                    <x-chip :tone="$akun->status === 'aktif' ? 'green' : 'neutral'" :label="ucfirst($akun->status)" />
                                @else
                                    <span class="text-muted small">Belum ada</span>
                                @endif
                            </td>
                            <td class="is-num tbl__aksi">
                                <a class="btn-hifi btn-hifi--ghost btn-hifi--sm" href="{{ route('admin.alumni.show', $item->id ?? $item) }}">
                                    Detail &amp; Tinjauan
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-state text-center py-4 text-muted">
                                Belum ada data alumni yang terdaftar. Data alumni akan terisi otomatis saat proses kelulusan tingkat XII dijalankan pada menu Transisi Akademik.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($alumni->hasPages())
            <x-slot:foot>
                <span>
                    Menampilkan {{ $alumni->firstItem() ?? 0 }}–{{ $alumni->lastItem() ?? 0 }}
                    dari {{ number_format($alumni->total(), 0, ',', '.') }} alumni
                </span>
                <x-pager :paginator="$alumni" />
            </x-slot:foot>
        @endif
    </x-card>
</div>
@endsection
