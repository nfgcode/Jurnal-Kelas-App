@extends('layouts.app')

@section('title', 'Dashboard Sesi Transisi Terpadu #' . $session->id)

@section('content')
<div class="transisi-terpadu-show-view space-y-5">
    <style>
        .transisi-terpadu-show-view .page-head {
            margin-top: 8px !important;
            margin-bottom: 24px !important;
        }
        .transisi-terpadu-show-view .page-head__title {
            font-size: 22px !important;
            font-weight: 700 !important;
            line-height: 1.25 !important;
            color: var(--text, #131313) !important;
            margin-bottom: 6px !important;
        }
        .transisi-terpadu-show-view .page-head__sub {
            font-size: 13.5px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
            color: var(--muted, #626262) !important;
            margin: 0 !important;
        }
        .transisi-terpadu-show-view .breadcrumb-nav {
            font-size: 13px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
        }
        .transisi-terpadu-show-view .breadcrumb-nav a {
            color: var(--muted, #626262) !important;
        }
        .transisi-terpadu-show-view .breadcrumb-nav a:hover {
            color: var(--primary, #3b5d32) !important;
        }
        .transisi-terpadu-show-view .card-hifi__title {
            font-size: 18px !important;
            font-weight: 600 !important;
            line-height: 1.3 !important;
            color: var(--text, #131313) !important;
        }
        .transisi-terpadu-show-view .btn-hifi {
            font-size: 14px !important;
            font-weight: 600 !important;
            line-height: 1.4 !important;
        }
        .transisi-terpadu-show-view .chip {
            font-size: 12.5px !important;
            font-weight: 600 !important;
            line-height: 1.4 !important;
        }
        .transisi-terpadu-show-view .tbl th {
            font-size: 12.5px !important;
            font-weight: 600 !important;
            line-height: 1.4 !important;
            padding: 10px 14px !important;
        }
        .transisi-terpadu-show-view .tbl td {
            font-size: 13.5px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
            padding: 10px 14px !important;
        }
        .transisi-terpadu-show-view .bento-stage-card {
            border-radius: var(--radius-card, 12px);
            background: #ffffff;
            border: 1px solid var(--n-200, #e6e8e5);
            transition: all 0.2s ease;
        }
        .transisi-terpadu-show-view .bento-stage-card.is-active {
            border: 1.5px solid var(--primary, #3b5d32);
            box-shadow: 0 4px 16px rgba(59, 93, 50, 0.08);
            background: #ffffff;
        }
        .transisi-terpadu-show-view .bento-stage-card.is-completed {
            border: 1px solid rgba(59, 93, 50, 0.25);
            background: #fafbfa;
        }
        .transisi-terpadu-show-view .bento-stage-card.is-locked {
            opacity: 0.72;
            background: #fdfdfd;
        }
        .transisi-terpadu-show-view .meter-track {
            height: 8px;
            border-radius: 999px;
            background: var(--n-200, #e9ebe8);
            overflow: hidden;
        }
        .transisi-terpadu-show-view .meter-fill {
            height: 100%;
            background: var(--primary, #3b5d32);
            border-radius: 999px;
            transition: width 0.3s ease;
        }
    </style>

    {{-- Breadcrumb Navigasi Horizontal --}}
    <nav class="breadcrumb-nav mb-3" aria-label="Breadcrumb">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Dashboard</a>
            <span class="text-muted">&rsaquo;</span>
            <a href="{{ route('admin.tahun-ajaran.index') }}" class="text-decoration-none">Tahun Ajaran</a>
            <span class="text-muted">&rsaquo;</span>
            <a href="{{ route('admin.transisi-terpadu.index') }}" class="text-decoration-none">Transisi Terpadu</a>
            <span class="text-muted">&rsaquo;</span>
            <span class="text-dark fw-semibold" aria-current="page">Sesi #{{ $session->id }}</span>
        </div>
    </nav>

    {{-- Page Header --}}
    <div class="page-head d-flex justify-content-between align-items-start">
        <div>
            <h2 class="page-head__title">Dashboard Transisi Terpadu #{{ $session->id }}</h2>
            <p class="page-head__sub">
                Koordinasi berurutan pemindahan data tahun ajaran dari persiapan hingga penetapan akhir.
            </p>
        </div>
        <div>
            <a href="{{ route('admin.transisi-terpadu.index') }}" class="btn-hifi btn-hifi--ghost">
                <x-ikon nama="arrow-left" /> Kembali ke Daftar Sesi
            </a>
        </div>
    </div>

    {{-- Status Flash & Error Messages --}}
    @if (session('status'))
        <div class="card-hifi p-3 border-l-4 border-primary bg-primary-soft d-flex align-items-center gap-2 mb-4">
            <x-ikon nama="check-circle" />
            <span class="fw-semibold text-primary" style="font-size: 14px;">{{ session('status') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="card-hifi p-4 border-l-4 border-danger mb-4" role="alert">
            <div class="d-flex align-items-center gap-2 text-danger fw-bold mb-2" style="font-size: 14px;">
                <x-ikon nama="exclamation-triangle" />
                <span>Terjadi kendala pada alur sesi:</span>
            </div>
            <ul class="mb-0 ps-3 text-danger" style="font-size: 13.5px; line-height: 1.5;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Bento Hero Row: Info Status Sesi (Kiri) + Progress Ringkas (Kanan) --}}
    <div style="display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); gap: 16px; align-items: stretch;" class="mb-4">
        {{-- Hero Kiri: Detail Periode & Metadata --}}
        <div class="card-hifi p-4 d-flex flex-column justify-content-between">
            <div>
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="chip {{ $isCompleted ? 'chip--green' : 'chip--neutral' }}">
                        {{ $isCompleted ? 'Seluruh Tahap Selesai' : 'Sedang Berjalan' }}
                    </span>
                    <span class="text-muted" style="font-size: 13px;">
                        Dibuat {{ \Illuminate\Support\Carbon::parse($session->created_at)->format('d M Y, H:i') }}
                    </span>
                </div>

                <div class="d-flex align-items-center gap-3 mb-3">
                    <div>
                        <div class="text-muted" style="font-size: 12px; font-weight: 500;">TAHUN SUMBER</div>
                        <div class="fw-bold text-dark" style="font-size: 18px;">{{ $session->source_year ?? '—' }}</div>
                    </div>
                    <div class="text-muted" style="font-size: 18px;">&rarr;</div>
                    <div>
                        <div class="text-muted" style="font-size: 12px; font-weight: 500;">TAHUN TUJUAN</div>
                        <div class="fw-bold text-dark" style="font-size: 18px;">{{ $session->target_year ?? '—' }}</div>
                    </div>
                </div>

                <p class="text-muted mb-0" style="font-size: 13.5px; line-height: 1.5;">
                    Sesi dikoordinasikan oleh <strong>{{ $session->actor_name ?? ('User #' . $session->actor_id) }}</strong>. Setiap tahap menjaga integritas data secara independen sebelum dilanjutkan ke tahap berikutnya.
                </p>
            </div>

            <div class="pt-3 mt-3 border-top d-flex align-items-center gap-2 text-muted" style="font-size: 13px;">
                <x-ikon nama="shield-check" />
                <span>Transaksi data terlindungi snapshot audit log pada setiap eksekusi.</span>
            </div>
        </div>

        {{-- Hero Kanan: Meter Progres Sesi --}}
        @php
            $pct = $totalStages > 0 ? round(($completedStages / $totalStages) * 100) : 0;
        @endphp
        <div class="card-hifi p-4 d-flex flex-column justify-content-between">
            <div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-semibold text-dark" style="font-size: 14.5px;">Kemajuan Alur Koordinasi</span>
                    <span class="chip chip--neutral fw-bold" style="font-size: 12.5px;">{{ $completedStages }} / {{ $totalStages }} Tahap</span>
                </div>
                <div class="d-flex align-items-baseline gap-2 mb-3">
                    <span class="fw-bold text-dark" style="font-size: 28px;">{{ $pct }}%</span>
                    <span class="text-muted" style="font-size: 13.5px;">langkah transisi terlaksana</span>
                </div>
                <div class="meter-track mb-3">
                    <div class="meter-fill" style="width: {{ $pct }}%;"></div>
                </div>
                <p class="text-muted mb-0" style="font-size: 13px; line-height: 1.4;">
                    @if ($isCompleted)
                        Semua tahap tuntas. Anda dapat memeriksa hasil masing-masing modul atau mengaktifkan tahun tujuan jika jadwal telah tiba.
                    @else
                        Selesaikan tahap yang sedang aktif untuk membuka akses ke tahap berikutnya secara teratur.
                    @endif
                </p>
            </div>

            <div class="pt-3 mt-3 border-top d-flex justify-content-between align-items-center" style="font-size: 13px;">
                <span class="text-muted">Status Tahap Aktif:</span>
                <span class="fw-semibold text-dark">
                    @if ($isCompleted)
                        Selesai Sempurna
                    @elseif ($currentActiveIndex >= 0)
                        {{ match ($stages[$currentActiveIndex]['kind'] ?? '') {
                            'graduation' => 'Tahap 1: Kelulusan XII',
                            'promotion' => 'Tahap 2: Kenaikan X–XI',
                            'intake' => 'Tahap 3: Penerimaan Siswa',
                            default => 'Dalam Proses',
                        } }}
                    @else
                        Siap Dijalankan
                    @endif
                </span>
            </div>
        </div>
    </div>

    {{-- Stepper Bento: 3 Kartu Alur Koordinasi --}}
    <div class="card-hifi mb-4">
        <div class="card-hifi__head py-3 px-4">
            <div class="d-flex align-items-center gap-2 mb-1">
                <x-ikon nama="diagram-3" />
                <h3 class="card-hifi__title mb-0" style="font-size: 18px !important;">Tahapan Alur Transisi</h3>
            </div>
            <span class="text-muted" style="font-size: 13.5px;">
                Jalankan alur secara bertahap mulai dari kelulusan, kenaikan kelas, hingga penerimaan siswa baru.
            </span>
        </div>

        <div class="card-hifi__body p-4 pt-3">
            <div class="grid-row grid-row--3" style="gap: 16px;">
                @foreach ($stages as $idx => $st)
                    @php
                        $isPast = ($st['state'] === 'completed');
                        $isCurrent = ($idx === $currentActiveIndex);
                        $isFuture = (! $isPast && ! $isCurrent);

                        $stageTitle = match ($st['kind']) {
                            'graduation' => 'Tahap 1: Kelulusan XII',
                            'promotion' => 'Tahap 2: Kenaikan X–XI',
                            'intake' => 'Tahap 3: Penerimaan Siswa',
                            default => ucfirst($st['kind']),
                        };

                        $stageIcon = match ($st['kind']) {
                            'graduation' => 'mortarboard',
                            'promotion' => 'arrow-up-circle',
                            'intake' => 'person-plus',
                            default => 'gear',
                        };

                        $stageDesc = match ($st['kind']) {
                            'graduation' => 'Penetapan status kelulusan siswa tingkat akhir dan arsip alumni periode sumber.',
                            'promotion' => 'Promosi siswa tingkat X ke XI dan XI ke XII beserta penempatan rombel baru.',
                            'intake' => 'Pendaftaran rombel siswa baru kelas X tahun ajaran sasaran.',
                            default => 'Proses transisi data akademik.',
                        };

                        $moduleUrl = match ($st['kind']) {
                            'graduation' => route('admin.kelulusan.index', ['tahun_asal' => $session->source_year, 'tahun_tujuan' => $session->target_year, 'coordinator_id' => $session->id]),
                            'promotion' => route('admin.kenaikan.index', ['tahun_asal' => $session->source_year, 'tahun_tujuan' => $session->target_year, 'coordinator_id' => $session->id]),
                            'intake' => route('admin.intake.index', ['tahun_tujuan' => $session->target_year, 'coordinator_id' => $session->id]),
                            default => '#',
                        };

                        $cardClass = $isPast ? 'is-completed' : ($isCurrent ? 'is-active' : 'is-locked');
                    @endphp

                    <div class="bento-stage-card {{ $cardClass }} p-4 d-flex flex-column justify-content-between">
                        <div>
                            {{-- Header Kartu --}}
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="chip {{ $isPast ? 'chip--green' : ($isCurrent ? 'chip--solid-lembut' : 'chip--neutral') }}">
                                    @if ($isPast)
                                        Selesai
                                    @elseif ($isCurrent)
                                        Tahap Berjalan
                                    @else
                                        Menunggu Giliran
                                    @endif
                                </span>
                                <div style="font-size: 20px; color: var(--primary, #3b5d32);">
                                    <x-ikon :nama="$stageIcon" />
                                </div>
                            </div>

                            <h4 class="stage-card__title mb-2" style="font-size: 16px; font-weight: 600; color: var(--text, #131313);">
                                {{ $stageTitle }}
                            </h4>
                            <p class="text-muted mb-3" style="font-size: 13px; line-height: 1.5;">
                                {{ $stageDesc }}
                            </p>

                            @if ($st['operation_id'])
                                <div class="p-2 mb-3 rounded" style="background: rgba(0,0,0,0.03); font-size: 12.5px;">
                                    <span class="text-muted">Operasi Terhubung:</span>
                                    <strong class="text-dark">#{{ $st['operation_id'] }}</strong>
                                </div>
                            @endif
                        </div>

                        {{-- Action Area Bawah --}}
                        <div class="pt-3 border-top">
                            @if ($isPast)
                                @php
                                    $recapUrl = match ($st['kind']) {
                                        'graduation' => route('admin.kelulusan.show', $st['operation_id']),
                                        'promotion' => route('admin.kenaikan.show', $st['operation_id']),
                                        'intake' => route('admin.intake.show', $st['operation_id']),
                                        default => '#',
                                    };
                                @endphp
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-success fw-semibold d-flex align-items-center gap-1" style="font-size: 13px;">
                                        <x-ikon nama="check-circle" /> Sukses
                                    </span>
                                    @if ($st['operation_id'])
                                        <a href="{{ $recapUrl }}" class="btn-hifi btn-hifi--sm btn-hifi--ghost">
                                            <x-ikon nama="check2-all" /> Lihat Hasil
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
                                    <a href="{{ $reviewUrl }}" class="btn-hifi w-100 text-center justify-content-center">
                                        <x-ikon nama="pencil" /> Lanjutkan Review
                                    </a>
                                @else
                                    <div class="d-flex flex-column gap-2">
                                        <a href="{{ $moduleUrl }}" class="btn-hifi w-100 text-center justify-content-center">
                                            <x-ikon nama="arrow-right-circle" /> Masuk ke Formulir
                                        </a>
                                        <button type="button" class="btn-hifi btn-hifi--ghost btn-hifi--sm w-100 justify-content-center" data-bs-toggle="modal" data-bs-target="#attachModal{{ $st['kind'] }}">
                                            Gunakan ID yang Sudah Ada
                                        </button>
                                    </div>
                                @endif
                            @else
                                <div class="text-muted d-flex align-items-center gap-2" style="font-size: 13px;">
                                    <x-ikon nama="lock" />
                                    <span>Terkunci sampai tahap sebelumnya tuntas</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Modal Hubungkan Operasi --}}
                    @if (! $isPast && ! $st['operation_id'])
                        <div class="modal fade" id="attachModal{{ $st['kind'] }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content card-hifi p-4">
                                    <form method="POST" action="{{ route('admin.transisi-terpadu.attach', $session->id) }}">
                                        @csrf
                                        <input type="hidden" name="kind" value="{{ $st['kind'] }}">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h4 class="card-hifi__title mb-0" style="font-size: 18px !important;">Hubungkan Operasi {{ $stageTitle }}</h4>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                        </div>
                                        <div class="mb-4">
                                            <p class="field__hint mb-3">
                                                Gunakan opsi ini jika Anda sebelumnya telah menyiapkan draf operasi langsung pada modul mandiri. Masukkan nomor ID operasi yang sesuai.
                                            </p>
                                            <div class="field">
                                                <label class="field__label" for="op_{{ $st['kind'] }}">
                                                    ID Operasi Terdaftar<span class="field__req"> *</span>
                                                </label>
                                                <input type="number" id="op_{{ $st['kind'] }}" name="operation_id" class="select-hifi w-100" placeholder="Contoh: 12" required>
                                                <span class="field__hint">Pastikan status operasi masih berstatus draft atau ready.</span>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                                            <button type="button" class="btn-hifi btn-hifi--ghost" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn-hifi">Hubungkan ke Sesi</button>
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

    {{-- Alert Kelengkapan Akhir Sesi --}}
    @if ($isCompleted)
        <div class="card-hifi p-4 border-l-4 border-primary bg-primary-soft d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center gap-3">
                <div style="font-size: 28px; color: var(--primary, #3b5d32);">
                    <x-ikon nama="check-circle" />
                </div>
                <div>
                    <h4 class="fw-bold text-dark mb-1" style="font-size: 17px;">Seluruh Tahapan Transisi Terpadu Selesai!</h4>
                    <p class="text-muted mb-0" style="font-size: 13.5px; line-height: 1.5;">
                        Kelulusan, kenaikan kelas, dan penerimaan siswa baru telah dieksekusi secara aman. Status tahun ajaran <strong>{{ $session->target_year }}</strong> tetap nonaktif untuk keamanan arsip sampai Anda mengaktifkannya melalui menu Tahun Ajaran.
                    </p>
                </div>
            </div>
            <div>
                <a href="{{ route('admin.tahun-ajaran.index') }}" class="btn-hifi text-nowrap">
                    Buka Menu Tahun Ajaran &rarr;
                </a>
            </div>
        </div>
    @endif

    {{-- Jejak Audit Sesi Terpadu --}}
    <x-card title="Jejak Audit Sesi Transisi Terpadu" flush>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th style="width: 160px;">Waktu Kejadian</th>
                        <th style="width: 220px;">Jenis Aktivitas</th>
                        <th>Keterangan Tindakan</th>
                        <th style="width: 160px;">Operator</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($audits as $audit)
                        <tr>
                            <td class="is-muted is-nowrap">
                                {{ \Illuminate\Support\Carbon::parse($audit->created_at)->format('d/m/Y H:i:s') }}
                            </td>
                            <td>
                                <span class="chip chip--neutral" style="font-family: monospace; font-size: 12px;">
                                    {{ $audit->aksi }}
                                </span>
                            </td>
                            <td class="text-dark">
                                {{ $audit->deskripsi }}
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="avatar avatar--xs">{{ strtoupper(substr($session->actor_name ?? 'A', 0, 1)) }}</span>
                                    <span>{{ $session->actor_name ?? ('User #' . $audit->user_id) }}</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="empty-state py-4 text-center text-muted">
                                Belum ada riwayat aktivitas yang tercatat untuk sesi transisi ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</div>
@endsection
