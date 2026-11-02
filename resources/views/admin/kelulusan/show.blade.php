@extends('layouts.app')

@section('title', 'Hasil Kelulusan Kelas XII')

@section('content')
<div class="kelulusan-show-view space-y-5">
    <style>
        .kelulusan-show-view .page-head {
            margin-top: 8px !important;
            margin-bottom: 24px !important;
        }
        .kelulusan-show-view .page-head__title {
            font-size: 22px !important;
            font-weight: 700 !important;
            line-height: 1.25 !important;
            color: #111827 !important;
            margin-bottom: 6px !important;
        }
        .kelulusan-show-view .page-head__sub {
            font-size: 14px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
            color: #4b5563 !important;
            margin: 0 !important;
        }
        .kelulusan-show-view .breadcrumb-nav {
            font-size: 13px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
        }
        .kelulusan-show-view .breadcrumb-nav a {
            color: #4b5563 !important;
        }
        .kelulusan-show-view .breadcrumb-nav a:hover {
            color: var(--primary, #3b5d32) !important;
        }
        .kelulusan-show-view .kpi-card__num {
            font-size: 24px !important;
            font-weight: 700 !important;
            line-height: 1.2 !important;
        }
        .kelulusan-show-view .kpi-card__label {
            font-size: 13px !important;
            font-weight: 500 !important;
            line-height: 1.3 !important;
            color: #4b5563 !important;
        }
        .kelulusan-show-view .btn-hifi {
            font-size: 14px !important;
            font-weight: 600 !important;
            line-height: 1.4 !important;
        }
        .kelulusan-show-view .tbl th {
            font-size: 12.5px !important;
            font-weight: 600 !important;
            line-height: 1.4 !important;
            padding: 10px 14px !important;
        }
        .kelulusan-show-view .tbl td {
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
            <span class="text-dark fw-semibold" aria-current="page">Hasil Eksekusi #{{ $op->id }}</span>
        </div>
    </nav>

    {{-- Bento Hero Card: Hasil Kelulusan & Aksi Navigasi --}}
    <div class="card-hifi p-4 mb-4 border-l-4" style="border-left-color: var(--primary, #3b5d32);">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <h2 class="page-head__title mb-0" style="font-size: 21px !important;">Hasil Kelulusan Kelas XII</h2>
                    <span class="chip chip--green">Eksekusi Selesai</span>
                </div>
                <div class="page-head__sub d-flex flex-wrap align-items-center gap-2">
                    <span>Operasi #{{ $op->id }}</span>
                    <span class="text-muted">&bull;</span>
                    <span>Periode:</span>
                    <span class="chip chip--solid-lembut fw-bold" style="font-size: 13px; color: var(--primary, #3b5d32);">{{ $op->source_year }}</span>
                    @if ($op->target_year)
                        <span>&rarr;</span>
                        <span class="chip chip--solid-lembut fw-bold" style="font-size: 13px; color: #1e3a8a; background: #e0e7ff;">{{ $op->target_year }}</span>
                    @endif
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @php
                    $attachedCoordId = \Illuminate\Support\Facades\DB::table('academic_workflow_stages')
                        ->where('operation_id', $op->id)
                        ->value('coordinator_id');
                @endphp
                @if ($attachedCoordId)
                    <a href="{{ route('admin.transisi-terpadu.show', $attachedCoordId) }}" class="btn-hifi btn-hifi--ghost">
                        <x-ikon nama="diagram-3" /> Transisi Terpadu
                    </a>
                @endif
                <a href="{{ route('admin.transisi.index') }}" class="btn-hifi btn-hifi--ghost">
                    <x-ikon nama="arrow-left" /> Kembali ke Awal
                </a>
                <a href="{{ route('admin.alumni.index') }}" class="btn-hifi">
                    <x-ikon nama="people" /> Buka Data Alumni
                </a>
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="card-hifi p-3 mb-4 border-l-4 border-primary bg-primary-soft d-flex align-items-center gap-2">
            <x-ikon nama="check-circle" class="text-primary" />
            <div class="text-dark fw-medium small">{{ session('status') }}</div>
        </div>
    @endif

    {{-- Ringkasan Metrik Bento --}}
    <div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px;" class="mb-4">
        {{-- Total Roster Diproses --}}
        <div class="card-hifi p-3 d-flex align-items-center gap-3">
            <div style="width: 48px; height: 48px; border-radius: 8px; background: rgba(59, 93, 50, 0.12); color: var(--primary, #3b5d32); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 20px;">
                <x-ikon nama="people" />
            </div>
            <div>
                <div class="kpi-card__num text-dark">{{ number_format($lulusItems->count() + $tidakLulusItems->count(), 0, ',', '.') }}</div>
                <div class="kpi-card__label">Total Roster Diproses</div>
            </div>
        </div>

        {{-- Total Lulus --}}
        <div class="card-hifi p-3 d-flex align-items-center gap-3">
            <div style="width: 48px; height: 48px; border-radius: 8px; background: rgba(22, 101, 52, 0.12); color: #166534; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 20px;">
                <x-ikon nama="check-circle" />
            </div>
            <div>
                <div class="kpi-card__num" style="color: #166534;">{{ number_format($lulusItems->count(), 0, ',', '.') }}</div>
                <div class="kpi-card__label">Total Siswa Lulus (Alumni)</div>
            </div>
        </div>

        {{-- Tidak Lulus --}}
        <div class="card-hifi p-3 d-flex align-items-center gap-3">
            <div style="width: 48px; height: 48px; border-radius: 8px; background: rgba(180, 83, 9, 0.12); color: #b45309; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 20px;">
                <x-ikon nama="dash-circle" />
            </div>
            <div>
                <div class="kpi-card__num" style="color: #b45309;">{{ number_format($tidakLulusItems->count(), 0, ',', '.') }}</div>
                <div class="kpi-card__label">Mengulang Kelas XII</div>
            </div>
        </div>
    </div>

    {{-- Daftar Alumni Baru --}}
    <x-card title="Daftar Alumni Kelulusan Tahun {{ $op->source_year }} ({{ $alumni->count() }})" flush class="mb-4">
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th style="width: 140px;">NIS</th>
                        <th>Nama Siswa</th>
                        <th style="width: 150px;">Kelas Terakhir</th>
                        <th style="width: 160px;">Tanggal Kelulusan</th>
                        <th>Keterangan Dokumen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($alumni as $alm)
                        <tr>
                            <td class="is-strong font-monospace">{{ $alm->siswa_nis }}</td>
                            <td><strong>{{ $alm->siswa->nama ?? '-' }}</strong></td>
                            <td><span class="chip chip--neutral">{{ $alm->kelasTerakhir->nama_kelas ?? '-' }}</span></td>
                            <td class="text-muted">{{ $alm->tanggal_lulus ? $alm->tanggal_lulus->format('d M Y') : '-' }}</td>
                            <td class="text-muted small">{{ $alm->keterangan ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-state text-center py-4 text-muted">
                                Tidak ada data siswa lulus.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    {{-- Daftar Siswa Tidak Lulus --}}
    @if ($tidakLulusItems->isNotEmpty())
        <x-card title="Daftar Siswa Mengulang & Rombel Penempatan Baru ({{ $tidakLulusItems->count() }})" flush class="mb-4 border-l-4" style="border-left-color: #b45309;">
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th style="width: 140px;">NIS</th>
                            <th>Rombel Penempatan Baru (Tahun {{ $op->target_year }})</th>
                            <th style="width: 120px;">Tingkat</th>
                            <th style="width: 160px;">Status Akademik</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tidakLulusItems as $tl)
                            <tr>
                                <td class="is-strong font-monospace">{{ $tl->student_ref }}</td>
                                <td><strong>{{ $tl->targetClass->nama_kelas ?? '-' }}</strong></td>
                                <td><span class="chip chip--neutral">Tingkat XII</span></td>
                                <td><span class="chip chip--neutral" style="color: #b45309; background: #fef3c7;">Tinggal Kelas</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

    {{-- Audit Log Operasi --}}
    <x-card title="Jejak Audit Administratif" flush class="mb-4">
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th style="width: 180px;">Waktu Kejadian</th>
                        <th style="width: 160px;">Aksi / Tahap</th>
                        <th>Perubahan Status</th>
                        <th class="is-num" style="width: 120px;">Hasil</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($audits as $audit)
                        <tr>
                            <td class="text-muted small">{{ $audit->created_at }}</td>
                            <td class="fw-semibold text-dark">{{ $audit->event }}</td>
                            <td>
                                <code>{{ $audit->previous_state ?? 'awal' }}</code> &rarr; <code>{{ $audit->next_state }}</code>
                            </td>
                            <td class="is-num">
                                <span class="chip {{ $audit->outcome === 'success' ? 'chip--green' : 'chip--neutral' }}">
                                    {{ $audit->outcome }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
</div>
@endsection
