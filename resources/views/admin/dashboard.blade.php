@extends('layouts.app')

@section('title', 'Dashboard Admin')

@section('content')
    @php
        // The honest recorded total; the breakdown component guards its own
        // divide, so this figure needs no "?: 1" fudge that would print "1".
        $totalPresensi = array_sum($presensi);
        $nilaiPengisian = array_column($pengisian, 'value');
        // The chart's traffic light: every timetabled lesson should get a
        // journal, so the target is the lessons of an average school day — or
        // of a whole week once Ringkasan::harian() rolls a long range up weekly.
        $mingguan = $periode->jumlahHari() > 31;
        $hariSekolah = count(\App\Support\Ringkasan::HARI);
        $targetPengisian = $mingguan ? $kpi['jadwal'] : (int) round($kpi['jadwal'] / $hariSekolah);
        $hariTerisi = array_filter($nilaiPengisian);
        $rataPengisian = (int) round(array_sum($hariTerisi) / max(1, count($hariTerisi)));
        $sparkJurnal = $nilaiPengisian;
        // Master-data counts have no daily series behind them, so their tiles
        // carry a deliberately flat track rather than an invented trend.
        $datar = array_fill(0, 12, 0);
    @endphp

    <x-page-head
        title="Selamat Datang, {{ Auth::user()->nama }}!"
        sub="Ringkasan seluruh data sekolah · Semester Gasal {{ now()->year }}/{{ now()->year + 1 }} · {{ now()->translatedFormat('j F Y') }}">
        <x-periode-filter :periode="$periode" />
        <a class="btn-hifi" href="{{ route('admin.laporan.jurnal') }}">Ekspor Data</a>
    </x-page-head>

    {{-- Action cards (Figma "Row / Aksi Cepat"): the admin's four daily jobs --}}
    <style>
        .bento-action-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            text-decoration: none !important;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            color: inherit;
        }
        .bento-action-card:hover {
            border-color: var(--primary, #3b5d32);
            box-shadow: 0 4px 12px rgba(59, 93, 50, 0.09);
            transform: translateY(-2px);
            color: inherit;
        }
        .bento-action-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        .bento-action-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }
        @media (max-width: 1200px) {
            .bento-action-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 640px) {
            .bento-action-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="bento-action-grid mb-4">
        {{-- 1. Cek Jurnal Hari Ini --}}
        <a href="{{ route('admin.laporan.jurnal') }}" class="bento-action-card">
            <div class="d-flex align-items-center gap-3">
                <div class="bento-action-icon" style="background: rgba(59, 93, 50, 0.12); color: var(--primary, #3b5d32);">
                    <x-ikon nama="book" />
                </div>
                <div>
                    <h4 class="fw-bold text-dark mb-1" style="font-size: 14.5px;">Cek Jurnal Hari Ini</h4>
                    <p class="text-muted small mb-0" style="font-size: 12px;">
                        {{ $jadwalHariIni ? $belumBerjurnal . ' dari ' . $jadwalHariIni . ' jadwal belum diisi' : 'Tidak ada jadwal aktif' }}
                    </p>
                </div>
            </div>
            <span class="chip chip--neutral" style="font-size: 11px;">Buka &rsaquo;</span>
        </a>

        {{-- 2. Pantau Kehadiran Siswa --}}
        <a href="{{ route('admin.laporan.presensi') }}" class="bento-action-card">
            <div class="d-flex align-items-center gap-3">
                <div class="bento-action-icon" style="background: rgba(30, 58, 138, 0.10); color: #1e3a8a;">
                    <x-ikon nama="people" />
                </div>
                <div>
                    <h4 class="fw-bold text-dark mb-1" style="font-size: 14.5px;">Pantau Kehadiran</h4>
                    <p class="text-muted small mb-0" style="font-size: 12px;">Rekap presensi hadir, izin, sakit, alpa</p>
                </div>
            </div>
            <span class="chip chip--neutral" style="font-size: 11px;">Buka &rsaquo;</span>
        </a>

        {{-- 3. Kelola Pengguna --}}
        @unless (Auth::user()->isHanyaBaca())
        <a href="{{ route('admin.akun.index') }}" class="bento-action-card">
            <div class="d-flex align-items-center gap-3">
                <div class="bento-action-icon" style="background: rgba(180, 83, 9, 0.10); color: #b45309;">
                    <x-ikon nama="person-plus" />
                </div>
                <div>
                    <h4 class="fw-bold text-dark mb-1" style="font-size: 14.5px;">Kelola Pengguna</h4>
                    <p class="text-muted small mb-0" style="font-size: 12px;">Manajemen akun guru, staf, dan siswa</p>
                </div>
            </div>
            <span class="chip chip--neutral" style="font-size: 11px;">Kelola &rsaquo;</span>
        </a>
        @endunless

        {{-- 4. Atur Jadwal --}}
        <a href="{{ route('jadwal.index') }}" class="bento-action-card">
            <div class="d-flex align-items-center gap-3">
                <div class="bento-action-icon" style="background: rgba(15, 118, 110, 0.10); color: #0f766e;">
                    <x-ikon nama="calendar3" />
                </div>
                <div>
                    <h4 class="fw-bold text-dark mb-1" style="font-size: 14.5px;">Atur Jadwal Pelajaran</h4>
                    <p class="text-muted small mb-0" style="font-size: 12px;">Penataan jam mengajar seluruh rombel</p>
                </div>
            </div>
            <span class="chip chip--neutral" style="font-size: 11px;">Atur &rsaquo;</span>
        </a>
    </div>

    {{-- KPI row --}}
    <div class="grid-row grid-row--6">
        <x-kpi label="Total Siswa" :value="number_format($kpi['siswa'], 0, ',', '.')"
               :spark="$datar" :caption="'tersebar di ' . $kpi['kelas'] . ' kelas'" />
        <x-kpi label="Total Guru" :value="$kpi['guru']" :spark="$datar" caption="guru pengajar" />
        <x-kpi label="Total Kelas" :value="$kpi['kelas']" :spark="$datar" caption="rombongan belajar" />
        <x-kpi label="Mata Pelajaran" :value="$kpi['mapel']" :spark="$datar" caption="aktif semester ini" />
        <x-kpi label="Total Jadwal" :value="number_format($kpi['jadwal'], 0, ',', '.')"
               :spark="$datar" caption="seluruh rombel" />
        <x-kpi label="Total Jurnal" :value="number_format($kpi['jurnal'], 0, ',', '.')"
               :spark="$sparkJurnal" :delta="$trenJurnal" :caption="$periode->label()" />
    </div>

    {{-- Analytics row --}}
    <div class="grid-row grid-row--analitik-3">
        <x-card title="Pengisian Jurnal" :meta="$periode->label() . ' · rata-rata ' . $rataPengisian . ($mingguan ? '/minggu' : '/hari')">
            <x-barchart :series="$pengisian" drill="jurnal" :target="$targetPengisian" />
        </x-card>

        <x-card title="Presensi Siswa" :meta="$periode->label()">
            <p class="card-hifi__meta mb-2">{{ number_format($totalPresensi, 0, ',', '.') }} kehadiran tercatat</p>
            <x-breakdown
                :items="['Hadir' => $presensi['hadir'], 'Sakit' => $presensi['sakit'], 'Izin' => $presensi['izin'], 'Alpa' => $presensi['alpa']]"
                :tones="['Hadir' => 'hadir', 'Sakit' => 'sakit', 'Izin' => 'izin', 'Alpa' => 'alpa']"
                drill-tipe="presensi"
                :drill-keys="['Hadir' => 'hadir', 'Sakit' => 'sakit', 'Izin' => 'izin', 'Alpa' => 'alpa']" />
        </x-card>

        <x-card title="Kehadiran Guru" :meta="$periode->label()">
            <p class="card-hifi__meta mb-2">{{ number_format($kehadiranGuru['total'], 0, ',', '.') }} pertemuan terjadwal</p>
            <x-breakdown
                :items="['Hadir' => $kehadiranGuru['hadir'], 'Ada Tugas' => $kehadiranGuru['ada_tugas'], 'Tanpa Tugas' => $kehadiranGuru['tanpa_tugas']]"
                :tones="['Hadir' => 'hadir', 'Ada Tugas' => 'izin', 'Tanpa Tugas' => 'alpa']" />

            @if ($guruPerluPerhatian > 0)
                <div class="flash mt-3 is-clickable" role="button" tabindex="0"
                     data-detail-tipe="guru_perhatian"
                     style="background: var(--yellow-100); color: #4a3b00">
                    <span class="legend__dot" style="background: var(--s-300)"></span>
                    {{ $guruPerluPerhatian }} guru perlu perhatian
                </div>
            @endif
        </x-card>
    </div>

    {{-- Cluster 2: Alat Administrasi, Pemeliharaan Data & Keamanan Sistem --}}
    @unless (Auth::user()->isHanyaBaca())
    <div class="mb-4">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <h3 class="fw-bold text-dark mb-0" style="font-size: 16px;">Pusat Administrasi &amp; Keamanan Data</h3>
            <span class="text-muted small" style="font-size: 12px;">Siklus tahunan, cadangan database, dan pemantauan sistem</span>
        </div>
        <div class="bento-action-grid">
            {{-- 1. Transisi Terpadu --}}
            <a href="{{ route('admin.transisi-terpadu.index') }}" class="bento-action-card border-l-4" style="border-left-color: var(--primary, #3b5d32);">
                <div class="d-flex align-items-center gap-3">
                    <div class="bento-action-icon" style="background: rgba(59, 93, 50, 0.12); color: var(--primary, #3b5d32);">
                        <x-ikon nama="arrow-repeat" />
                    </div>
                    <div>
                        <h4 class="fw-bold text-dark mb-1" style="font-size: 14.5px;">Transisi Terpadu</h4>
                        <p class="text-muted small mb-0" style="font-size: 12px;">Kelulusan, kenaikan tingkat, dan intake PPDB</p>
                    </div>
                </div>
                <span class="chip chip--green" style="font-size: 11px;">Alur Tahunan &rsaquo;</span>
            </a>

            {{-- 2. Impor Massal Excel --}}
            <a href="{{ route('admin.impor.index') }}" class="bento-action-card border-l-4" style="border-left-color: #166534;">
                <div class="d-flex align-items-center gap-3">
                    <div class="bento-action-icon" style="background: rgba(22, 101, 52, 0.12); color: #166534;">
                        <x-ikon nama="file-earmark-spreadsheet" />
                    </div>
                    <div>
                        <h4 class="fw-bold text-dark mb-1" style="font-size: 14.5px;">Impor Massal Excel</h4>
                        <p class="text-muted small mb-0" style="font-size: 12px;">Pendaftaran akun siswa &amp; guru via spreadsheet</p>
                    </div>
                </div>
                <span class="chip chip--neutral" style="font-size: 11px;">Template &amp; AI &rsaquo;</span>
            </a>

            {{-- 3. Cadangan & Arsip Data --}}
            <a href="{{ route('admin.manajemen-data.cadangan.index') }}" class="bento-action-card border-l-4" style="border-left-color: #1e3a8a;">
                <div class="d-flex align-items-center gap-3">
                    <div class="bento-action-icon" style="background: rgba(30, 58, 138, 0.10); color: #1e3a8a;">
                        <x-ikon nama="shield-lock" />
                    </div>
                    <div>
                        <h4 class="fw-bold text-dark mb-1" style="font-size: 14.5px;">Cadangan &amp; Pemulihan</h4>
                        <p class="text-muted small mb-0" style="font-size: 12px;">Snapshot berkala dan uji pemulihan staging</p>
                    </div>
                </div>
                <span class="chip chip--neutral" style="font-size: 11px;">Arsip SHA-256 &rsaquo;</span>
            </a>

            {{-- 4. Sistem & Audit Log --}}
            <a href="{{ route('admin.sistem.index') }}" class="bento-action-card border-l-4" style="border-left-color: #4b5563;">
                <div class="d-flex align-items-center gap-3">
                    <div class="bento-action-icon" style="background: rgba(75, 85, 99, 0.10); color: #374151;">
                        <x-ikon nama="activity" />
                    </div>
                    <div>
                        <h4 class="fw-bold text-dark mb-1" style="font-size: 14.5px;">Sistem &amp; Audit Log</h4>
                        <p class="text-muted small mb-0" style="font-size: 12px;">Kesehatan server, antrean, dan rekam audit</p>
                    </div>
                </div>
                <span class="chip chip--neutral" style="font-size: 11px;">Kontrol &rsaquo;</span>
            </a>
        </div>
    </div>
    @endunless

    {{-- Latest journals --}}
    <x-card title="Jurnal Terbaru" flush>
        <x-slot:actions>
            <a class="auth__link" href="{{ route('admin.laporan.jurnal') }}">Lihat semua →</a>
        </x-slot:actions>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Jam</th>
                        <th>Kelas</th>
                        <th>Mata Pelajaran</th>
                        <th>Guru</th>
                        <th>Materi</th>
                        <th>Kehadiran Siswa</th>
                        <th>Kehadiran Guru</th>
                        <th class="is-num">Jurnal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($jurnalTerbaru as $jurnal)
                        @php
                            $guruChip = $jurnal->kehadiranGuruChip();
                            $statusChip = $jurnal->statusPengisian();
                        @endphp
                        <tr>
                            <td class="is-muted">{{ $jurnal->tanggal->format('d/m') }}</td>
                            <td class="is-muted">{{ $jurnal->jadwal?->jpLabel() }}</td>
                            <td class="is-strong">{{ $jurnal->jadwal?->kelas?->nama_kelas }}</td>
                            <td>{{ $jurnal->jadwal?->mataPelajaran?->nama }}</td>
                            <td><x-guru-link :guru="$jurnal->guru" :avatar="false" /></td>
                            <td>{{ $jurnal->materiKosong() ? 'Belum diisi' : Str::limit($jurnal->materi, 32) }}</td>
                            <td>
                                <span class="meter-cell">
                                    <x-meter :percent="$jurnal->total_presensi ? $jurnal->hadir_count / $jurnal->total_presensi * 100 : 0" />
                                    <span class="is-muted">{{ $jurnal->hadir_count }}/{{ $jurnal->total_presensi }}</span>
                                </span>
                            </td>
                            <td><x-chip :tone="$guruChip['tone']" :label="$guruChip['label']" /></td>
                            <td class="is-num"><x-chip :tone="$statusChip['tone']" :label="$statusChip['label']" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="empty-state">Belum ada jurnal tercatat.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <x-detail-modal />
@endsection
