@extends('layouts.app')

@section('title', 'Dashboard Admin')

@section('content')
    @php
        // The honest recorded total; the breakdown component guards its own
        // divide, so this figure needs no "?: 1" fudge that would print "1".
        $totalPresensi = array_sum($presensi);
        $nilaiPengisian = array_column($pengisian, 'value');
        $sparkJurnal = $nilaiPengisian;
        // Master-data counts have no daily series behind them, so their tiles
        // carry a deliberately flat track rather than an invented trend.
        $datar = array_fill(0, 12, 0);
    @endphp

    <x-page-head
        title="Selamat Datang, {{ Auth::user()->name }}!"
        sub="Ringkasan seluruh data sekolah · Semester Gasal {{ now()->year }}/{{ now()->year + 1 }} · {{ now()->translatedFormat('j F Y') }}">
        <x-periode-filter :periode="$periode" />
        <a class="btn-hifi" href="{{ route('admin.laporan.jurnal') }}">Ekspor Data</a>
    </x-page-head>

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
    <div class="grid-row grid-row--analitik">
        <x-card title="Pengisian Jurnal" :meta="$periode->label() . ' · rata-rata ' . round(array_sum($nilaiPengisian) / max(1, count($nilaiPengisian))) . '/titik'">
            <x-barchart :series="$pengisian" drill="jurnal" />
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

        <x-card title="Guru Teraktif" :meta="$periode->label()">
            @php $puncak = max(1, $guruTeraktif->max('jurnals_count') ?? 1); @endphp
            @foreach ($guruTeraktif as $guru)
                <div class="breakdown mb-2 is-clickable" role="button" tabindex="0"
                     data-detail-tipe="guru" data-detail-guru="{{ $guru->id }}">
                    <span class="avatar avatar--xs">{{ $guru->inisial() }}</span>
                    <span class="breakdown__label" style="width: 88px">{{ $guru->name }}</span>
                    <span class="stack"><span class="stack__seg--hadir" style="width: {{ $guru->jurnals_count / $puncak * 100 }}%"></span></span>
                    <span class="breakdown__value">{{ $guru->jurnals_count }}</span>
                </div>
            @endforeach
        </x-card>
    </div>

    {{-- Journal completeness heatmap --}}
    <x-card title="Kelengkapan Jurnal per Kelas">
        <x-slot:actions>
            <x-legend :swatch="true" :items="[
                'Belum diisi' => 'var(--n-200)',
                '' => 'var(--s-100)',
                ' ' => 'var(--p-100)',
                '  ' => 'var(--p-200)',
                'Lengkap' => 'var(--p-300)',
            ]" />
        </x-slot:actions>

        <x-heatmap :rows="$heatmap" drill-tipe="kelas"
                   :kelas-ids="$petaKelas" :tanggal-map="$petaTanggal" />
    </x-card>

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
                            <td>{{ Str::limit($jurnal->materi, 32) }}</td>
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
