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

    {{-- Action cards (Figma "Row / Aksi Cepat"): the admin's four daily jobs,
         each card itself the link — no button inside. --}}
    <div class="aksi-row aksi-row--4">
        <x-aksi-card :href="route('admin.laporan.jurnal')" judul="Cek Jurnal Hari Ini" ikon="book" warna="hijau" ringkas
                     :deskripsi="$jadwalHariIni
                         ? $belumBerjurnal . ' dari ' . $jadwalHariIni . ' jadwal belum berjurnal.'
                         : 'Tidak ada jadwal hari ini.'" />
        <x-aksi-card :href="route('admin.laporan.presensi')" judul="Pantau Kehadiran" ikon="people" warna="oranye" ringkas
                     deskripsi="Hadir, izin, sakit, dan alpa siswa hari ini." />
        <x-aksi-card :href="route('admin.akun.index')" judul="Kelola Pengguna" ikon="person-plus" warna="khaki" ringkas
                     deskripsi="Tambah atau impor akun guru dan siswa." />
        <x-aksi-card :href="route('jadwal.index')" judul="Atur Jadwal" ikon="calendar3" warna="sage" ringkas
                     deskripsi="Susun jadwal pelajaran untuk tiap kelas." />
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
