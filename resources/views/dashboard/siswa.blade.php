@extends('layouts.app')

@section('title', 'Dashboard Siswa')

@section('content')
    @php
        $datar = array_fill(0, 12, 0);
        $totalPresensi = array_sum($kehadiran) ?: 1;
        $totalJurnal = $jurnalStatus['total'] ?: 1;
        $sapaan = Auth::user()->isKetuaKelas()
            ? 'Selamat Datang, Ketua Kelas ' . ($kelas?->nama_kelas ?? '') . '!'
            : 'Selamat Datang, ' . Auth::user()->name . '!';
    @endphp

    <x-page-head
        :title="$sapaan"
        :sub="'Ringkasan kelas dan kehadiran Anda · Semester Gasal ' . now()->year . '/' . (now()->year + 1) . ' · ' . now()->translatedFormat('j F Y')">
        {{-- A label, not a control — see dashboard/guru.blade.php. --}}
        <x-chip tone="neutral" :label="now()->translatedFormat('F Y')" />
        @if ($isKetua && $kelas)
            <a class="btn-hifi {{ $sudahIsiHariIni ? 'btn-hifi--ghost' : '' }}"
               href="{{ route('presensi-harian.edit', $kelas) }}">
                {{ $sudahIsiHariIni ? 'Perbarui Presensi Hari Ini' : 'Isi Presensi Hari Ini' }}
            </a>
            <a class="btn-hifi btn-hifi--ghost" href="{{ route('jurnal.create') }}">Isi Jurnal Kelas</a>
        @endif
    </x-page-head>

    @if ($isKetua && $kelas && ! $sudahIsiHariIni)
        {{-- One duty a day, and it is this one. Said plainly at the top rather
             than left for the ketua to remember. --}}
        <p class="banner banner--bahaya mb-2">
            Presensi {{ $kelas->nama_kelas }} untuk {{ now()->translatedFormat('l, j F Y') }} belum diisi.
            <a class="auth__link" href="{{ route('presensi-harian.edit', $kelas) }}">Isi sekarang →</a>
        </p>
    @endif

    <div class="grid-row grid-row--6">
        <x-kpi label="Jadwal Hari Ini" :value="$kpi['jadwalHariIni']" :spark="$datar" :caption="now()->translatedFormat('l')" />
        <x-kpi label="Jurnal Terisi" :value="$kpi['jurnalTerisi']" :spark="$datar" caption="hari ini" />
        <x-kpi label="Belum Diisi" :value="$kpi['belumDiisi']" :spark="$datar" caption="menunggu guru" />
        <x-kpi :label="$kehadiranLabel" :value="$kpi['kehadiran'] . '%'" :spark="$datar" caption="semester berjalan" />
        <x-kpi label="Hadir" :value="number_format($kpi['hadir'], 0, ',', '.')" :spark="$datar" caption="hari sekolah" />
        <x-kpi label="Alpa" :value="$kpi['alpa']" :spark="$datar" caption="tanpa keterangan" />
    </div>

    <div class="grid-row grid-row--split">
        <x-card title="Jadwal Kelas Hari Ini" flush>
            <x-slot:actions>
                <span class="card-hifi__meta">{{ now()->translatedFormat('l, j F Y') }}</span>
            </x-slot:actions>

            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Jam</th>
                            <th>Mata Pelajaran</th>
                            <th>Guru</th>
                            <th>Hadir Guru</th>
                            <th>Ruang</th>
                            <th class="is-num">Jurnal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($jadwalHariIni as $jadwal)
                            @php $jurnal = $jurnalHariIni[$jadwal->id] ?? null; @endphp
                            <tr>
                                <td class="is-muted">{{ $jadwal->jpLabel() }}</td>
                                <td class="is-strong">{{ $jadwal->mataPelajaran?->nama }}</td>
                                <td>{{ $jadwal->guru?->name }}</td>
                                <td>
                                    @if ($jurnal)
                                        @php $chip = $jurnal->kehadiranGuruChip(); @endphp
                                        <x-chip :tone="$chip['tone']" :label="$chip['label']" />
                                    @else
                                        <span class="is-muted">—</span>
                                    @endif
                                </td>
                                <td class="is-muted">{{ $jadwal->ruang ?? $kelas?->ruang ?? '—' }}</td>
                                <td class="is-num">
                                    @if ($jurnal)
                                        @php $status = $jurnal->statusPengisian(); @endphp
                                        <x-chip :tone="$status['tone']" :label="$status['label']" />
                                    @else
                                        <x-chip tone="neutral" label="Belum" />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty-state">Tidak ada jadwal hari ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <x-card :title="$kehadiranLabel" :meta="now()->translatedFormat('F Y')">
            <p class="card-hifi__meta mb-2">{{ number_format($totalPresensi, 0, ',', '.') }} pertemuan tercatat</p>
            <x-breakdown
                :items="['Hadir' => $kehadiran['hadir'], 'Sakit' => $kehadiran['sakit'], 'Izin' => $kehadiran['izin'], 'Alpa' => $kehadiran['alpa']]"
                :tones="['Hadir' => 'hadir', 'Sakit' => 'sakit', 'Izin' => 'izin', 'Alpa' => 'alpa']" />
        </x-card>

        <x-card title="Jurnal Kelas" :meta="now()->translatedFormat('F Y')">
            <div class="text-center my-2">
                <div style="font-size: 28px; font-weight: 700; letter-spacing: -0.02em">
                    {{ round($jurnalStatus['kelengkapan']) }}%
                </div>
                <div class="kpi__caption">{{ $jurnalStatus['total'] }} jurnal tercatat</div>
            </div>
            <span class="meter" style="width: 100%">
                <span class="meter__fill" style="width: {{ $jurnalStatus['kelengkapan'] }}%"></span>
            </span>

            <div class="mt-3 d-flex flex-column gap-2">
                @foreach ([
                    ['Tepat waktu', $jurnalStatus['tepatWaktu'], 'var(--green-200)'],
                    ['Terlambat', $jurnalStatus['terlambat'], 'var(--yellow-200)'],
                ] as [$label, $nilai, $warna])
                    <div class="d-flex align-items-center justify-content-between" style="font-size: 11px">
                        <span class="legend__item">
                            <span class="legend__dot" style="background: {{ $warna }}"></span>{{ $label }}
                        </span>
                        <span class="breakdown__value">{{ $nilai }}</span>
                    </div>
                @endforeach
            </div>
        </x-card>
    </div>

    <div class="grid-row grid-row--2">
        <x-card title="Kehadiran per Bulan" meta="6 bulan terakhir">
            <x-legend class="mb-2" :items="[
                'Hadir' => 'var(--green-200)',
                'Sakit' => 'var(--s-300)',
                'Izin' => 'var(--yellow-200)',
                'Alpa' => 'var(--red-100)',
            ]" />

            @forelse ($kehadiranPerBulan as $bulan => $rekap)
                @php
                    $hadir = (int) ($rekap['hadir'] ?? 0);
                    $total = $rekap->sum() ?: 1;
                @endphp
                <div class="breakdown breakdown--wide mb-2">
                    <span class="breakdown__label">{{ $bulan }}</span>
                    <x-stack-bar :hadir="$hadir" :sakit="$rekap['sakit'] ?? 0"
                                 :izin="$rekap['izin'] ?? 0" :alpa="$rekap['alpa'] ?? 0" />
                    <span class="breakdown__value">{{ round($hadir / $total * 100) }}%</span>
                    <span class="breakdown__pct">{{ $total }}</span>
                </div>
            @empty
                <p class="empty-state">Belum ada catatan kehadiran.</p>
            @endforelse
        </x-card>

        <x-card title="Riwayat Jurnal Kelas" flush>
            <x-slot:actions>
                <a class="auth__link" href="{{ route('jurnal.index') }}">Lihat semua →</a>
            </x-slot:actions>

            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Mata Pelajaran</th>
                            <th>Guru</th>
                            <th>Hadir Guru</th>
                            <th>Materi</th>
                            <th class="is-num">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($riwayatJurnal as $jurnal)
                            @php
                                $chip = $jurnal->kehadiranGuruChip();
                                $status = $jurnal->statusPengisian();
                            @endphp
                            <tr>
                                <td class="is-muted">{{ $jurnal->tanggal->format('d/m') }}</td>
                                <td class="is-strong is-nowrap">{{ $jurnal->jadwal?->mataPelajaran?->nama }}</td>
                                <td class="is-muted is-nowrap">{{ $jurnal->guru?->name }}</td>
                                <td><x-chip :tone="$chip['tone']" :label="$chip['label']" /></td>
                                <td class="is-muted is-nowrap">{{ Str::limit($jurnal->materi, 18) }}</td>
                                <td class="is-num"><x-chip :tone="$status['tone']" :label="$status['label']" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty-state">Belum ada jurnal kelas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    <x-card title="Riwayat Kehadiran Saya">
        <x-slot:actions>
            <x-legend :swatch="true" :items="[
                'Hadir' => 'var(--p-300)',
                'Izin' => 'var(--yellow-100)',
                'Sakit' => 'var(--s-100)',
                'Alpa' => 'var(--red-100)',
                'Tidak ada jadwal' => 'var(--n-200)',
            ]" />
        </x-slot:actions>

        <x-heatmap :rows="$heatmap" :labelWidth="120" />
    </x-card>
@endsection
