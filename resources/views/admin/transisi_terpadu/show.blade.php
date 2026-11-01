@extends('layouts.app')

@section('title', 'Dashboard Sesi Transisi Terpadu #' . $session->id)

@section('content')
<div class="transisi-terpadu-show-page space-y-4">
    {{-- Breadcrumb Navigasi --}}
    <nav class="breadcrumb-nav mb-2" aria-label="Breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.tahun-ajaran.index') }}">Tahun Ajaran</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.transisi-terpadu.index') }}">Transisi Terpadu</a></li>
            <li class="breadcrumb-item active" aria-current="page">Sesi #{{ $session->id }}</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-1">
                <x-ikon nama="diagram-3" /> Dashboard Transisi Terpadu #{{ $session->id }}
            </h4>
            <div class="text-muted small">
                Periode: <strong>{{ $session->source_year ?? '-' }}</strong> &rarr; <strong>{{ $session->target_year ?? '-' }}</strong>
                &bull; Pembuat: <strong>{{ $session->actor_name ?? ('User #' . $session->actor_id) }}</strong>
                &bull; Status: <span class="badge {{ $isCompleted ? 'bg-success' : 'bg-primary' }}">{{ $isCompleted ? 'Semua Tahap Selesai' : 'Sedang Berjalan' }}</span>
            </div>
        </div>
        <div>
            <a href="{{ route('admin.transisi-terpadu.index') }}" class="btn btn-outline-secondary btn-sm">
                <x-ikon nama="arrow-left" /> Kembali ke Daftar Sesi
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
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Progress Card & Stepper --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold text-uppercase text-muted mb-0">Kemajuan Tahapan Koordinasi</h6>
                <span class="badge bg-light text-dark border fs-6">
                    {{ $completedStages }} dari {{ $totalStages }} Tahap Selesai
                </span>
            </div>

            <div class="progress mb-4" style="height: 10px;">
                @php
                    $pct = $totalStages > 0 ? round(($completedStages / $totalStages) * 100) : 0;
                @endphp
                <div class="progress-bar bg-success" role="progressbar" style="width: {{ $pct }}%;" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"></div>
            </div>

            <div class="row g-3">
                @foreach ($stages as $idx => $st)
                    @php
                        $isPast = ($st['state'] === 'completed');
                        $isCurrent = ($idx === $currentActiveIndex);
                        $isFuture = (! $isPast && ! $isCurrent);

                        $badgeClass = $isPast ? 'bg-success' : ($isCurrent ? 'bg-primary' : 'bg-secondary');
                        $borderClass = $isCurrent ? 'border-primary border-2' : ($isPast ? 'border-success' : 'border-light');

                        $stageTitle = match ($st['kind']) {
                            'graduation' => 'Tahap 1: Kelulusan XII',
                            'promotion' => 'Tahap 2: Kenaikan X–XI',
                            'intake' => 'Tahap 3: Penerimaan Siswa',
                            default => ucfirst($st['kind']),
                        };

                        $moduleUrl = match ($st['kind']) {
                            'graduation' => route('admin.kelulusan.index', ['tahun_asal' => $session->source_year, 'tahun_tujuan' => $session->target_year, 'coordinator_id' => $session->id]),
                            'promotion' => route('admin.kenaikan.index', ['tahun_asal' => $session->source_year, 'tahun_tujuan' => $session->target_year, 'coordinator_id' => $session->id]),
                            'intake' => route('admin.intake.index', ['tahun_tujuan' => $session->target_year, 'coordinator_id' => $session->id]),
                            default => '#',
                        };
                    @endphp
                    <div class="col-md-4">
                        <div class="card h-100 shadow-sm border {{ $borderClass }} p-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge {{ $badgeClass }} text-uppercase">
                                    {{ $st['kind'] }}
                                </span>
                                <span class="badge bg-light text-muted border small">
                                    Posisi {{ $st['position'] + 1 }}
                                </span>
                            </div>

                            <h5 class="fw-bold mb-1">{{ $stageTitle }}</h5>
                            <div class="small text-muted mb-3">
                                Status: <strong>{{ ucfirst($st['state']) }}</strong>
                                @if ($st['operation_id'])
                                    &bull; Operasi: <strong>#{{ $st['operation_id'] }}</strong>
                                @endif
                            </div>

                            <div class="mt-auto pt-2 border-top">
                                @if ($isPast)
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-success small fw-bold">
                                            <x-ikon nama="check-circle" /> Selesai Dieksekusi
                                        </span>
                                        @if ($st['operation_id'])
                                            @php
                                                $recapUrl = match ($st['kind']) {
                                                    'graduation' => route('admin.kelulusan.show', $st['operation_id']),
                                                    'promotion' => route('admin.kenaikan.show', $st['operation_id']),
                                                    'intake' => route('admin.intake.show', $st['operation_id']),
                                                    default => '#',
                                                };
                                            @endphp
                                            <a href="{{ $recapUrl }}" class="btn btn-sm btn-outline-success">
                                                <x-ikon nama="check2-all" /> Hasil
                                            </a>
                                        @endif
                                    </div>
                                @elseif ($isCurrent)
                                    @if ($st['operation_id'])
                                        @php
                                            $reviewUrl = match ($st['kind']) {
                                                'graduation' => route('admin.kelulusan.review', $st['operation_id']),
                                                'promotion' => route('admin.kenaikan.review', $st['operation_id']),
                                                'intake' => route('admin.intake.review', $st['operation_id']),
                                                default => '#',
                                            };
                                        @endphp
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge bg-primary">Tahap Aktif</span>
                                            <a href="{{ $reviewUrl }}" class="btn btn-sm btn-primary fw-bold">
                                                <x-ikon nama="pencil" /> Lanjutkan Review
                                            </a>
                                        </div>
                                    @else
                                        <div class="d-flex flex-column gap-2">
                                            <a href="{{ $moduleUrl }}" class="btn btn-sm btn-primary fw-bold">
                                                <x-ikon nama="upload" /> Mulai Alur {{ ucfirst($st['kind']) }}
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#attachModal{{ $st['kind'] }}">
                                                Hubungkan Operasi ID
                                            </button>
                                        </div>
                                    @endif
                                @else
                                    <div class="text-muted small">
                                        <x-ikon nama="clock-history" /> Menunggu penyelesaian tahap sebelumnya
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Modal Hubungkan Operasi --}}
                    @if (! $isPast && ! $st['operation_id'])
                        <div class="modal fade" id="attachModal{{ $st['kind'] }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST" action="{{ route('admin.transisi-terpadu.attach', $session->id) }}">
                                        @csrf
                                        <input type="hidden" name="kind" value="{{ $st['kind'] }}">
                                        <div class="modal-header">
                                            <h5 class="modal-title fw-bold">Hubungkan Operasi ke {{ $stageTitle }}</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p class="small text-muted mb-3">
                                                Masukkan ID Operasi (Draft / Ready) yang sudah dibuat pada modul {{ ucfirst($st['kind']) }} untuk periode yang sama.
                                            </p>
                                            <div class="mb-3">
                                                <label class="form-label fw-bold small">ID Operasi <span class="text-danger">*</span></label>
                                                <input type="number" name="operation_id" class="form-control" placeholder="contoh: 12" required>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-primary btn-sm">Hubungkan Operasi</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>

    {{-- Kartu Status Akhir & Aktivasi --}}
    @if ($isCompleted)
        <div class="alert alert-success d-flex align-items-center justify-content-between p-4 mb-4 shadow-sm" role="alert">
            <div class="d-flex gap-3 align-items-center">
                <x-ikon nama="check-circle" class="fs-2 text-success flex-shrink-0" />
                <div>
                    <h5 class="alert-heading fw-bold mb-1">Seluruh Tahapan Transisi Terpadu Selesai!</h5>
                    <p class="small mb-0">
                        Kelulusan, kenaikan, dan penerimaan siswa baru telah dieksekusi secara aman. Status tahun ajaran <strong>{{ $session->target_year }}</strong> tetap nonaktif demi keamanan historis sampai Anda melakukan aktivasi manual.
                    </p>
                </div>
            </div>
            <a href="{{ route('admin.tahun-ajaran.index') }}" class="btn btn-outline-success fw-bold px-4">
                Buka Menu Tahun Ajaran
            </a>
        </div>
    @endif

    {{-- Jejak Audit Sesi Terpadu --}}
    <div class="card shadow-sm border-0">
        <div class="card-header bg-transparent fw-bold">
            <x-ikon nama="clock-history" /> Jejak Audit Sesi Transisi Terpadu
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>Waktu</th>
                            <th>Peristiwa Aksi</th>
                            <th>Deskripsi</th>
                            <th>Pengguna</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($audits as $audit)
                            <tr>
                                <td class="small text-muted">{{ \Illuminate\Support\Carbon::parse($audit->created_at)->format('d/m/Y H:i:s') }}</td>
                                <td><code>{{ $audit->aksi }}</code></td>
                                <td>{{ $audit->deskripsi }}</td>
                                <td>User #{{ $audit->user_id }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">Belum ada catatan audit.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
