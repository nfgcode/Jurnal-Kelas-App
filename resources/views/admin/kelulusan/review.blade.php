@extends('layouts.app')

@section('title', 'Pratinjau Kelulusan Kelas XII')

@section('content')
<div class="kelulusan-review-view space-y-5">
    <style>
        .kelulusan-review-view .page-head {
            margin-top: 8px !important;
            margin-bottom: 24px !important;
        }
        .kelulusan-review-view .page-head__title {
            font-size: 22px !important;
            font-weight: 700 !important;
            line-height: 1.25 !important;
            color: #111827 !important;
            margin-bottom: 6px !important;
        }
        .kelulusan-review-view .page-head__sub {
            font-size: 14px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
            color: #4b5563 !important;
            margin: 0 !important;
        }
        .kelulusan-review-view .breadcrumb-nav {
            font-size: 13px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
        }
        .kelulusan-review-view .breadcrumb-nav a {
            color: #4b5563 !important;
        }
        .kelulusan-review-view .breadcrumb-nav a:hover {
            color: var(--primary, #3b5d32) !important;
        }
        .kelulusan-review-view .kpi-card__num {
            font-size: 24px !important;
            font-weight: 700 !important;
            line-height: 1.2 !important;
        }
        .kelulusan-review-view .kpi-card__label {
            font-size: 13px !important;
            font-weight: 500 !important;
            line-height: 1.3 !important;
            color: #4b5563 !important;
        }
        .kelulusan-review-view .btn-hifi {
            font-size: 14px !important;
            font-weight: 600 !important;
            line-height: 1.4 !important;
        }
        .kelulusan-review-view .tbl th {
            font-size: 12.5px !important;
            font-weight: 600 !important;
            line-height: 1.4 !important;
            padding: 10px 14px !important;
        }
        .kelulusan-review-view .tbl td {
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
            <a href="{{ route('admin.tahun-ajaran.index') }}" class="text-decoration-none">Tahun Ajaran</a>
            <span class="text-muted">&rsaquo;</span>
            <a href="{{ route('admin.kelulusan.index') }}" class="text-decoration-none">Kelulusan Kelas XII</a>
            <span class="text-muted">&rsaquo;</span>
            <span class="text-dark fw-semibold" aria-current="page">Tinjau Draf #{{ $op->id }}</span>
        </div>
    </nav>

    {{-- Page Header --}}
    <div class="page-head d-flex justify-content-between align-items-start">
        <div>
            <h2 class="page-head__title">Pratinjau Kelulusan Kelas XII</h2>
            <p class="page-head__sub">
                Operasi #{{ $op->id }} &bull; Periode:
                <span class="chip chip--solid-lembut fw-bold" style="font-size: 13px; color: var(--primary, #3b5d32);">{{ $op->source_year }}</span>
                @if ($op->target_year)
                    &rarr; Penempatan: <span class="chip chip--solid-lembut fw-bold" style="font-size: 13px; color: #1e3a8a; background: #e0e7ff;">{{ $op->target_year }}</span>
                @endif
                &bull; Tanggal Efektif: <strong>{{ $op->source_metadata['effective_date'] ?? '-' }}</strong>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.kelulusan.index') }}" class="btn-hifi btn-hifi--ghost">
                <x-ikon nama="arrow-left" /> Kembali
            </a>
            <form method="POST" action="{{ route('admin.kelulusan.validate', $op->id) }}">
                @csrf
                <button type="submit" class="btn-hifi btn-hifi--ghost">
                    <x-ikon nama="arrow-repeat" /> Validasi Ulang
                </button>
            </form>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success d-flex align-items-center gap-2 mb-3" role="alert">
            <x-ikon nama="check-circle" />
            <div>{{ session('status') }}</div>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger mb-3" role="alert">
            <div class="fw-bold mb-1">Perhatian:</div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- 4 Stat Cards Ringkasan Verifikasi --}}
    <div style="display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px;" class="mb-4">
        {{-- Total Roster --}}
        <div class="card-hifi p-3 d-flex align-items-center gap-3">
            <div style="width: 44px; height: 44px; border-radius: 8px; background: rgba(59, 93, 50, 0.12); color: var(--primary, #3b5d32); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 18px;">
                <x-ikon nama="people" />
            </div>
            <div>
                <div class="kpi-card__num text-dark">{{ number_format($summary['total'], 0, ',', '.') }}</div>
                <div class="kpi-card__label">Total Roster XII</div>
            </div>
        </div>

        {{-- Siswa Lulus --}}
        <div class="card-hifi p-3 d-flex align-items-center gap-3">
            <div style="width: 44px; height: 44px; border-radius: 8px; background: rgba(22, 101, 52, 0.12); color: #166534; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 18px;">
                <x-ikon nama="check-circle" />
            </div>
            <div>
                <div class="kpi-card__num" style="color: #166534;">{{ number_format($summary['lulus'], 0, ',', '.') }}</div>
                <div class="kpi-card__label">Siswa Lulus</div>
            </div>
        </div>

        {{-- Tidak Lulus --}}
        <div class="card-hifi p-3 d-flex align-items-center gap-3">
            <div style="width: 44px; height: 44px; border-radius: 8px; background: rgba(180, 83, 9, 0.12); color: #b45309; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 18px;">
                <x-ikon nama="dash-circle" />
            </div>
            <div>
                <div class="kpi-card__num" style="color: #b45309;">{{ number_format($summary['tidak_lulus'], 0, ',', '.') }}</div>
                <div class="kpi-card__label">Tidak Lulus</div>
            </div>
        </div>

        {{-- Masalah / Belum Verifikasi --}}
        <div class="card-hifi p-3 d-flex align-items-center gap-3">
            <div style="width: 44px; height: 44px; border-radius: 8px; background: {{ $summary['masalah'] > 0 ? 'rgba(185, 28, 28, 0.12)' : 'rgba(107, 114, 128, 0.12)' }}; color: {{ $summary['masalah'] > 0 ? '#b91c1c' : '#6b7280' }}; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 18px;">
                <x-ikon nama="exclamation-circle" />
            </div>
            <div>
                <div class="kpi-card__num" style="color: {{ $summary['masalah'] > 0 ? '#b91c1c' : '#374151' }};">{{ number_format($summary['masalah'], 0, ',', '.') }}</div>
                <div class="kpi-card__label">Perlu Tindak Lanjut</div>
            </div>
        </div>
    </div>

    {{-- Banner Status Kesiapan Finalisasi --}}
    @if ($summary['masalah'] > 0 || $preview->validation !== 'valid')
        <div class="card-hifi p-4 border-l-4 mb-4" style="border-left-color: #b45309; background: #fffbeb;">
            <div class="d-flex align-items-start gap-3">
                <div style="font-size: 24px; color: #b45309;">
                    <x-ikon nama="exclamation-triangle" />
                </div>
                <div>
                    <h4 class="fw-bold mb-1" style="font-size: 16.5px; color: #92400e;">Finalisasi Ditahan (Ada {{ $summary['masalah'] }} Siswa Memerlukan Tindak Lanjut)</h4>
                    <p class="mb-0 text-muted" style="font-size: 13.5px; line-height: 1.5;">
                        Finalisasi kelulusan hanya dapat diproses apabila seluruh siswa kelas XII telah terdaftar dengan keputusan valid dan siswa yang tidak lulus telah ditentukan rombel tujuannya. Silakan periksa daftar kelompok di bawah ini.
                    </p>
                </div>
            </div>
        </div>
    @else
        <div class="card-hifi p-4 border-l-4 border-primary bg-primary-soft d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <div style="font-size: 26px; color: var(--primary, #3b5d32);">
                    <x-ikon nama="check-circle" />
                </div>
                <div>
                    <h4 class="fw-bold text-dark mb-1" style="font-size: 16.5px;">Seluruh Roster Siswa Terverifikasi Lengkap</h4>
                    <p class="text-muted small mb-0">Semua keputusan telah valid. Anda dapat mengeksekusi penutupan kelulusan sekarang.</p>
                </div>
            </div>
            <button type="button" class="btn-hifi" data-bs-toggle="modal" data-bs-target="#confirmExecutionModal" style="padding: 10px 24px;">
                <x-ikon nama="check2-all" /> Finalisasi Kelulusan
            </button>
        </div>
    @endif

    {{-- Filter Pencarian Data Siswa --}}
    <div class="card-hifi p-3 mb-4">
        <form method="GET" action="{{ route('admin.kelulusan.review', $op->id) }}" class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center gap-2">
            <div class="flex-grow-1">
                <input type="text" name="q" class="select-hifi w-100" placeholder="Cari NIS atau Nama Siswa..." value="{{ $q }}">
            </div>
            <div style="width: 260px;">
                <select name="status" class="select-hifi w-100" onchange="this.form.submit()">
                    <option value="semua" @selected($filterStatus === 'semua')>Semua Status ({{ $summary['total'] }})</option>
                    <option value="masalah" @selected($filterStatus === 'masalah')>Perlu Tindak Lanjut ({{ $summary['masalah'] }})</option>
                    <option value="tidak_lulus" @selected($filterStatus === 'tidak_lulus')>Tidak Lulus ({{ $summary['tidak_lulus'] }})</option>
                    <option value="lulus" @selected($filterStatus === 'lulus')>Lulus ({{ $summary['lulus'] }})</option>
                </select>
            </div>
            <button type="submit" class="btn-hifi btn-hifi--ghost">Cari</button>
        </form>
    </div>

    {{-- TABEL PENGELOMPOKAN --}}

    {{-- 1. Kelompok Masalah & Belum Terverifikasi --}}
    @if (!empty($grouped['masalah']))
        <x-card title="1. Siswa Bermasalah & Belum Terverifikasi ({{ count($grouped['masalah']) }})" flush class="mb-4 border-l-4" style="border-left-color: #b91c1c;">
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th style="width: 140px;">NIS</th>
                            <th>Nama Siswa</th>
                            <th style="width: 140px;">Keputusan</th>
                            <th>Keterangan Masalah</th>
                            <th class="is-num" style="width: 140px;">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($grouped['masalah'] as $item)
                            <tr>
                                <td class="is-strong font-monospace">{{ $item['student_ref'] }}</td>
                                <td><strong>{{ $item['nama'] }}</strong></td>
                                <td>
                                    <span class="chip chip--neutral">{{ ucfirst($item['decision']) }}</span>
                                </td>
                                <td>
                                    @foreach ($item['errors'] as $err)
                                        <span class="chip chip--neutral" style="color: #b91c1c; background: #fee2e2;">{{ $err }}</span>
                                    @endforeach
                                    @if ($item['validation'] === 'unverified' && empty($item['errors']))
                                        <span class="chip chip--neutral" style="color: #b45309; background: #fef3c7;">Belum Terverifikasi</span>
                                    @endif
                                </td>
                                <td class="is-num tbl__aksi">
                                    <button type="button" class="btn-hifi btn-hifi--sm" data-bs-toggle="modal" data-bs-target="#editItemModal{{ $item['id'] }}">
                                        <x-ikon nama="pencil" /> Perbaiki
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

    {{-- 2. Kelompok Siswa Tidak Lulus --}}
    @if (!empty($grouped['tidak_lulus']))
        <x-card title="2. Siswa Tidak Lulus ({{ count($grouped['tidak_lulus']) }})" flush class="mb-4 border-l-4" style="border-left-color: #b45309;">
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th style="width: 140px;">NIS</th>
                            <th>Nama Siswa</th>
                            <th style="width: 140px;">Keputusan</th>
                            <th>Kelas Penempatan Ulang (Tingkat XII)</th>
                            <th class="is-num" style="width: 160px;">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($grouped['tidak_lulus'] as $item)
                            <tr>
                                <td class="is-strong font-monospace">{{ $item['student_ref'] }}</td>
                                <td><strong>{{ $item['nama'] }}</strong></td>
                                <td>
                                    <span class="chip chip--neutral" style="color: #b45309; background: #fef3c7;">Tidak Lulus</span>
                                </td>
                                <td>
                                    @php
                                        $cObj = collect($targetClasses)->firstWhere('id', $item['target_class_id']);
                                    @endphp
                                    @if ($cObj)
                                        <span class="chip chip--solid-lembut">{{ $cObj->nama_kelas }} ({{ $cObj->tingkat }})</span>
                                    @else
                                        <span class="text-danger fw-bold small">Belum Ditentukan</span>
                                    @endif
                                </td>
                                <td class="is-num tbl__aksi">
                                    <button type="button" class="btn-hifi btn-hifi--sm btn-hifi--ghost" data-bs-toggle="modal" data-bs-target="#editItemModal{{ $item['id'] }}">
                                        <x-ikon nama="pencil" /> Atur Rombel
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

    {{-- 3. Kelompok Siswa Lulus --}}
    @if (!empty($grouped['lulus']))
        <x-card title="3. Siswa Lulus ({{ count($grouped['lulus']) }})" flush class="mb-4 border-l-4 border-primary">
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th style="width: 140px;">NIS</th>
                            <th>Nama Siswa</th>
                            <th style="width: 140px;">Keputusan</th>
                            <th>Status Kelulusan</th>
                            <th class="is-num" style="width: 140px;">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($grouped['lulus'] as $item)
                            <tr>
                                <td class="is-strong font-monospace">{{ $item['student_ref'] }}</td>
                                <td><strong>{{ $item['nama'] }}</strong></td>
                                <td><span class="chip chip--green">Lulus</span></td>
                                <td class="text-muted small">Tercatat sebagai Alumni resmi sekolah</td>
                                <td class="is-num tbl__aksi">
                                    <button type="button" class="btn-hifi btn-hifi--sm btn-hifi--ghost" data-bs-toggle="modal" data-bs-target="#editItemModal{{ $item['id'] }}">
                                        <x-ikon nama="pencil" /> Ubah
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif
</div>

{{-- Modals untuk Edit Item Siswa --}}
@foreach ($items as $item)
    <div class="modal fade" id="editItemModal{{ $item['id'] }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content card-hifi p-4">
                <form method="POST" action="{{ route('admin.kelulusan.update-item', [$op->id, $item['id']]) }}">
                    @csrf
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="card-hifi__title mb-0" style="font-size: 17px !important;">Tinjau Keputusan: {{ $item['nama'] }} ({{ $item['student_ref'] }})</h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>

                    <div class="mb-3">
                        <div class="field mb-3">
                            <label class="field__label" for="decisionSelect{{ $item['id'] }}">Keputusan Kelulusan</label>
                            <select name="decision" class="select-hifi w-100" id="decisionSelect{{ $item['id'] }}" onchange="document.getElementById('targetClassDiv{{ $item['id'] }}').style.display = (this.value === 'tidak_lulus' ? 'block' : 'none');">
                                <option value="lulus" @selected($item['decision'] === 'lulus')>LULUS</option>
                                <option value="tidak_lulus" @selected($item['decision'] === 'tidak_lulus')>TIDAK LULUS (Tinggal di Kelas XII)</option>
                                <option value="unverified" @selected($item['decision'] === 'unverified')>BELUM TERVERIFIKASI</option>
                            </select>
                        </div>

                        <div class="field mb-3" id="targetClassDiv{{ $item['id'] }}" style="{{ $item['decision'] === 'tidak_lulus' ? 'display:block;' : 'display:none;' }}">
                            <label class="field__label">Pilih Rombel Kelas XII Penempatan Ulang</label>
                            <select name="target_class_id" class="select-hifi w-100">
                                <option value="">-- Pilih Kelas XII Tujuan --</option>
                                @foreach ($targetClasses as $tc)
                                    <option value="{{ $tc->id }}" @selected((string)$tc->id === (string)$item['target_class_id'])>
                                        {{ $tc->nama_kelas }} ({{ $tc->jurusan_kode }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="field mb-3">
                            <label class="field__label">Alasan Penyesuaian<span class="field__req"> *</span></label>
                            <input type="text" name="alasan" class="select-hifi w-100" placeholder="Tuliskan catatan atau alasan perubahan..." required>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <button type="button" class="btn-hifi btn-hifi--ghost" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn-hifi">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach

{{-- Modal Konfirmasi Eksekusi Final --}}
<div class="modal fade" id="confirmExecutionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card-hifi p-4">
            <form method="POST" action="{{ route('admin.kelulusan.execute', $op->id) }}">
                @csrf
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="card-hifi__title mb-0" style="font-size: 18px !important;">Konfirmasi Finalisasi Kelulusan</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="mb-3">
                    <p class="field__hint mb-3" style="font-size: 13.5px; color: #374151;">
                        Anda akan memfinalisasi kelulusan untuk <strong>{{ $summary['total'] }}</strong> siswa tingkat akhir Tahun Ajaran <strong>{{ $op->source_year }}</strong>:
                    </p>
                    <ul class="text-muted small ps-3 mb-3" style="line-height: 1.6;">
                        <li><strong>{{ $summary['lulus'] }}</strong> siswa LULUS akan diterbitkan status resmi Alumni.</li>
                        @if ($summary['tidak_lulus'] > 0)
                            <li><strong>{{ $summary['tidak_lulus'] }}</strong> siswa TIDAK LULUS akan dialokasikan ke kelas XII tahun {{ $op->target_year }}.</li>
                        @endif
                        <li>Proses ini dieksekusi secara atomik dan snapshot audit log disimpan otomatis.</li>
                    </ul>
                </div>
                <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                    <button type="button" class="btn-hifi btn-hifi--ghost" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn-hifi">
                        <x-ikon nama="check-lg" /> Ya, Eksekusi Finalisasi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
