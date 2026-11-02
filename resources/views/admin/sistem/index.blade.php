@extends('layouts.app')

@section('title', 'Sistem & Log')

@push('styles')
<style>
    .cek-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 14px;
    }
    .cek-card {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        background: #ffffff;
        padding: 16px 18px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        transition: all 0.2s ease;
    }
    .cek-card:hover {
        border-color: var(--primary, #3b5d32);
        box-shadow: 0 4px 12px rgba(59, 93, 50, 0.08);
    }
    .cek-card__atas {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 8px;
    }
    .cek-card__nama {
        font-size: 13.5px;
        font-weight: 600;
        color: #111827;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .cek-card__nilai {
        font-size: 17px;
        font-weight: 700;
        letter-spacing: -0.01em;
        color: #111827;
        margin-bottom: 4px;
    }
    .cek-card__detail {
        font-size: 12.5px;
        color: #4b5563;
        line-height: 1.5;
    }
    .segmented-pill-nav {
        display: inline-flex;
        background: #f3f4f6;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 4px;
        gap: 4px;
    }
    .segmented-pill-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 9px 18px;
        font-size: 13px;
        font-weight: 600;
        border-radius: 8px;
        text-decoration: none !important;
        color: #4b5563;
        transition: all 0.2s ease;
    }
    .segmented-pill-btn:hover {
        color: #111827;
        background: rgba(0, 0, 0, 0.03);
    }
    .segmented-pill-btn.is-active {
        background: var(--primary, #3b5d32);
        color: #ffffff !important;
        box-shadow: 0 1px 3px rgba(59, 93, 50, 0.25);
    }
    .log-entri {
        border-top: 1px solid #e5e7eb;
        padding: 12px 0;
    }
    .log-entri:first-child {
        border-top: 0;
    }
    .log-entri__atas {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 4px;
    }
    .log-entri__waktu {
        font-size: 12px;
        color: #6b7280;
    }
    .log-entri__pesan {
        font-size: 13px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 6px;
    }
    .kv-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12.5px;
    }
    .kv-table th, .kv-table td {
        padding: 8px 12px;
        border-bottom: 1px solid #e5e7eb;
    }
    .kv-table th {
        width: 32%;
        color: #4b5563;
        font-weight: 600;
        background: #f9fafb;
    }
    .kv-table td {
        color: #111827;
        word-break: break-word;
    }
</style>
@endpush

