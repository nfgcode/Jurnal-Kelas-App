@extends('layouts.app')

@section('title', 'Hasil Rekapitulasi Kenaikan Kelas X–XI')

@section('content')
<div class="kenaikan-show-view space-y-5">
    <style>
        .kenaikan-show-view .page-head {
            margin-top: 8px !important;
            margin-bottom: 24px !important;
        }
        .kenaikan-show-view .page-head__title {
            font-size: 22px !important;
            font-weight: 700 !important;
            line-height: 1.25 !important;
            color: #111827 !important;
            margin-bottom: 6px !important;
        }
        .kenaikan-show-view .page-head__sub {
            font-size: 14px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
            color: #4b5563 !important;
            margin: 0 !important;
        }
        .kenaikan-show-view .breadcrumb-nav {
            font-size: 13px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
        }
        .kenaikan-show-view .breadcrumb-nav a {
            color: #4b5563 !important;
        }
        .kenaikan-show-view .breadcrumb-nav a:hover {
            color: var(--primary, #3b5d32) !important;
        }
        .kenaikan-show-view .kpi-card__num {
            font-size: 24px !important;
            font-weight: 700 !important;
            line-height: 1.2 !important;
        }
        .kenaikan-show-view .kpi-card__label {
            font-size: 13px !important;
            font-weight: 500 !important;
            line-height: 1.3 !important;
            color: #4b5563 !important;
        }
        .kenaikan-show-view .btn-hifi {
            font-size: 14px !important;
            font-weight: 600 !important;
            line-height: 1.4 !important;
        }
        .kenaikan-show-view .tbl th {
            font-size: 12.5px !important;
            font-weight: 600 !important;
            line-height: 1.4 !important;
            padding: 10px 14px !important;
        }
        .kenaikan-show-view .tbl td {
            font-size: 13.5px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
            padding: 10px 14px !important;
        }
        .kenaikan-show-view .rekap-tabs .nav-link {
            border: 1px solid transparent;
            border-bottom: 2px solid transparent;
            color: #4b5563;
            font-size: 13.5px;
            font-weight: 600;
            padding: 10px 18px;
            border-radius: 8px 8px 0 0;
        }
        .kenaikan-show-view .rekap-tabs .nav-link.active {
            color: var(--primary, #3b5d32);
            border-bottom: 2px solid var(--primary, #3b5d32);
            background: rgba(59, 93, 50, 0.05);
        }
    </style>

    {{-- Breadcrumb Navigasi --}}
    <nav class="breadcrumb-nav mb-3" aria-label="Breadcrumb">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Dashboard</a>
            <span class="text-muted">&rsaquo;</span>
            <a href="{{ route('admin.tahun-ajaran.index') }}" class="text-decoration-none">Tahun Ajaran</a>
            <span class="text-muted">&rsaquo;</span>
            <a href="{{ route('admin.kenaikan.index') }}" class="text-decoration-none">Kenaikan Kelas X–XI</a>
            <span class="text-muted">&rsaquo;</span>
            <span class="text-dark fw-semibold" aria-current="page">Hasil Eksekusi #{{ $op->id }}</span>
        </div>
    </nav>

    {{-- Bento Hero Card: Rekapitulasi & Aksi Navigasi --}}
    <div class="card-hifi p-4 mb-4 border-l-4" style="border-left-color: var(--primary, #3b5d32);">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <h2 class="page-head__title mb-0" style="font-size: 21px !important;">Rekapitulasi Kenaikan Kelas X–XI</h2>
                    <span class="chip chip--green">Eksekusi Selesai</span>
                </div>
                <div class="page-head__sub d-flex flex-wrap align-items-center gap-2">
                    <span>Operasi #{{ $op->id }}</span>
                    <span class="text-muted">&bull;</span>
                    <span>Periode:</span>
                    <span class="chip chip--solid-lembut fw-bold" style="font-size: 13px; color: var(--primary, #3b5d32);">{{ $op->source_year }}</span>
                    <span>&rarr;</span>
                    <span class="chip chip--solid-lembut fw-bold" style="font-size: 13px; color: #1e3a8a; background: #e0e7ff;">{{ $op->target_year }}</span>
                    <span class="text-muted">&bull;</span>
                    <span>Dieksekusi: <strong>{{ $op->completed_at ? \Illuminate\Support\Carbon::parse($op->completed_at)->format('d/m/Y H:i') : '-' }}</strong></span>
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
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="card-hifi p-3 mb-4 border-l-4 border-primary bg-primary-soft d-flex align-items-center gap-2">
            <x-ikon nama="check-circle" class="text-primary" />
            <div class="text-dark fw-medium small">{{ session('status') }}</div>
        </div>
    @endif

    {{-- Kartu Metrik Hasil Bento --}}
    <div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px;" class="mb-4">
        {{-- Total Siswa --}}
        <div class="card-hifi p-3 d-flex align-items-center gap-3 border-l-4 border-primary">
            <div style="width: 48px; height: 48px; border-radius: 8px; background: rgba(59, 93, 50, 0.12); color: var(--primary, #3b5d32); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 20px;">
                <x-ikon nama="people" />
            </div>
            <div>
                <div class="kpi-card__num text-dark">{{ number_format($op->items()->count(), 0, ',', '.') }}</div>
                <div class="kpi-card__label">Total Roster Diproses</div>
            </div>
        </div>

        {{-- Naik Tingkat --}}
        <div class="card-hifi p-3 d-flex align-items-center gap-3 border-l-4" style="border-left-color: #166534;">
            <div style="width: 48px; height: 48px; border-radius: 8px; background: rgba(22, 101, 52, 0.12); color: #166534; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 20px;">
                <x-ikon nama="arrow-up-circle" />
            </div>
            <div>
                <div class="kpi-card__num" style="color: #166534;">{{ number_format($naikItems->count(), 0, ',', '.') }}</div>
                <div class="kpi-card__label">Siswa Naik Tingkat</div>
            </div>
        </div>

        {{-- Tinggal Kelas --}}
        <div class="card-hifi p-3 d-flex align-items-center gap-3 border-l-4" style="border-left-color: #b45309;">
            <div style="width: 48px; height: 48px; border-radius: 8px; background: rgba(180, 83, 9, 0.12); color: #b45309; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 20px;">
                <x-ikon nama="dash-circle" />
            </div>
            <div>
                <div class="kpi-card__num" style="color: #b45309;">{{ number_format($tinggalItems->count(), 0, ',', '.') }}</div>
                <div class="kpi-card__label">Siswa Tinggal Kelas</div>
            </div>
        </div>
    </div>

    {{-- Tabs Rekapitulasi --}}
    <ul class="nav rekap-tabs mb-3 gap-2" id="rekapTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="naik-tab" data-bs-toggle="tab" data-bs-target="#naikTabPane" type="button" role="tab">
                <x-ikon nama="check-circle" /> Siswa Naik Kelas ({{ $naikItems->count() }})
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tinggal-tab" data-bs-toggle="tab" data-bs-target="#tinggalTabPane" type="button" role="tab">
                <x-ikon nama="dash-circle" /> Siswa Tinggal Kelas ({{ $tinggalItems->count() }})
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="audit-tab" data-bs-toggle="tab" data-bs-target="#auditTabPane" type="button" role="tab">
                <x-ikon nama="clock-history" /> Jejak Audit Operasi
            </button>
        </li>
    </ul>

    <div class="tab-content" id="rekapTabsContent">
        {{-- Tab Siswa Naik Kelas --}}
        <div class="tab-pane fade show active" id="naikTabPane" role="tabpanel">
            <x-card flush class="border-l-4 border-primary">
                <div class="tbl-wrap">
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th style="width: 140px;">NIS</th>
                                <th>Nama Siswa</th>
                                <th style="width: 140px;">Tingkat Baru</th>
                                <th>Rombel Kelas Baru (Tahun {{ $op->target_year }})</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($naikItems as $item)
                                @php $s = $students->get($item->student_ref); @endphp
                                <tr>
                                    <td class="is-strong font-monospace">{{ $item->student_ref }}</td>
                                    <td><strong>{{ $s ? $s->nama : '-' }}</strong></td>
                                    <td><span class="chip chip--green">Tingkat {{ $item->targetClass?->tingkat ?? '-' }}</span></td>
                                    <td><strong class="text-dark">{{ $item->targetClass?->nama_kelas ?? '-' }}</strong></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="empty-state text-center py-4 text-muted">Tidak ada siswa naik kelas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>

        {{-- Tab Siswa Tinggal Kelas --}}
        <div class="tab-pane fade" id="tinggalTabPane" role="tabpanel">
            <x-card flush class="border-l-4" style="border-left-color: #b45309;">
                <div class="tbl-wrap">
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th style="width: 140px;">NIS</th>
                                <th>Nama Siswa</th>
                                <th style="width: 160px;">Tingkat Mengulang</th>
                                <th>Rombel Penempatan Baru (Tahun {{ $op->target_year }})</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($tinggalItems as $item)
                                @php $s = $students->get($item->student_ref); @endphp
                                <tr>
                                    <td class="is-strong font-monospace">{{ $item->student_ref }}</td>
                                    <td><strong>{{ $s ? $s->nama : '-' }}</strong></td>
                                    <td><span class="chip chip--neutral" style="color: #b45309; background: #fef3c7;">Mengulang Tingkat {{ $item->targetClass?->tingkat ?? '-' }}</span></td>
                                    <td><strong>{{ $item->targetClass?->nama_kelas ?? '-' }}</strong></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="empty-state text-center py-4 text-muted">Tidak ada siswa tinggal kelas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>

        {{-- Tab Audit Trail --}}
        <div class="tab-pane fade" id="auditTabPane" role="tabpanel">
            <x-card flush>
                <div class="tbl-wrap">
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th style="width: 180px;">Waktu Kejadian</th>
                                <th style="width: 160px;">Peristiwa</th>
                                <th style="width: 130px;">Hasil Status</th>
                                <th>Perubahan State</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($audits as $audit)
                                <tr>
                                    <td class="text-muted small">{{ \Illuminate\Support\Carbon::parse($audit->created_at)->format('d/m/Y H:i:s') }}</td>
                                    <td class="fw-semibold text-dark">{{ ucfirst($audit->event) }}</td>
                                    <td>
                                        <span class="chip {{ $audit->outcome === 'success' ? 'chip--green' : 'chip--neutral' }}">
                                            {{ ucfirst($audit->outcome) }}
                                        </span>
                                    </td>
                                    <td>
                                        <code>{{ $audit->previous_state ?? '-' }}</code> &rarr; <code>{{ $audit->next_state }}</code>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="empty-state text-center py-4 text-muted">Belum ada catatan audit untuk operasi ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>
    </div>
</div>
@endsection
