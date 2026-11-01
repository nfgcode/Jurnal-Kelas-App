@extends('layouts.app')

@section('title', 'Hasil Rekapitulasi Kenaikan Kelas X–XI')

@section('content')
<div class="kenaikan-show-page">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-1">Rekapitulasi Kenaikan Kelas X–XI</h4>
            <div class="text-muted small">
                Operasi #{{ $op->id }} &bull; Periode Sumber: <strong>{{ $op->source_year }}</strong>
                &bull; Periode Tujuan: <strong>{{ $op->target_year }}</strong>
                &bull; Dieksekusi pada: <strong>{{ $op->completed_at ? \Illuminate\Support\Carbon::parse($op->completed_at)->format('d/m/Y H:i') : '-' }}</strong>
            </div>
        </div>
        <div>
            @php
                $attachedCoordId = \Illuminate\Support\Facades\DB::table('academic_workflow_stages')
                    ->where('operation_id', $op->id)
                    ->value('coordinator_id');
            @endphp
            @if ($attachedCoordId)
                <a href="{{ route('admin.transisi-terpadu.show', $attachedCoordId) }}" class="btn btn-primary btn-sm fw-bold me-2">
                    <x-ikon nama="diagram-3" /> Kembali ke Transisi Terpadu
                </a>
            @endif
            <a href="{{ route('admin.kenaikan.index') }}" class="btn btn-outline-secondary btn-sm">
                <x-ikon nama="arrow-left" /> Kembali ke Daftar
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success d-flex align-items-center gap-2 mb-4" role="alert">
            <x-ikon nama="check-circle" />
            <div>{{ session('status') }}</div>
        </div>
    @endif

    {{-- Kartu Metrik Hasil --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4 col-6">
            <div class="card shadow-sm border-0">
                <div class="card-body p-3">
                    <div class="text-muted small fw-bold text-uppercase">Total Siswa Diproses</div>
                    <div class="fs-4 fw-bold text-dark">{{ number_format($op->items()->count(), 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-6">
            <div class="card shadow-sm border-0 border-start border-success border-4">
                <div class="card-body p-3">
                    <div class="text-success small fw-bold text-uppercase">Siswa Naik Tingkat</div>
                    <div class="fs-4 fw-bold text-success">{{ number_format($naikItems->count(), 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-6">
            <div class="card shadow-sm border-0 border-start border-warning border-4">
                <div class="card-body p-3">
                    <div class="text-warning small fw-bold text-uppercase">Siswa Tinggal Kelas</div>
                    <div class="fs-4 fw-bold text-warning">{{ number_format($tinggalItems->count(), 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabs Rekapitulasi --}}
    <ul class="nav nav-tabs mb-3" id="rekapTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-bold" id="naik-tab" data-bs-toggle="tab" data-bs-target="#naikTabPane" type="button" role="tab">
                <x-ikon nama="check-circle" /> Siswa Naik Kelas ({{ $naikItems->count() }})
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold" id="tinggal-tab" data-bs-toggle="tab" data-bs-target="#tinggalTabPane" type="button" role="tab">
                <x-ikon nama="dash-circle" /> Siswa Tinggal Kelas ({{ $tinggalItems->count() }})
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold" id="audit-tab" data-bs-toggle="tab" data-bs-target="#auditTabPane" type="button" role="tab">
                <x-ikon nama="clock-history" /> Jejak Audit Operasi
            </button>
        </li>
    </ul>

    <div class="tab-content" id="rekapTabsContent">
        {{-- Tab Siswa Naik Kelas --}}
        <div class="tab-pane fade show active" id="naikTabPane" role="tabpanel">
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>NIS</th>
                                    <th>Nama Siswa</th>
                                    <th>Tingkat Baru</th>
                                    <th>Rombel Kelas Baru (Tahun {{ $op->target_year }})</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($naikItems as $item)
                                    @php $s = $students->get($item->student_ref); @endphp
                                    <tr>
                                        <td class="font-monospace fw-bold">{{ $item->student_ref }}</td>
                                        <td>{{ $s ? $s->nama : '-' }}</td>
                                        <td><span class="badge bg-success">Tingkat {{ $item->targetClass?->tingkat ?? '-' }}</span></td>
                                        <td><strong>{{ $item->targetClass?->nama_kelas ?? '-' }}</strong></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">Tidak ada siswa naik kelas.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tab Siswa Tinggal Kelas --}}
        <div class="tab-pane fade" id="tinggalTabPane" role="tabpanel">
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>NIS</th>
                                    <th>Nama Siswa</th>
                                    <th>Tingkat Mengulang</th>
                                    <th>Rombel Penempatan Baru (Tahun {{ $op->target_year }})</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($tinggalItems as $item)
                                    @php $s = $students->get($item->student_ref); @endphp
                                    <tr>
                                        <td class="font-monospace fw-bold">{{ $item->student_ref }}</td>
                                        <td>{{ $s ? $s->nama : '-' }}</td>
                                        <td><span class="badge bg-warning text-dark">Mengulang Tingkat {{ $item->targetClass?->tingkat ?? '-' }}</span></td>
                                        <td><strong>{{ $item->targetClass?->nama_kelas ?? '-' }}</strong></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">Tidak ada siswa tinggal kelas.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tab Audit Trail --}}
        <div class="tab-pane fade" id="auditTabPane" role="tabpanel">
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>Waktu</th>
                                    <th>Peristiwa</th>
                                    <th>Hasil Status</th>
                                    <th>State Awal &rarr; Akhir</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($audits as $audit)
                                    <tr>
                                        <td class="small text-muted">{{ \Illuminate\Support\Carbon::parse($audit->created_at)->format('d/m/Y H:i:s') }}</td>
                                        <td class="fw-bold">{{ ucfirst($audit->event) }}</td>
                                        <td>
                                            <span class="badge {{ $audit->outcome === 'success' ? 'bg-success' : 'bg-warning text-dark' }}">
                                                {{ ucfirst($audit->outcome) }}
                                            </span>
                                        </td>
                                        <td>
                                            <code>{{ $audit->previous_state ?? '-' }}</code> &rarr; <code>{{ $audit->next_state }}</code>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">Belum ada catatan audit untuk operasi ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