@section('content')
    @php
        $tone = ['ok' => 'green', 'warn' => 'yellow', 'gagal' => 'red', 'n/a' => 'neutral'];
        $label = ['ok' => 'Normal', 'warn' => 'Perhatian', 'gagal' => 'Bermasalah', 'n/a' => 'N/A'];
        $adaGagal = collect($cek)->contains(fn ($c) => $c['status'] === 'gagal');

        $terjemahanAksi = [
            'kelas.penyiapan_tahunan' => 'Penyiapan Rombel Tahunan',
            'cadangan.manual_create' => 'Pembuatan Cadangan Manual',
            'cadangan.hapus' => 'Penghapusan Arsip Cadangan',
            'cadangan.unduh' => 'Pengunduhan Berkas Cadangan',
            'cadangan.ekspor_json' => 'Ekspor Cadangan JSON',
            'cadangan.ekspor_xlsx' => 'Ekspor Cadangan Excel',
            'pemulihan.uji_staging' => 'Uji Pemulihan Staging',
            'pemulihan.persetujuan_administratif' => 'Persetujuan Hasil Staging',
            'alumni.perbarui' => 'Pembaruan Data Alumni',
            'pengguna.tambah' => 'Penambahan Akun Pengguna',
            'pengguna.perbarui' => 'Pembaruan Profil Akun',
            'pengguna.hapus' => 'Penonaktifan Akun',
            'academic_workflow_coordinator' => 'Transisi Siswa Terpadu',
            'workflow_coordinator.create' => 'Pembuatan Sesi Transisi Terpadu',
            'tahun_ajaran.siapkan' => 'Penyiapan Draf Tahun Ajaran',
            'riwayat_kelas.backfill' => 'Sinkronisasi Riwayat Kelas Siswa',
            'transisi_tahun_ajaran' => 'Aktivasi Tahun Ajaran',
        ];
    @endphp

    <x-page-head
        title="Sistem & Log"
        :sub="$adaGagal ? 'Ada komponen server yang memerlukan tindakan segera' : 'Seluruh layanan database, penyimpanan, dan aplikasi berjalan stabil'">
        <x-chip :tone="$adaGagal ? 'red' : 'green'" :label="$adaGagal ? 'Perlu tindakan' : 'Layanan Stabil'" />
    </x-page-head>

    {{-- 1. Status Komponen Server (Bento Grid Hi-Fi) --}}
    <x-card title="Status Komponen Server & Lingkungan" class="mb-4">
        <x-slot:actions>
            <span class="card-hifi__meta">Diperiksa otomatis {{ now()->translatedFormat('d/m/Y H:i') }}</span>
        </x-slot:actions>

        <div class="cek-grid">
            @foreach ($cek as $item)
                @php
                    $ikonKomponen = match(strtolower($item['nama'])) {
                        'database' => 'database',
                        'migrasi' => 'arrow-repeat',
                        'cache' => 'lightning',
                        'storage' => 'hdd',
                        'berkas log' => 'file-text',
                        'db objek' => 'gear',
                        'app_debug' => 'bug',
                        'app_url' => 'globe',
                        'cache config' => 'check-circle',
                        default => 'cpu',
                    };
                @endphp
                <div class="cek-card">
                    <div class="cek-card__atas">
                        <span class="cek-card__nama">
                            <x-ikon :nama="$ikonKomponen" class="text-secondary" /> {{ $item['nama'] }}
                        </span>
                        <x-chip :tone="$tone[$item['status']] ?? 'neutral'" :label="$label[$item['status']] ?? $item['status']" />
                    </div>
                    <div class="cek-card__nilai">{{ $item['nilai'] }}</div>
                    <div class="cek-card__detail">{{ $item['detail'] }}</div>
                </div>
            @endforeach
        </div>
    </x-card>

    {{-- 2. Pengingat Administrasi Data Tahunan --}}
    <x-card title="Pengingat Siklus Administrasi Tahunan" class="mb-4 border-l-4" style="border-left-color: var(--primary, #3b5d32);">
        <x-slot:actions>
            <x-chip :tone="$pengingat['tone']" :label="$pengingat['label']" />
        </x-slot:actions>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div>
                <h6 class="fw-bold mb-1 text-dark" style="font-size: 15px;">{{ $pengingat['judul'] }}</h6>
                <p class="text-muted small mb-0" style="font-size: 13px;">{{ $pengingat['deskripsi'] }}</p>
            </div>
            <div class="d-flex gap-2 flex-wrap flex-shrink-0">
                @foreach ($pengingat['tautan'] as $t)
                    <a href="{{ $t['url'] }}" class="btn-hifi btn-hifi--ghost btn-hifi--sm">
                        <x-ikon :nama="$t['ikon']" /> {{ $t['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    </x-card>

    {{-- 3. Segmented Pill Tab Switcher (Tanpa Emoji / Ikon Bersih) --}}
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div class="segmented-pill-nav" role="tablist">
            <a class="segmented-pill-btn {{ $tabAktif === 'log-sistem' ? 'is-active' : '' }}"
               href="{{ request()->fullUrlWithQuery(['tab' => 'log-sistem']) }}">
                <x-ikon nama="exclamation-circle" /> Laporan Error Pengguna ({{ $jumlahBaru }} Baru)
            </a>
            <a class="segmented-pill-btn {{ $tabAktif === 'aktivitas-admin' ? 'is-active' : '' }}"
               href="{{ request()->fullUrlWithQuery(['tab' => 'aktivitas-admin']) }}">
                <x-ikon nama="shield-lock" /> Aktivitas Admin &amp; Audit Log ({{ number_format($auditLogs->total(), 0, ',', '.') }})
            </a>
        </div>
    </div>

    @if ($tabAktif === 'aktivitas-admin')
        {{-- ========================================== --}}
        {{-- TAB AKTIVITAS ADMIN (AUDIT LOG TERSTRUKTUR) --}}
        {{-- ========================================== --}}
        <x-card title="Log Aktivitas & Audit Administratif" flush>
            <x-slot:actions>
                <span class="card-hifi__meta">
                    Total {{ number_format($auditLogs->total(), 0, ',', '.') }} rekaman audit tercatat
                </span>
            </x-slot:actions>

            {{-- Filter Bar Audit --}}
            <div class="card-hifi__body border-bottom" style="padding-bottom: 14px">
                <form class="filter-bar" method="GET">
                    <input type="hidden" name="tab" value="aktivitas-admin">

                    <label class="filter-bar__search">
                        <x-ikon nama="search" />
                        <input class="input-hifi" type="search" name="audit_q" value="{{ $filter['audit_q'] ?? '' }}"
                               placeholder="Cari deskripsi operasi, nama admin...">
                    </label>

                    @if ($pelaksanaAuditTersedia->isNotEmpty())
                        <select class="select-hifi" name="audit_user" style="width: 170px" onchange="this.form.submit()">
                            <option value="">Semua Pelaksana</option>
                            @foreach ($pelaksanaAuditTersedia as $p)
                                <option value="{{ $p }}" @selected(($filter['audit_user'] ?? null) === $p)>
                                    {{ $p }}
                                </option>
                            @endforeach
                        </select>
                    @endif

                    <select class="select-hifi" name="audit_status" style="width: 140px" onchange="this.form.submit()">
                        <option value="">Semua Status</option>
                        <option value="berhasil" @selected(($filter['audit_status'] ?? null) === 'berhasil')>Berhasil</option>
                        <option value="gagal" @selected(($filter['audit_status'] ?? null) === 'gagal')>Gagal</option>
                        <option value="diproses" @selected(($filter['audit_status'] ?? null) === 'diproses')>Diproses</option>
                    </select>

                    <input class="input-hifi" type="date" name="audit_dari" value="{{ $filter['audit_dari'] ?? '' }}"
                           title="Dari Tanggal" style="width: 140px" onchange="this.form.submit()">
                    <span class="text-muted small">s/d</span>
                    <input class="input-hifi" type="date" name="audit_sampai" value="{{ $filter['audit_sampai'] ?? '' }}"
                           title="Sampai Tanggal" style="width: 140px" onchange="this.form.submit()">

                    @if ($filter['audit_q'] ?? $filter['audit_user'] ?? $filter['audit_status'] ?? $filter['audit_dari'] ?? $filter['audit_sampai'] ?? false)
                        <a href="{{ route('admin.sistem.index', ['tab' => 'aktivitas-admin']) }}" class="btn-hifi btn-hifi--ghost btn-hifi--sm">
                            Reset Filter
                        </a>
                    @endif
                </form>
            </div>

            {{-- Tabel Audit --}}
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Waktu &amp; Tanggal</th>
                            <th>Pelaksana</th>
                            <th>Aktivitas Operasi</th>
                            <th class="is-num">Status</th>
                            <th>Deskripsi Tindakan</th>
                            <th class="is-num">Rincian</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($auditLogs as $idx => $logItem)
                            @php
                                $statusTone = match ($logItem->status) {
                                    'berhasil' => 'green',
                                    'gagal' => 'red',
                                    'diproses' => 'yellow',
                                    default => 'neutral',
                                };
                                $sanitized = $logItem->metadata_sanitasi ?? [];
                                $namaAksiHumanis = $terjemahanAksi[$logItem->aksi] ?? ucwords(str_replace(['.', '_'], ' ', $logItem->aksi));
                            @endphp
                            <tr>
                                <td class="is-muted is-nowrap" style="font-size: 12.5px;">
                                    {{ $logItem->created_at ? $logItem->created_at->format('d/m/Y H:i:s') : '—' }}
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width: 28px; height: 28px; border-radius: 6px; background: rgba(59, 93, 50, 0.1); color: var(--primary, #3b5d32); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 11px;">
                                            {{ strtoupper(substr($logItem->nama_user ?? 'S', 0, 1)) }}
                                        </div>
                                        <div>
                                            <span class="is-strong d-block" style="font-size: 13px;">{{ $logItem->nama_user ?? 'Sistem Otomatis' }}</span>
                                            <span class="text-muted small" style="font-size: 11px;">{{ ucfirst($logItem->role_user ?? 'Sistem') }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="fw-bold text-dark d-block" style="font-size: 13px;">{{ $namaAksiHumanis }}</span>
                                    <span class="text-muted font-monospace" style="font-size: 10.5px;">{{ $logItem->aksi }}</span>
                                </td>
                                <td class="is-num">
                                    <x-chip :tone="$statusTone" :label="ucfirst($logItem->status)" />
                                </td>
                                <td style="max-width: 320px; font-size: 12.5px;">
                                    {{ $logItem->deskripsi }}
                                </td>
                                <td class="is-num">
                                    <button type="button" class="btn-hifi btn-hifi--ghost btn-hifi--sm"
                                            data-bs-toggle="modal" data-bs-target="#modalAudit{{ $logItem->id }}">
                                        Rincian &rsaquo;
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="empty-state">Belum ada catatan aktivitas admin yang sesuai dengan filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($auditLogs->hasPages())
                <x-slot:foot>
                    <span>
                        Menampilkan {{ $auditLogs->firstItem() ?? 0 }}–{{ $auditLogs->lastItem() ?? 0 }}
                        dari {{ number_format($auditLogs->total(), 0, ',', '.') }} aktivitas
                    </span>
                    <x-pager :paginator="$auditLogs" />
                </x-slot:foot>
            @endif
        </x-card>

        {{-- Modal Dialogs untuk Rincian Audit Log --}}
        @foreach ($auditLogs as $logItem)
            @php
                $sanitized = $logItem->metadata_sanitasi ?? [];
                $namaAksiHumanis = $terjemahanAksi[$logItem->aksi] ?? ucwords(str_replace(['.', '_'], ' ', $logItem->aksi));
            @endphp
            <div class="modal fade" id="modalAudit{{ $logItem->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content card-hifi p-4">
                        <div class="d-flex justify-content-between align-items-center pb-3 border-bottom mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(59, 93, 50, 0.12); color: var(--primary, #3b5d32); display: flex; align-items: center; justify-content: center; font-size: 18px;">
                                    <x-ikon nama="shield-lock" />
                                </div>
                                <div>
                                    <h4 class="card-hifi__title mb-0" style="font-size: 16px !important;">Rincian Log Aktivitas Admin #{{ $logItem->id }}</h4>
                                    <p class="text-muted small mb-0">{{ $logItem->created_at ? $logItem->created_at->format('l, d F Y - H:i:s') : '—' }}</p>
                                </div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                        </div>

                        <div class="modal-body p-0">
                            <table class="kv-table mb-3">
                                <tbody>
                                    <tr><th>Nama Pelaksana</th><td><strong>{{ $logItem->nama_user ?? 'Sistem Otomatis' }}</strong> ({{ ucfirst($logItem->role_user ?? 'Sistem') }})</td></tr>
                                    <tr><th>Aktivitas Operasi</th><td>{{ $namaAksiHumanis }} <code class="small text-muted ms-1">({{ $logItem->aksi }})</code></td></tr>
                                    <tr><th>Status Hasil</th><td><x-chip :tone="$logItem->status === 'berhasil' ? 'green' : 'red'" :label="ucfirst($logItem->status)" /></td></tr>
                                    <tr><th>Entitas Terdampak</th><td>{{ $logItem->tipe_entitas }} @if($logItem->entitas_id) <span class="badge bg-light text-dark ms-1">ID: {{ $logItem->entitas_id }}</span> @endif</td></tr>
                                    <tr><th>Deskripsi Operasi</th><td>{{ $logItem->deskripsi }}</td></tr>
                                </tbody>
                            </table>

                            <h6 class="fw-bold mb-2 text-dark" style="font-size: 13.5px;">Parameter &amp; Metadata Operasi:</h6>
                            @if (! empty($sanitized))
                                <div class="table-responsive rounded border mb-3">
                                    <table class="kv-table mb-0">
                                        <tbody>
                                            @foreach ($sanitized as $k => $v)
                                                <tr>
                                                    <th>{{ ucwords(str_replace(['_', '-'], ' ', $k)) }}</th>
                                                    <td>
                                                        @if (is_array($v))
                                                            <pre class="mb-0 small bg-light p-2 rounded" style="font-size: 11px;">{{ json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                        @else
                                                            <span class="font-monospace">{{ (string) $v }}</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-muted small p-3 bg-light rounded mb-3">Tidak ada parameter metadata tambahan untuk aktivitas ini.</p>
                            @endif
                        </div>

                        <div class="d-flex justify-content-end pt-3 border-top">
                            <button type="button" class="btn-hifi btn-hifi--ghost" data-bs-dismiss="modal">Tutup</button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

    @else
        {{-- ========================================== --}}
        {{-- TAB LOG SISTEM & ERROR (DEFAULT)           --}}
        {{-- ========================================== --}}

        {{-- Laporan dari pengguna --}}
        <x-card title="Laporan Kendala & Error dari Pengguna" class="mb-4" flush>
            <x-slot:actions>
                <span class="card-hifi__meta">
                    {{ $jumlahBaru }} baru · {{ number_format($laporan->total(), 0, ',', '.') }} total laporan
                </span>
            </x-slot:actions>

            <div class="card-hifi__body border-bottom" style="padding-bottom: 14px">
                <form class="filter-bar" method="GET">
                    @if ($filter['level'] ?? null)
                        <input type="hidden" name="level" value="{{ $filter['level'] }}">
                    @endif
                    <select class="select-hifi" name="status" style="width: 170px" onchange="this.form.submit()">
                        <option value="">Semua Status Laporan</option>
                        @foreach (\App\Models\LaporanError::STATUS as $s)
                            <option value="{{ $s }}" @selected(($filter['status'] ?? null) === $s)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                    <span class="filter-bar__note text-muted small">Laporan dikirim otomatis oleh guru/siswa saat menemukan kendala</span>
                </form>
            </div>

            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Waktu Lapor</th>
                            <th>Pelapor</th>
                            <th>Ringkasan Keluhan Pengguna</th>
                            <th class="is-num">Frekuensi</th>
                            <th class="is-num">Status Penanganan</th>
                            <th class="is-num">Rincian &amp; Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($laporan as $item)
                            @php $chip = $item->statusChip(); @endphp
                            <tr>
                                <td class="is-muted is-nowrap" style="font-size: 12.5px;">{{ $item->created_at->format('d/m/Y H:i') }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width: 28px; height: 28px; border-radius: 6px; background: rgba(30, 58, 138, 0.1); color: #1e3a8a; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 11px;">
                                            {{ strtoupper(substr($item->pelapor?->nama ?? 'P', 0, 1)) }}
                                        </div>
                                        <div>
                                            <span class="is-strong d-block" style="font-size: 13px;">{{ $item->pelapor?->nama ?? 'Pengguna Tamu' }}</span>
                                            <span class="text-muted small" style="font-size: 11px;">{{ ucfirst($item->pelapor?->role ?? 'Anonim') }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td style="max-width: 380px; font-size: 12.5px;">
                                    <span class="text-dark fw-semibold d-block">{{ $item->pesan ? Str::limit($item->pesan, 75) : 'Tidak ada keterangan pesan pengguna' }}</span>
                                    <span class="text-muted small font-monospace" style="font-size: 11px;">Ref: {{ $item->ref }}</span>
                                </td>
                                <td class="is-num" style="font-size: 13px; font-weight: 600;">{{ $item->jumlah }}×</td>
                                <td class="is-num"><x-chip :tone="$chip['tone']" :label="$chip['label']" /></td>
                                <td class="is-num">
                                    <button type="button" class="btn-hifi btn-hifi--ghost btn-hifi--sm"
                                            data-bs-toggle="modal" data-bs-target="#modalLaporan{{ $item->id }}">
                                        Periksa Rincian &rsaquo;
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty-state">Belum ada laporan kendala dari pengguna.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-slot:foot>
                <span>
                    Menampilkan {{ $laporan->firstItem() ?? 0 }}–{{ $laporan->lastItem() ?? 0 }}
                    dari {{ number_format($laporan->total(), 0, ',', '.') }} laporan
                </span>
                <x-pager :paginator="$laporan" />
            </x-slot:foot>
        </x-card>

        {{-- Modal Dialogs untuk Laporan Error Pengguna --}}
        @foreach ($laporan as $item)
            @php $chip = $item->statusChip(); @endphp
            <div class="modal fade" id="modalLaporan{{ $item->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content card-hifi p-4">
                        <div class="d-flex justify-content-between align-items-center pb-3 border-bottom mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(185, 28, 28, 0.1); color: #b91c1c; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                                    <x-ikon nama="exclamation-circle" />
                                </div>
                                <div>
                                    <h4 class="card-hifi__title mb-0" style="font-size: 16px !important;">Rincian Laporan Kendala Ref #{{ $item->ref }}</h4>
                                    <p class="text-muted small mb-0">{{ $item->created_at->format('l, d F Y - H:i:s') }}</p>
                                </div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                        </div>

                        <div class="modal-body p-0">
                            <table class="kv-table mb-3">
                                <tbody>
                                    <tr><th>Nama Pelapor</th><td><strong>{{ $item->pelapor?->nama ?? 'Pengguna Tamu' }}</strong> ({{ ucfirst($item->pelapor?->role ?? 'Anonim') }})</td></tr>
                                    <tr><th>Frekuensi Kejadian</th><td>{{ $item->jumlah }} kali dialami pengguna</td></tr>
                                    <tr><th>URL Halaman Error</th><td><a href="{{ $item->url }}" target="_blank" class="text-break">{{ $item->url ?? '—' }}</a></td></tr>
                                    <tr><th>Pesan / Cerita Pengguna</th><td><div class="p-2 bg-light rounded text-dark">{{ $item->pesan ?? '—' }}</div></td></tr>
                                    <tr><th>Pesan Error Sistem</th><td><code class="text-danger fw-semibold">{{ $item->exception_pesan ?? '—' }}</code></td></tr>
                                    @if ($item->exception_file)
                                        <tr><th>Lokasi Berkas Teknis</th><td><span class="font-monospace small">{{ $item->exception_file }}:{{ $item->exception_line }}</span></td></tr>
                                    @endif
                                </tbody>
                            </table>

                            <div class="p-3 rounded mb-3" style="background: rgba(0,0,0,0.03);">
                                <form method="POST" action="{{ route('admin.sistem.laporan.status', $item) }}" class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    @csrf
                                    <div>
                                        <span class="fw-bold text-dark d-block" style="font-size: 13px;">Ubah Status Penanganan:</span>
                                        <span class="text-muted small" style="font-size: 11.5px;">Perbarui status tiket tindak lanjut laporan ini.</span>
                                    </div>
                                    <div class="d-flex gap-2 align-items-center">
                                        <select class="select-hifi" name="status" style="width: 140px">
                                            @foreach (\App\Models\LaporanError::STATUS as $s)
                                                <option value="{{ $s }}" @selected($item->status === $s)>{{ ucfirst($s) }}</option>
                                            @endforeach
                                        </select>
                                        <button class="btn-hifi btn-hifi--sm" type="submit">Simpan Status</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end pt-3 border-top">
                            <button type="button" class="btn-hifi btn-hifi--ghost" data-bs-dismiss="modal">Tutup</button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        {{-- 4. Pengumuman & Log Berkas Aplikasi --}}
        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <x-card title="Tayangkan Banner Pengumuman Sekolah">
                    <p class="field__hint mb-3" style="font-size: 12.5px;">
                        Pesan banner ini akan langsung muncul di halaman guru dan siswa untuk pemberitahuan libur, pemeliharaan, atau agenda penting sekolah.
                    </p>

                    <form method="POST" action="{{ route('admin.sistem.pengumuman.simpan') }}" class="form-grid">
                        @csrf
                        <div class="field mb-3">
                            <label class="field__label mb-1" for="pesanPengumuman">Isi Pesan Pengumuman<span class="field__req"> *</span></label>
                            <textarea class="input-hifi w-100" id="pesanPengumuman" name="pesan" rows="3" maxlength="500" required
                                      placeholder="Contoh: Pembagian rapor semester gasal dijadwalkan hari Jumat pukul 08.00 WIB."></textarea>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="field__label mb-1" for="tipePengumuman">Kategori Banner</label>
                                <select class="select-hifi w-100" id="tipePengumuman" name="tipe" required>
                                    <option value="info">Informasi Umum</option>
                                    <option value="peringatan">Peringatan Penting</option>
                                    <option value="maintenance">Jendela Pemeliharaan</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="field__label mb-1" for="selesaiPengumuman">Berakhir Otomatis (Opsional)</label>
                                <input class="input-hifi w-100" id="selesaiPengumuman" type="datetime-local" name="selesai">
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button class="btn-hifi" type="submit">
                                <x-ikon nama="broadcast" /> Tayangkan Pengumuman
                            </button>
                        </div>
                    </form>
                </x-card>
            </div>

            <div class="col-lg-6">
                <x-card title="Riwayat Banner Pengumuman" flush>
                    <div class="tbl-wrap">
                        <table class="tbl">
                            <thead>
                                <tr><th>Isi Pengumuman</th><th>Kategori</th><th>Batas Waktu</th><th class="is-num">Status</th><th class="is-num">Aksi</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($pengumuman as $item)
                                    <tr>
                                        <td style="font-size: 12.5px;">{{ Str::limit($item->pesan, 45) }}</td>
                                        <td class="is-muted small">{{ ucfirst($item->tipe) }}</td>
                                        <td class="is-muted is-nowrap small">{{ $item->selesai?->format('d/m H:i') ?? 'Manual' }}</td>
                                        <td class="is-num">
                                            <x-chip :tone="$item->aktif ? 'green' : 'neutral'"
                                                    :label="$item->aktif ? 'Tayang' : 'Mati'" />
                                        </td>
                                        <td class="is-num">
                                            <form method="POST" action="{{ route('admin.sistem.pengumuman.alih', $item) }}">
                                                @csrf
                                                <button class="btn-hifi btn-hifi--ghost btn-hifi--sm" type="submit">
                                                    {{ $item->aktif ? 'Hentikan' : 'Tayangkan' }}
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="empty-state">Belum ada banner pengumuman tercatat.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-card>
            </div>
        </div>

        {{-- 5. Log Berkas Laravel --}}
        <x-card title="Log Berkas Server &amp; Laravel Terbaru" flush>
            <x-slot:actions>
                <span class="card-hifi__meta">
                    Ukuran Berkas: {{ number_format($log['ukuran'] / 1024, 0, ',', '.') }} KB
                    {{ $log['terpotong'] ? '· Menampilkan 100 entri terakhir' : '' }}
                </span>
            </x-slot:actions>

            <div class="card-hifi__body border-bottom" style="padding-bottom: 14px">
                <form class="filter-bar" method="GET">
                    @if ($filter['status'] ?? null)
                        <input type="hidden" name="status" value="{{ $filter['status'] }}">
                    @endif
                    <select class="select-hifi" name="level" style="width: 170px" onchange="this.form.submit()">
                        <option value="">Semua Tingkat Level</option>
                        @foreach ($levelTersedia as $lv)
                            <option value="{{ $lv }}" @selected(($filter['level'] ?? null) === $lv)>Level {{ $lv }}</option>
                        @endforeach
                    </select>
                    <span class="filter-bar__note text-muted small">{{ count($log['entri']) }} entri log terbaca</span>
                </form>
            </div>

            <div class="card-hifi__body">
                @forelse ($log['entri'] as $entri)
                    <div class="log-entri">
                        <div class="log-entri__atas">
                            <x-chip :tone="\App\Support\PembacaLog::tone($entri['level'])" :label="$entri['level']" />
                            <span class="log-entri__waktu">{{ $entri['waktu'] }} · Lingkungan: {{ $entri['lingkungan'] }}</span>
                        </div>
                        <div class="log-entri__pesan">{{ $entri['pesan'] }}</div>
                        <details>
                            <summary class="text-primary small cursor-pointer fw-semibold">Lihat Rincian Stack Trace Lengkap</summary>
                            <pre class="audit-pre">{{ $entri['lengkap'] }}</pre>
                        </details>
                    </div>
                @empty
                    <p class="empty-state">Tidak ada entri log tercatat pada berkas.</p>
                @endforelse
            </div>

            <x-slot:foot>
                <span>Hanya sebagian akhir berkas log yang dimuat agar performa server tetap cepat.</span>
                <form method="POST" action="{{ route('admin.sistem.log.bersihkan') }}"
                      data-konfirmasi="Bersihkan seluruh isi berkas log server? Tindakan ini tidak dapat dibatalkan.">
                    @csrf
                    <button class="btn-hifi btn-hifi--ghost btn-hifi--sm text-danger" type="submit">
                        <x-ikon nama="trash" /> Bersihkan Berkas Log
                    </button>
                </form>
            </x-slot:foot>
        </x-card>
    @endif
@endsection
