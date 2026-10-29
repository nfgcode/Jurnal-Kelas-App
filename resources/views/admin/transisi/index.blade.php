@extends('layouts.app')

@section('title', 'Kenaikan & Kelulusan')

@push('styles')
<style>
    /* Senior-Friendly Typography, Spacing & Touch Targets */
    .transisi-banner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 14px 18px;
        background: #fefce8;
        border: 1px solid #fef08a;
        border-left: 4px solid #ca8a04;
        border-radius: var(--radius-sm, 6px);
    }
    .transisi-label {
        font-size: 14.5px;
        font-weight: 600;
        color: var(--n-900, #0f172a);
        margin-bottom: 5px;
        display: block;
    }
    .transisi-desc {
        font-size: 14px;
        line-height: 1.5;
        color: var(--n-700, #475569);
        margin-bottom: 14px;
    }
    .transisi-select {
        min-height: 44px;
        font-size: 14.5px;
        border: 1px solid var(--n-300, #cbd5e1);
        border-radius: var(--radius-sm, 6px);
        padding: 8px 12px;
        width: 100%;
        background-color: #fff;
        color: var(--n-900, #0f172a);
    }
    .transisi-select:focus-visible {
        outline: 2px solid #16a34a;
        outline-offset: 1px;
    }
    .transisi-btn {
        min-height: 44px;
        font-size: 14.5px;
        font-weight: 600;
        padding: 9px 20px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        border-radius: var(--radius-sm, 6px);
        cursor: pointer;
        text-decoration: none;
    }
    .transisi-btn:disabled, .transisi-btn[disabled] {
        opacity: 0.55;
        cursor: not-allowed;
        pointer-events: none;
    }
    .transisi-btn:focus-visible {
        outline: 3px solid #2563eb;
        outline-offset: 2px;
    }
    .transisi-tag {
        font-size: 12px;
        font-weight: 600;
        color: var(--n-600, #64748b);
    }
    .transisi-summary-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 14px;
        background: #f8fafc;
        border: 1px solid var(--n-200, #e2e8f0);
        border-radius: var(--radius-sm, 6px);
        margin-bottom: 12px;
    }
    .transisi-details-summary {
        font-size: 14px;
        font-weight: 600;
        color: #2563eb;
        cursor: pointer;
        user-select: none;
        padding: 6px 0;
        display: inline-block;
    }
    .transisi-details-summary:hover {
        text-decoration: underline;
    }
    .transisi-cohort-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 12px;
        font-size: 13.5px;
    }

    @media (max-width: 767.98px) {
        .grid-row--4 {
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 10px !important;
        }
        .grid-row--2 {
            grid-template-columns: 1fr !important;
            gap: 14px !important;
        }
        .transisi-banner {
            flex-direction: column !important;
            align-items: flex-start !important;
        }
        .transisi-summary-bar {
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 10px !important;
        }
        .transisi-cohort-bar {
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 6px !important;
            width: 100% !important;
            padding-top: 8px !important;
            border-top: 1px dashed var(--n-200, #e2e8f0) !important;
        }
        .transisi-btn {
            width: 100% !important;
        }
    }
</style>
@endpush

@section('content')
    <x-page-head
        title="Kenaikan & Kelulusan"
        sub="Persiapan kelas, simulasi kenaikan, dan kelulusan siswa antar tahun ajaran.">
        @if ($tahunAktif)
            <div class="d-inline-flex align-items-center gap-2">
                <span class="field__label mb-0" style="font-size: 14px">Tahun Berjalan:</span>
                <x-chip tone="green" :label="$tahunAktif->kode . ' (Aktif)'" />
            </div>
        @else
            <x-chip tone="red" label="Belum Ada Tahun Aktif" />
        @endif
    </x-page-head>

    {{-- 1. Empty State Banner: Tampil hanya bila belum ada tahun tujuan --}}
    @if (! $adaTahunTujuan)
        <div class="transisi-banner mb-4" role="alert">
            <div>
                <h5 class="fw-bold mb-1 text-dark" style="font-size: 15px">Tahun ajaran tujuan belum tersedia.</h5>
                <p class="text-secondary mb-0" style="font-size: 13.5px">Tambahkan tahun ajaran baru untuk melanjutkan proses transisi.</p>
            </div>
            <a href="{{ route('admin.tahun-ajaran.index') }}" class="btn-hifi btn-hifi--sm transisi-btn flex-shrink-0" style="background: #ca8a04; color: #fff; border: 0">
                <x-ikon nama="plus-circle" /> Tambah Tahun Ajaran
            </a>
        </div>
    @endif

    {{-- 2. Ringkasan Siswa Aktif Tahun Berjalan (Clean KPI) --}}
    <div class="grid-row grid-row--4 mb-4">
        <x-stat label="Siswa Aktif" :value="number_format($statistik['total_siswa'], 0, ',', '.')" caption="seluruh kelas asal ({{ $statistik['tahun_kode'] }})" />
        <x-stat label="Tingkat X" :value="number_format($statistik['tingkat_x'], 0, ',', '.')" caption="calon naik ke XI" />
        <x-stat label="Tingkat XI" :value="number_format($statistik['tingkat_xi'], 0, ',', '.')" caption="calon naik ke XII" />
        <x-stat label="Tingkat XII" :value="number_format($statistik['tingkat_xii'], 0, ',', '.')" caption="calon lulusan" />
    </div>

    {{-- 3. Status Kesiapan Transisi Akademik (Task-Oriented Checklist) --}}
    @php
        $totalPemeriksaan = count($checklist['item']);
        $pemeriksaanTerpenuhi = $checklist['jumlah_siap'];
        $langkahBerikutnya = match ($checklist['status_keseluruhan']) {
            'belum_dikonfigurasi' => 'Tambahkan tahun ajaran tujuan baru pada menu Tahun Ajaran.',
            'terhambat' => 'Selesaikan ' . $checklist['jumlah_terhambat'] . ' masalah yang tertera sebelum memproses transisi.',
            'perlu_ditinjau' => 'Periksa catatan sebelum menjalankan simulasi.',
            'siap' => 'Buka simulasi transisi untuk memeriksa penempatan kelas dan kelulusan.',
            default => 'Lakukan persiapan data.',
        };
    @endphp

    <x-card title="Kesiapan Transisi Akademik" class="mb-4">
        <x-slot:actions>
            <div class="d-flex align-items-center gap-2">
                <span class="card-hifi__meta" style="font-size: 13.5px">
                    {{ $checklist['tahun_asal'] ?? '—' }} &rarr; {{ $checklist['tahun_tujuan'] ?? 'Belum Ditentukan' }}
                </span>
                <x-chip :tone="$checklist['tone_keseluruhan']" :label="$checklist['label_keseluruhan']" />
            </div>
        </x-slot:actions>

        {{-- Baris Status Utama Ringkas --}}
        <div class="transisi-summary-bar">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <span class="badge bg-success p-2" style="font-size: 13px">
                    {{ $pemeriksaanTerpenuhi }} dari {{ $totalPemeriksaan }} pemeriksaan terpenuhi
                </span>
                <span class="text-secondary small">
                    Langkah berikutnya: <strong class="text-dark">{{ $langkahBerikutnya }}</strong>
                </span>
            </div>

            <div class="transisi-cohort-bar">
                <span>Promosi X &rarr; XI: <x-chip :tone="$checklist['promosi_x_ke_xi']['tone']" :label="$checklist['promosi_x_ke_xi']['label']" /></span>
                <span>Promosi XI &rarr; XII: <x-chip :tone="$checklist['promosi_xi_ke_xii']['tone']" :label="$checklist['promosi_xi_ke_xii']['label']" /></span>
                <span>Kelulusan XII: <x-chip :tone="$checklist['kelulusan_xii']['tone']" :label="$checklist['kelulusan_xii']['label']" /></span>
            </div>
        </div>

        {{-- Pesan Pemblokir Ringkas (Bila Ada) --}}
        @if ($checklist['jumlah_terhambat'] > 0)
            <div class="alert alert-danger mb-3 p-3" role="alert" style="font-size: 14px">
                <strong>Perhatian:</strong> Terdapat {{ $checklist['jumlah_terhambat'] }} masalah yang harus diperbaiki sebelum kenaikan kelas atau kelulusan dapat diproses. Buka rincian di bawah untuk melihat petunjuk.
            </div>
        @endif

        {{-- Rincian Pemeriksaan (Progressive Disclosure yang Bersih) --}}
        <details class="mt-2" {{ ($checklist['jumlah_terhambat'] > 0 || request()->has('detail_kesiapan')) ? 'open' : '' }}>
            <summary class="transisi-details-summary mb-3">
                Lihat rincian pemeriksaan data ({{ $totalPemeriksaan }} kriteria penilaian)
            </summary>

            <div class="tbl-wrap mt-2">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th style="font-size: 13px">Kategori</th>
                            <th style="font-size: 13px">Kriteria Penilaian</th>
                            <th class="is-num" style="font-size: 13px">Status</th>
                            <th style="font-size: 13px">Penjelasan</th>
                            <th class="is-num" style="font-size: 13px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($checklist['item'] as $item)
                            <tr>
                                <td class="is-muted small">{{ $item['kategori'] }}</td>
                                <td class="is-strong" style="font-size: 13.5px">{{ $item['kriteria'] }}</td>
                                <td class="is-num">
                                    <x-chip :tone="$item['tone']" :label="$item['label']" />
                                </td>
                                <td class="small" style="max-width: 340px; word-break: break-word">
                                    {{ $item['pesan'] }}
                                    @if ($item['jumlah_terdampak'] !== null && $item['jumlah_terdampak'] > 0)
                                        <span class="badge bg-secondary ms-1">{{ $item['jumlah_terdampak'] }}</span>
                                    @endif
                                </td>
                                <td class="is-num">
                                    @if ($item['tautan'])
                                        <a href="{{ $item['tautan'] }}" class="btn-hifi btn-hifi--ghost btn-hifi--sm" style="font-size: 12.5px">
                                            {{ $item['label_tautan'] ?? 'Buka' }} &rarr;
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    </x-card>

    {{-- 4 & 5. Dua Kartu Tindakan Fungsional --}}
    <div class="grid-row grid-row--2 mb-4 align-items-stretch">
        {{-- LANGKAH 1: SIAPKAN KELAS TUJUAN --}}
        <x-card title="1. Siapkan Rombel Kelas Tujuan">
            <x-slot:actions>
                <span class="transisi-tag">Mengubah data kelas</span>
            </x-slot:actions>

            <p class="transisi-desc">
                Sistem akan menyiapkan kelas untuk tahun ajaran baru berdasarkan jurusan dan nomor kelas saat ini.
            </p>

            <form method="POST" action="{{ route('admin.transisi.siapkan-kelas') }}" class="form-grid">
                @csrf
                <div class="form-grid form-grid--2">
                    <div>
                        <label for="tahun_asal_siap" class="transisi-label">Tahun Ajaran Asal</label>
                        <select class="select-hifi transisi-select" name="tahun_asal" id="tahun_asal_siap" required>
                            @foreach ($tahunList as $t)
                                <option value="{{ $t->kode }}" @selected($t->aktif)>
                                    {{ $t->kode }} {{ $t->aktif ? '(Aktif)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="tahun_tujuan_siap" class="transisi-label">Tahun Ajaran Tujuan</label>
                        <select class="select-hifi transisi-select" name="tahun_tujuan" id="tahun_tujuan_siap" required @disabled(! $adaTahunTujuan)>
                            @if (! $adaTahunTujuan)
                                <option value="" disabled selected>Pilih Tahun Tujuan...</option>
                            @else
                                <option value="">Pilih Tahun Tujuan...</option>
                                @foreach ($tahunList as $t)
                                    @if (! $t->aktif)
                                        <option value="{{ $t->kode }}" @selected(($checklist['tahun_tujuan'] ?? '') === $t->kode)>
                                            {{ $t->kode }}
                                        </option>
                                    @endif
                                @endforeach
                            @endif
                        </select>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-3">
                    <button class="btn-hifi transisi-btn" type="submit" @disabled(! $adaTahunTujuan)>
                        <x-ikon nama="building" /> Siapkan Kelas Baru
                    </button>
                </div>
            </form>
        </x-card>

        {{-- LANGKAH 2: LIHAT SIMULASI TRANSIASI --}}
        <x-card title="2. Pratinjau & Eksekusi Transisi">
            <x-slot:actions>
                <span class="transisi-tag">Tidak mengubah data</span>
            </x-slot:actions>

            <p class="transisi-desc">
                Simulasikan penempatan kelas siswa dan kelulusan kelas XII sebelum diproses nyata.
            </p>

            <form method="GET" action="{{ route('admin.transisi.pratinjau') }}" class="form-grid">
                <div class="form-grid form-grid--2">
                    <div>
                        <label for="tahun_asal_sim" class="transisi-label">Tahun Ajaran Asal</label>
                        <select class="select-hifi transisi-select" name="tahun_asal" id="tahun_asal_sim" required>
                            @foreach ($tahunList as $t)
                                <option value="{{ $t->kode }}" @selected($t->aktif)>
                                    {{ $t->kode }} {{ $t->aktif ? '(Aktif)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="tahun_tujuan_sim" class="transisi-label">Tahun Ajaran Tujuan</label>
                        <select class="select-hifi transisi-select" name="tahun_tujuan" id="tahun_tujuan_sim" required @disabled(! $adaTahunTujuan)>
                            @if (! $adaTahunTujuan)
                                <option value="" disabled selected>Pilih Tahun Tujuan...</option>
                            @else
                                <option value="">Pilih Tahun Tujuan...</option>
                                @foreach ($tahunList as $t)
                                    @if (! $t->aktif)
                                        <option value="{{ $t->kode }}" @selected(($checklist['tahun_tujuan'] ?? '') === $t->kode)>
                                            {{ $t->kode }}
                                        </option>
                                    @endif
                                @endforeach
                            @endif
                        </select>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-3">
                    <button class="btn-hifi transisi-btn" type="submit" @disabled(! $adaTahunTujuan)>
                        <x-ikon nama="eye" /> Buka Simulasi Transisi &rarr;
                    </button>
                </div>
            </form>
        </x-card>
    </div>

    {{-- 6. Riwayat Transisi --}}
    <x-card title="Riwayat Transisi" flush>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th style="font-size: 13px">Waktu</th>
                        <th style="font-size: 13px">Periode</th>
                        <th class="is-num" style="font-size: 13px">Naik Kelas</th>
                        <th class="is-num" style="font-size: 13px">Lulus</th>
                        <th class="is-num" style="font-size: 13px">Tinggal</th>
                        <th class="is-num" style="font-size: 13px">Status</th>
                        <th style="font-size: 13px">Keterangan</th>
                        <th style="font-size: 13px">Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transisiList as $tr)
                        @php
                            $tone = match ($tr->status) {
                                'selesai' => 'green',
                                'diproses' => 'yellow',
                                'gagal' => 'red',
                                default => 'neutral',
                            };
                        @endphp
                        <tr>
                            <td class="is-muted is-nowrap" style="font-size: 13px">
                                {{ $tr->diproses_pada ? $tr->diproses_pada->format('d/m/Y H:i') : $tr->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="is-strong" style="font-size: 13.5px">{{ $tr->tahun_asal }} &rarr; {{ $tr->tahun_tujuan }}</td>
                            <td class="is-num" style="font-size: 13.5px">{{ number_format($tr->jumlah_siswa_dipromosikan, 0, ',', '.') }}</td>
                            <td class="is-num" style="font-size: 13.5px">{{ number_format($tr->jumlah_siswa_lulus, 0, ',', '.') }}</td>
                            <td class="is-num" style="font-size: 13.5px">{{ number_format($tr->jumlah_siswa_tinggal, 0, ',', '.') }}</td>
                            <td class="is-num"><x-chip :tone="$tone" :label="ucfirst($tr->status)" /></td>
                            <td class="is-muted small" style="max-width: 250px; word-break: break-word">{{ Str::limit($tr->catatan, 60) }}</td>
                            <td class="is-muted" style="font-size: 13px">{{ $tr->diprosesOleh?->nama ?? $tr->diprosesOleh?->username ?? 'Sistem' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-state" style="font-size: 14px; padding: 28px 16px">
                                Belum ada riwayat proses transisi antar tahun ajaran.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
