@extends('layouts.app')
@section('title', 'Kenaikan & Kelulusan')
@push('styles')
    <style>
        .transisi-hub-page .page-head {
            margin-top: 8px !important;
            margin-bottom: 24px !important;
        }
        .transisi-hub-page .page-head__title {
            font-size: 22px !important;
            font-weight: 700 !important;
            line-height: 1.25 !important;
            color: var(--text, #131313) !important;
            margin-bottom: 6px !important;
        }
        .transisi-hub-page .page-head__sub {
            font-size: 13.5px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
            color: var(--muted, #626262) !important;
            margin: 0 !important;
        }
        .transisi-hub-page .breadcrumb-nav {
            font-size: 13px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
        }
        .transisi-hub-page .breadcrumb-nav a {
            color: var(--muted, #626262) !important;
        }
        .transisi-hub-page .breadcrumb-nav a:hover {
            color: var(--primary, #3b5d32) !important;
        }
        .transisi-hub-page .kpi-card__num {
            font-size: 24px !important;
            font-weight: 700 !important;
            line-height: 1.2 !important;
            color: var(--text, #131313) !important;
        }
        .transisi-hub-page .kpi-card__label {
            font-size: 13px !important;
            font-weight: 500 !important;
            line-height: 1.3 !important;
            color: var(--muted, #626262) !important;
        }
        .transisi-hub-page .flow-card {
            border-radius: var(--radius-card, 12px);
            background: #ffffff;
            border: 1px solid var(--n-200, #e6e8e5);
            transition: all 0.2s ease;
        }
        .transisi-hub-page .flow-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.05);
        }
        .transisi-hub-page .flow-card--recommended {
            border: 1.5px solid var(--primary, #3b5d32);
            background: #fafbfa;
        }
        .transisi-hub-page .tbl th {
            font-size: 12.5px !important;
            font-weight: 600 !important;
            line-height: 1.4 !important;
            padding: 10px 14px !important;
        }
        .transisi-hub-page .tbl td {
            font-size: 13.5px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
            padding: 10px 14px !important;
        }
    </style>
@endpush
@section('content')
    <div class="transisi-hub-page transition-page space-y-4">
        {{-- Breadcrumb Navigasi --}}
        <nav class="breadcrumb-nav mb-2" aria-label="Breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.tahun-ajaran.index') }}">Tahun Ajaran</a></li>
                <li class="breadcrumb-item active" aria-current="page">Kenaikan & Kelulusan (Legacy)</li>
            </ol>
        </nav>

        {{-- Page Header --}}
        <div class="page-head d-flex justify-content-between align-items-start">
            <div>
                <h2 class="page-head__title">Transisi &amp; Siklus Akademik</h2>
                <p class="page-head__sub">
                    Pusat koordinasi pemindahan tahun ajaran, proses kelulusan kelas XII, promosi kenaikan kelas, dan penerimaan rombel baru.
                </p>
            </div>
            @if ($tahunAktif)
                <span class="chip chip--green">Tahun Aktif: {{ $tahunAktif->kode }}</span>
            @else
                <span class="chip chip--neutral">Belum Ada Tahun Aktif</span>
            @endif
        </div>

        {{-- Banner Edukasi & Pengarah Transisi Terpadu --}}
        <div class="card-hifi p-4 border-l-4 border-primary bg-primary-soft mb-3">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="chip chip--solid-lembut">Pemberitahuan Sistem</span>
                        <h4 class="h6 fw-bold mb-0 text-primary">Tersedia Alur Transisi Terpadu Terkoordinasi</h4>
                    </div>
                    <p class="text-muted small mb-0">
                        Halaman ini merupakan modul transisi manual periode lama. Untuk proses perpindahan tahun ajaran terkoordinasi (Kelulusan XII &rarr; Kenaikan X–XI &rarr; Penerimaan Siswa Baru) dengan transaksi atomik dan checkpoint audit terpisah per tahap, gunakan <strong>Transisi Terpadu</strong>.
                    </p>
                </div>
                <div class="flex-shrink-0">
                    <a href="{{ route('admin.transisi-terpadu.index') }}" class="btn-hifi btn-hifi--sm">
                        Buka Transisi Terpadu &rarr;
                    </a>
                </div>
            </div>
        </div>

        {{-- Bento Baris 1: Filter Periode (Kiri) + 4 Stat Card Siswa Aktif (Kanan) --}}
        <div style="display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr); gap: 16px; align-items: stretch;" class="mb-4">
            {{-- Kartu Filter Periode --}}
            <div class="card-hifi d-flex flex-column justify-content-between p-4">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <x-ikon nama="calendar3" />
                        <h3 class="card-hifi__title mb-0" style="font-size: 18px; font-weight: 600;">Periode Transisi Akademik</h3>
                    </div>
                    <p class="field__hint mb-3" style="font-size: 13px; color: var(--muted, #626262);">
                        Pilih tahun ajaran dasar yang akan dijadikan acuan data statistik dan siklus mutasi.
                    </p>

                    <form method="GET" action="{{ route('admin.transisi.index') }}" id="formPeriodeHub">
                        <div class="form-grid form-grid--2 mb-2">
                            <div class="field">
                                <label class="field__label" for="tahun_asal" style="font-size: 13.5px; font-weight: 600;">Tahun Ajaran Asal</label>
                                <select name="tahun_asal" id="tahun_asal" class="select-hifi" onchange="document.getElementById('formPeriodeHub').submit()" required>
                                    @if (!$tahunAsalPilihan)
                                        <option value="">Pilih tahun asal</option>
                                    @endif
                                    @foreach ($tahunList as $t)
                                        <option value="{{ $t->kode }}" @selected($tahunAsalPilihan === $t->kode)>
                                            {{ $t->kode }}{{ $t->aktif ? ' (Aktif)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label class="field__label" for="tahun_tujuan" style="font-size: 13.5px; font-weight: 600;">Tahun Ajaran Tujuan</label>
                                <select name="tahun_tujuan" id="tahun_tujuan" class="select-hifi" @disabled(!$adaTahunTujuan) onchange="document.getElementById('formPeriodeHub').submit()">
                                    @if (!$adaTahunTujuan)
                                        <option value="">Belum tersedia</option>
                                    @endif
                                    @foreach ($tahunTujuanList as $t)
                                        <option value="{{ $t->kode }}" @selected($tahunTujuanPilihan === $t->kode)>
                                            {{ $t->kode }} (Mendatang)
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="pt-3 border-top d-flex align-items-center gap-2 text-muted" style="font-size: 13px;">
                    <x-ikon nama="info-circle" />
                    <span>Statistik di samping mencerminkan populasi siswa aktif pada tahun asal terpilih ({{ $statistik['tahun_kode'] }}).</span>
                </div>
            </div>

            {{-- 4 Kartu Metrik Siswa: Total, X, XI, XII --}}
            <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; align-content: stretch;">
                {{-- Total Siswa --}}
                <div class="card-hifi p-3 d-flex align-items-center gap-3">
                    <div style="width: 44px; height: 44px; border-radius: 8px; background: rgba(59, 93, 50, 0.12); color: var(--primary, #3b5d32); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 18px;">
                        <x-ikon nama="people" />
                    </div>
                    <div>
                        <div class="kpi-card__num">{{ number_format($statistik['total_siswa'], 0, ',', '.') }}</div>
                        <div class="kpi-card__label">Total Siswa Aktif</div>
                    </div>
                </div>

                {{-- Siswa Kelas X --}}
                <div class="card-hifi p-3 d-flex align-items-center gap-3">
                    <div style="width: 44px; height: 44px; border-radius: 8px; background: rgba(59, 93, 50, 0.12); color: var(--primary, #3b5d32); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 18px;">
                        <x-ikon nama="person-check" />
                    </div>
                    <div>
                        <div class="kpi-card__num">{{ number_format($statistik['tingkat_x'], 0, ',', '.') }}</div>
                        <div class="kpi-card__label">Siswa Kelas X</div>
                    </div>
                </div>

                {{-- Siswa Kelas XI --}}
                <div class="card-hifi p-3 d-flex align-items-center gap-3">
                    <div style="width: 44px; height: 44px; border-radius: 8px; background: rgba(59, 93, 50, 0.12); color: var(--primary, #3b5d32); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 18px;">
                        <x-ikon nama="person-check" />
                    </div>
                    <div>
                        <div class="kpi-card__num">{{ number_format($statistik['tingkat_xi'], 0, ',', '.') }}</div>
                        <div class="kpi-card__label">Siswa Kelas XI</div>
                    </div>
                </div>

                {{-- Calon Lulusan (Kelas XII) --}}
                <div class="card-hifi p-3 d-flex align-items-center gap-3">
                    <div style="width: 44px; height: 44px; border-radius: 8px; background: rgba(59, 93, 50, 0.12); color: var(--primary, #3b5d32); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 18px;">
                        <x-ikon nama="mortarboard" />
                    </div>
                    <div>
                        <div class="kpi-card__num">{{ number_format($statistik['tingkat_xii'], 0, ',', '.') }}</div>
                        <div class="kpi-card__label">Calon Lulusan (XII)</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Bento Baris 2: Empat Kartu Utama Alur Transisi Akademik --}}
        <div>
            <div class="d-flex justify-content-between align-items-end mb-3">
                <div>
                    <h3 class="card-hifi__title mb-1" style="font-size: 18px; font-weight: 600;">Pilih Alur Operasi Akademik</h3>
                    <p class="text-muted small mb-0">
                        Jalankan modul secara mandiri per tingkat atau gunakan orkestrasi terpadu untuk alur otomatis 2-3 tahap sekaligus.
                    </p>
                </div>
            </div>

            <div class="grid-row grid-row--4" style="gap: 16px;">
                {{-- Alur 1: Kelulusan XII --}}
                <div class="flow-card p-4 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="chip chip--neutral fw-bold">Alur Mandiri</span>
                            <div style="font-size: 20px; color: var(--primary, #3b5d32);">
                                <x-ikon nama="mortarboard" />
                            </div>
                        </div>
                        <h4 class="fw-bold mb-2" style="font-size: 16px; color: var(--text, #131313);">Kelulusan Kelas XII</h4>
                        <p class="text-muted mb-3" style="font-size: 13px; line-height: 1.5;">
                            Pencatatan status alumni bagi siswa tingkat akhir, penomoran kelulusan, dan pengarsipan akun akademik secara permanen.
                        </p>
                    </div>
                    <div class="pt-3 border-top">
                        <a href="{{ route('admin.kelulusan.index', ['tahun_asal' => $tahunAsalPilihan, 'tahun_tujuan' => $tahunTujuanPilihan]) }}" class="btn-hifi btn-hifi--ghost btn-hifi--sm w-100 text-center justify-content-center">
                            Buka Kelulusan &rarr;
                        </a>
                    </div>
                </div>

                {{-- Alur 2: Kenaikan X–XI --}}
                <div class="flow-card p-4 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="chip chip--neutral fw-bold">Alur Mandiri</span>
                            <div style="font-size: 20px; color: var(--primary, #3b5d32);">
                                <x-ikon nama="arrow-up-circle" />
                            </div>
                        </div>
                        <h4 class="fw-bold mb-2" style="font-size: 16px; color: var(--text, #131313);">Kenaikan Kelas X–XI</h4>
                        <p class="text-muted mb-3" style="font-size: 13px; line-height: 1.5;">
                            Promosi siswa tingkat X ke XI dan XI ke XII, alokasi rombel baru, rolling presensi, serta penanganan tinggal kelas.
                        </p>
                    </div>
                    <div class="pt-3 border-top">
                        <a href="{{ route('admin.kenaikan.index', ['tahun_asal' => $tahunAsalPilihan, 'tahun_tujuan' => $tahunTujuanPilihan]) }}" class="btn-hifi btn-hifi--ghost btn-hifi--sm w-100 text-center justify-content-center">
                            Buka Kenaikan &rarr;
                        </a>
                    </div>
                </div>

                {{-- Alur 3: Penerimaan Siswa Baru --}}
                <div class="flow-card p-4 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="chip chip--neutral fw-bold">Alur Mandiri</span>
                            <div style="font-size: 20px; color: var(--primary, #3b5d32);">
                                <x-ikon nama="person-plus" />
                            </div>
                        </div>
                        <h4 class="fw-bold mb-2" style="font-size: 16px; color: var(--text, #131313);">Penerimaan Siswa Baru</h4>
                        <p class="text-muted mb-3" style="font-size: 13px; line-height: 1.5;">
                            Pendaftaran rombel masuk kelas X, pembagian rombel awal, validasi identitas (NIS/NISN), dan aktivasi akun siswa.
                        </p>
                    </div>
                    <div class="pt-3 border-top">
                        <a href="{{ route('admin.intake.index', ['tahun_tujuan' => $tahunTujuanPilihan]) }}" class="btn-hifi btn-hifi--ghost btn-hifi--sm w-100 text-center justify-content-center">
                            Buka Penerimaan &rarr;
                        </a>
                    </div>
                </div>

                {{-- Alur 4: Transisi Terpadu (Orkestrator Gabungan) --}}
                <div class="flow-card flow-card--recommended p-4 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="chip chip--green fw-bold">Rekomendasi Terpadu</span>
                            <div style="font-size: 20px; color: var(--primary, #3b5d32);">
                                <x-ikon nama="diagram-3" />
                            </div>
                        </div>
                        <h4 class="fw-bold mb-2 text-primary" style="font-size: 16px;">Transisi Terpadu</h4>
                        <p class="text-muted mb-3" style="font-size: 13px; line-height: 1.5;">
                            Orkestrasi gabungan 2–3 alur berurutan dalam satu transaksi terkontrol dengan checkpoint audit, log bertahap, dan pengamanan atomik.
                        </p>
                    </div>
                    <div class="pt-3 border-top">
                        <a href="{{ route('admin.transisi-terpadu.index') }}" class="btn-hifi btn-hifi--sm w-100 text-center justify-content-center">
                            Buka Transisi Terpadu &rarr;
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Bagian Alur Legacy (Collapsible untuk arsip simulasi) --}}
        <div class="card-hifi mb-4">
            <details>
                <summary class="p-4 cursor-pointer fw-bold text-muted d-flex justify-content-between align-items-center" style="font-size: 14.5px;">
                    <span>Alat Bantu Persiapan Kelas &amp; Simulasi Transisi Lama (Legacy)</span>
                    <span class="chip chip--neutral" style="font-size: 12px;">Klik untuk Membuka</span>
                </summary>
                <div class="p-4 pt-0 border-top">
                    @php
                        $periodeLengkap = $tahunAktif && $tahunAsalPilihan && $tahunTujuanPilihan && !$transisiSelesai;
                        $penghambat = collect($checklist['item'])->filter(fn($i) => $i['status'] === 'terhambat');
                    @endphp

                    <div class="grid-row grid-row--2 mb-3 mt-3" style="gap: 16px;">
                        <div class="card-hifi p-3 border">
                            <h5 class="fw-bold mb-1" style="font-size: 15px;">1. Persiapan Kelas Tujuan</h5>
                            <p class="text-muted small mb-3">
                                Siapkan kelas tujuan berdasarkan jurusan dan nomor kelas asal. Kelas yang sudah ada tidak dibuat ulang.
                            </p>
                            <form method="POST" action="{{ route('admin.transisi.siapkan-kelas') }}">
                                @csrf
                                <input type="hidden" name="tahun_asal" value="{{ $tahunAsalPilihan }}">
                                <input type="hidden" name="tahun_tujuan" value="{{ $tahunTujuanPilihan }}">
                                <button type="submit" class="btn-hifi btn-hifi--ghost btn-hifi--sm" @disabled(!$periodeLengkap)>
                                    Siapkan Kelas Tujuan
                                </button>
                            </form>
                        </div>

                        <div class="card-hifi p-3 border">
                            <h5 class="fw-bold mb-1" style="font-size: 15px;">2. Simulasi Transisi</h5>
                            <p class="text-muted small mb-3">
                                Periksa penempatan siswa, calon lulusan, dan pengecualian. Membuka simulasi tidak mengubah data siswa.
                            </p>
                            <form method="GET" action="{{ route('admin.transisi.pratinjau') }}">
                                <input type="hidden" name="tahun_asal" value="{{ $tahunAsalPilihan }}">
                                <input type="hidden" name="tahun_tujuan" value="{{ $tahunTujuanPilihan }}">
                                <button type="submit" class="btn-hifi btn-hifi--sm" @disabled(!$periodeLengkap)>
                                    Buka Simulasi Transisi
                                </button>
                            </form>
                        </div>
                    </div>

                    @if ($periodeLengkap && $penghambat->isNotEmpty())
                        <div class="alert alert-danger p-3 mb-3 small" role="alert">
                            <ul class="mb-0 ps-3">
                                @foreach ($penghambat as $item)
                                    <li>{{ $item['pesan'] }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </details>
        </div>
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
                                <td class="is-strong" style="font-size: 13.5px">{{ $tr->tahun_asal }} &rarr;
                                    {{ $tr->tahun_tujuan }}</td>
                                <td class="is-num" style="font-size: 13.5px">
                                    {{ number_format($tr->jumlah_siswa_dipromosikan, 0, ',', '.') }}</td>
                                <td class="is-num" style="font-size: 13.5px">
                                    {{ number_format($tr->jumlah_siswa_lulus, 0, ',', '.') }}</td>
                                <td class="is-num" style="font-size: 13.5px">
                                    {{ number_format($tr->jumlah_siswa_tinggal, 0, ',', '.') }}</td>
                                <td class="is-num"><x-chip :tone="$tone" :label="ucfirst($tr->status)" /></td>
                                <td class="is-muted small" style="max-width: 250px; word-break: break-word">
                                    {{ Str::limit($tr->catatan, 60) }}</td>
                                <td class="is-muted" style="font-size: 13px">
                                    {{ $tr->diprosesOleh?->nama ?? ($tr->diprosesOleh?->username ?? 'Sistem') }}</td>
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
            {{ $transisiList->links() }}
        </x-card>
    </div>
@endsection
