@extends('layouts.app')
@section('title', 'Kenaikan & Kelulusan')
@push('styles')
    <style>
        .transition-period {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto;
            gap: 16px;
            align-items: end
        }

        .transition-period label {
            display: block;
            margin-bottom: 6px;
            font-size: 15px;
            font-weight: 600
        }

        .transition-period select {
            width: 100%;
            min-height: 44px
        }

        .transition-page .btn-hifi {
            min-height: 44px;
            white-space: normal
        }

        .transition-roster {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 24px;
            border-top: 1px solid var(--n-200, #e2e8f0);
            padding-top: 14px;
            margin-top: 16px;
            font-size: 14px
        }

        .transition-step {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 24px
        }

        .transition-step p {
            margin: 0;
            font-size: 15px;
            line-height: 1.6
        }

        .transition-note {
            padding: 12px 16px;
            margin-top: 16px;
            background: #fefce8;
            color: #713f12;
            border-left: 3px solid #ca8a04;
            line-height: 1.6
        }

        .transition-page summary {
            cursor: pointer;
            padding: 10px 0;
            min-height: 44px;
            font-size: 15px;
            font-weight: 600
        }

        .transition-page :is(button, a, select, summary):focus-visible {
            outline: 2px solid #15803d;
            outline-offset: 3px
        }

        .transition-check {
            padding: 14px 0;
            border-top: 1px solid var(--n-200, #e2e8f0)
        }

        .transition-check p {
            font-size: 14px;
            line-height: 1.6;
            margin: 6px 0
        }

        .transition-check small {
            display: block
        }

        .transition-check .btn-hifi {
            margin-left: 12px
        }

        @media(max-width:767.98px) {
            .transition-period {
                grid-template-columns: minmax(0, 1fr)
            }

            .transition-step {
                flex-direction: column;
                align-items: stretch;
                gap: 14px
            }

            .transition-step .btn-hifi {
                width: 100%
            }
        }

        .transition-page {
            max-width: 1200px
        }

        .transition-page .btn-hifi:disabled {
            opacity: .45;
            cursor: not-allowed;
            filter: grayscale(.5)
        }

        .transition-page .card-hifi__head {
            padding-top: 12px;
            padding-bottom: 8px
        }

        .transition-page .card-hifi__body {
            padding-top: 12px;
            padding-bottom: 14px
        }

        .transition-note {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            font-size: 14px
        }

        .transition-note>div {
            margin-top: 0 !important;
            flex-shrink: 0
        }

        .transition-page .card-hifi__title {
            font-size: 15px
        }

        .transition-page .transition-step p {
            font-size: 14.5px
        }

        @media(max-width:767.98px) {
            .transition-note {
                display: block
            }

            .transition-note>div {
                margin-top: 10px !important
            }

            .transition-period {
                gap: 12px
            }

            .transition-page .card-hifi__body {
                padding: 12px 16px
            }

            .transition-page .card-hifi__head {
                padding: 12px 16px 6px
            }
        }
    </style>
@endpush
@section('content')
    <div class="transition-page space-y-4">
        {{-- Breadcrumb Navigasi --}}
        <nav class="breadcrumb-nav mb-2" aria-label="Breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.tahun-ajaran.index') }}">Tahun Ajaran</a></li>
                <li class="breadcrumb-item active" aria-current="page">Kenaikan & Kelulusan (Legacy)</li>
            </ol>
        </nav>

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

        <x-page-head title="Kenaikan & Kelulusan"
            sub="Siapkan kelas tujuan, periksa simulasi, lalu proses setelah dikonfirmasi.">
            @if ($tahunAktif)
            <span>Tahun berjalan: <x-chip tone="green" :label="$tahunAktif->kode" /></span>@else<x-chip tone="red"
                    label="Belum ada tahun aktif" />
            @endif
        </x-page-head>
        <x-card title="Periode Transisi" class="mb-3">
            <form method="GET" action="{{ route('admin.transisi.index') }}" class="transition-period">
                <div><label for="tahun_asal">Tahun Ajaran Asal</label><select name="tahun_asal" id="tahun_asal"
                        class="select-hifi" required
                        onchange="document.getElementById('tahun_tujuan').disabled=true;this.form.requestSubmit()">
                        @if (!$tahunAsalPilihan)
                            <option value="">Pilih tahun asal</option>
                        @endif
                        @foreach ($tahunList as $t)
                            <option value="{{ $t->kode }}" @selected($tahunAsalPilihan === $t->kode)>
                                {{ $t->kode }}{{ $t->aktif ? ' (Aktif)' : '' }}</option>
                        @endforeach
                    </select></div>
                <div><label for="tahun_tujuan">Tahun Ajaran Tujuan</label><select name="tahun_tujuan" id="tahun_tujuan"
                        class="select-hifi" @disabled(!$adaTahunTujuan)>
                        @if (!$adaTahunTujuan)
                            <option value="">Belum tersedia</option>
                        @endif
                        @foreach ($tahunTujuanList as $t)
                            <option value="{{ $t->kode }}" @selected($tahunTujuanPilihan === $t->kode)>{{ $t->kode }}</option>
                        @endforeach
                    </select></div><button type="submit" class="btn-hifi btn-hifi--ghost"
                    @disabled(!$tahunAsalPilihan)>Terapkan Periode</button>
            </form>
            @if (!$tahunAktif)
                <div class="transition-note">Tetapkan tahun ajaran aktif sebelum memproses transisi. <a
                        href="{{ route('admin.tahun-ajaran.index') }}">Kelola Tahun Ajaran</a></div>
            @elseif(!$adaTahunTujuan)
                <div class="transition-note"><span><strong>Tahun ajaran tujuan belum tersedia.</strong><br>Tanggal periode berikutnya dapat disesuaikan di menu Tahun Ajaran.</span><div class="mt-2"><a class="btn-hifi btn-hifi--ghost"
                            href="{{ route('admin.tahun-ajaran.index') }}">Siapkan Tahun Berikutnya</a></div>
                </div>
            @endif
            <div class="transition-roster"><span><strong>{{ number_format($statistik['total_siswa'], 0, ',', '.') }}</strong>
                    siswa aktif · {{ $statistik['tahun_kode'] }}</span><span>X:
                    {{ $statistik['tingkat_x'] }}</span><span>XI: {{ $statistik['tingkat_xi'] }}</span><span>XII:
                    {{ $statistik['tingkat_xii'] }} calon lulusan</span></div>
            <div class="transition-note mt-3">
                <div>
                    <strong>Alur Mandiri Kelulusan Kelas XII:</strong><br>
                    Kelulusan tingkat akhir dapat diproses mandiri berdasarkan berkas resmi sekolah (XLSX/CSV) tanpa menunggu kenaikan kelas X–XI.
                </div>
                <div>
                    <a href="{{ route('admin.kelulusan.index', ['tahun_asal' => $tahunAsalPilihan, 'tahun_tujuan' => $tahunTujuanPilihan]) }}" class="btn-hifi btn-hifi--primary">
                        Buka Kelulusan XII
                    </a>
                </div>
            </div>
        </x-card>
        @if ($transisiSelesai)
            <p class="alert alert-success">Transisi periode ini sudah selesai.</p>
        @endif
        @php
            $periodeLengkap = $tahunAktif && $tahunAsalPilihan && $tahunTujuanPilihan && !$transisiSelesai;
            $penghambat = collect($checklist['item'])->filter(fn($i) => $i['status'] === 'terhambat');
        @endphp
        <x-card title="1. Persiapan Kelas" class="mb-3">
            <div class="transition-step">
                <div>
                    <p>Siapkan kelas tujuan berdasarkan jurusan dan nomor kelas asal. Kelas yang tersedia tidak dibuat
                        ulang.</p>
                    @if ($periodeLengkap)
                        <p class="mt-2">
                            <strong>{{ $checklist['jumlah_terhambat'] === 0 ? 'Siap diperiksa melalui simulasi.' : 'Persiapan belum lengkap.' }}</strong>
                        </p>
                    @endif
                </div>
                <form method="POST" action="{{ route('admin.transisi.siapkan-kelas') }}" class="flex-shrink-0">@csrf
                    <input type="hidden" name="tahun_asal" value="{{ $tahunAsalPilihan }}"><input type="hidden"
                        name="tahun_tujuan" value="{{ $tahunTujuanPilihan }}">
                    <button type="submit" class="btn-hifi btn-hifi--ghost" @disabled(!$periodeLengkap)>Siapkan Kelas
                        Tujuan</button>
                </form>
            </div>
            @if ($periodeLengkap && $penghambat->isNotEmpty())
                <ul class="text-danger mt-3 mb-0" role="alert">
                    @foreach ($penghambat as $item)
                        <li>{{ $item['pesan'] }}</li>
                    @endforeach
                </ul>
            @endif
        </x-card>
        <x-card title="2. Simulasi Transisi" class="mb-3">
            <div class="transition-step">
                <p>Periksa penempatan siswa, calon lulusan, dan pengecualian. Membuka simulasi tidak mengubah data siswa.
                </p>
                <form method="GET" action="{{ route('admin.transisi.pratinjau') }}" class="flex-shrink-0"><input
                        type="hidden" name="tahun_asal" value="{{ $tahunAsalPilihan }}"><input type="hidden"
                        name="tahun_tujuan" value="{{ $tahunTujuanPilihan }}"><button type="submit" class="btn-hifi"
                        @disabled(!$periodeLengkap)>Buka Simulasi</button></form>
            </div>
        </x-card>
        <x-card class="mb-3">
            <details {{ request()->has('detail_kesiapan') ? 'open' : '' }}>
                <summary>Rincian Pemeriksaan · {{ count($checklist['item']) }} pemeriksaan</summary>
                @foreach ($checklist['item'] as $item)
                    <div class="transition-check">
                        <small>{{ $item['kategori'] }}</small><strong>{{ $item['kriteria'] }}</strong>
                        <p>{{ $item['pesan'] }}</p><x-chip :tone="$item['tone']" :label="$item['label']" />
                        @if ($item['tautan'])
                            <a class="btn-hifi btn-hifi--ghost btn-hifi--sm"
                                href="{{ $item['tautan'] }}">{{ $item['label_tautan'] ?? 'Buka' }}</a>
                        @endif
                    </div>
                @endforeach
            </details>
        </x-card>
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
