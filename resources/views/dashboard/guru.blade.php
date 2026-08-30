@extends('layouts.app')

@section('title', 'Dashboard Guru')

@section('content')
    @php
        $sparkAktivitas = array_column($aktivitas, 'value');
        $datar = array_fill(0, 12, 0);
        $totalKehadiranGuru = $kehadiranGuru['total'] ?: 1;
    @endphp

    <x-page-head
        title="Selamat Datang, {{ Auth::user()->nama }}!"
        :sub="'Ringkasan kegiatan mengajar Anda · Semester Gasal ' . now()->year . '/' . (now()->year + 1) . ' · ' . now()->translatedFormat('j F Y')">
        {{-- A label, not a control: this screen is "today plus standing totals",
             so there is no period to choose. Styled as a chip so it never looks
             like a dropdown that refuses to open. --}}
        <x-chip tone="neutral" :label="now()->translatedFormat('F Y')" />
        {{-- A guru's daily duty is the roster, not the journal: the class writes
             the journal, and the recap screen lists every lesson still to mark. --}}
        <a class="btn-hifi" href="{{ route('presensi.index') }}">Isi Presensi</a>
    </x-page-head>

    <div class="grid-row grid-row--6">
        <x-kpi label="Jadwal Hari Ini" :value="$kpi['jadwalHariIni']" :spark="$datar"
               :caption="now()->translatedFormat('l')" />
        <x-kpi label="Jurnal Terisi" :value="$kpi['jurnalTerisi']" :spark="$sparkAktivitas" caption="hari ini" />
        <x-kpi label="Belum Ditandai" :value="$kpi['belumDitandai']" :spark="$datar" caption="presensi hari ini" />
        <x-kpi label="Kelas Diampu" :value="$kpi['kelasDiampu']" :spark="$datar" caption="rombongan belajar" />
        <x-kpi label="Siswa Diampu" :value="number_format($kpi['siswaDiampu'], 0, ',', '.')" :spark="$datar" caption="seluruh kelas" />
        <x-kpi label="Rata Kehadiran" :value="$kpi['rataKehadiran'] . '%'" :spark="$datar" caption="siswa di kelas Anda" />
    </div>

    <div class="grid-row grid-row--split">
        <x-card title="Jadwal Mengajar Hari Ini" flush>
            <x-slot:actions>
                <span class="card-hifi__meta">{{ now()->translatedFormat('l, j F Y') }}</span>
            </x-slot:actions>

            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Jam</th>
                            <th>Kelas</th>
                            <th>Mata Pelajaran</th>
                            <th>Ruang</th>
                            <th>Hadir Guru</th>
                            <th>Presensi Siswa</th>
                            <th class="is-num">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($jadwalHariIni as $jadwal)
                            @php $jurnal = $jurnalHariIni[$jadwal->id] ?? null; @endphp
                            <tr>
                                <td class="is-muted">{{ $jadwal->jpLabel() }}</td>
                                <td class="is-strong">{{ $jadwal->kelas?->nama_kelas }}</td>
                                <td>{{ $jadwal->mataPelajaran?->nama }}</td>
                                <td class="is-muted">{{ $jadwal->ruang ?? $jadwal->kelas?->ruang ?? '—' }}</td>
                                <td>
                                    @if ($jurnal)
                                        @php $chip = $jurnal->kehadiranGuruChip(); @endphp
                                        <x-chip :tone="$chip['tone']" :label="$chip['label']" />
                                    @else
                                        <span class="is-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @php $ditandai = $jurnal ? ($ditandaiHariIni[$jurnal->id] ?? 0) : 0; @endphp
                                    <span class="meter-cell">
                                        <x-meter :percent="$jurnal && $jurnal->total_siswa ? $jurnal->hadir_count / $jurnal->total_siswa * 100 : 0" />
                                        <span class="is-muted">{{ $jurnal?->hadir_count ?? 0 }}/{{ $ditandai }}</span>
                                    </span>
                                </td>
                                <td class="is-num">
                                    {{-- POST, not a link: the roster may have to open the meeting's
                                         record when the class has not written its journal yet. --}}
                                    <form method="POST" action="{{ route('presensi-jurnal.mulai') }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="jadwal_id" value="{{ $jadwal->id }}">
                                        <input type="hidden" name="tanggal" value="{{ now()->toDateString() }}">
                                        <button class="btn-hifi btn-hifi--sm {{ $ditandai ? 'btn-hifi--ghost' : '' }}" type="submit">
                                            {{ $ditandai ? 'Ubah' : 'Tandai →' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="empty-state">Tidak ada jadwal mengajar hari ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <x-card title="Aktivitas 14 Hari" meta="jurnal ditulis">
            <x-barchart :series="$aktivitas" />
        </x-card>

        <x-card title="Kehadiran Saya" :meta="now()->translatedFormat('F Y')">
            <p class="card-hifi__meta mb-1">{{ $kehadiranGuru['total'] }} pertemuan tercatat</p>
            <div class="text-center my-2">
                <div style="font-size: 28px; font-weight: 700; letter-spacing: -0.02em">
                    {{ round($kehadiranGuru['hadir'] / $totalKehadiranGuru * 100) }}%
                </div>
                <div class="kpi__caption">{{ $kehadiranGuru['hadir'] }} dari {{ $kehadiranGuru['total'] }} hadir mengajar</div>
            </div>
            @php $persenHadir = round($kehadiranGuru['hadir'] / $totalKehadiranGuru * 100); @endphp
            <span class="meter" style="width: 100%">
                <span class="meter__fill" style="width: {{ $persenHadir }}%"></span>
            </span>

            <div class="mt-3 d-flex flex-column gap-2">
                @foreach ([
                    ['Hadir', $kehadiranGuru['hadir'], 'var(--green-200)'],
                    ['Tidak Hadir – Ada Tugas', $kehadiranGuru['ada_tugas'], 'var(--yellow-200)'],
                    ['Tidak Hadir – Tanpa Tugas', $kehadiranGuru['tanpa_tugas'], 'var(--red-100)'],
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
        <x-card title="Kehadiran per Kelas" :meta="now()->translatedFormat('F Y')">
            <x-legend class="mb-2" :items="[
                'Hadir' => 'var(--green-200)',
                'Sakit' => 'var(--s-300)',
                'Izin' => 'var(--yellow-200)',
                'Alpa' => 'var(--red-100)',
            ]" />

            @forelse ($kelasDiampu as $kelas)
                @php
                    $rekap = $kehadiranPerKelas[$kelas->id] ?? collect();
                    $hadir = (int) ($rekap['hadir'] ?? 0);
                    $total = $rekap->sum() ?: 1;
                @endphp
                <div class="breakdown breakdown--wide mb-2">
                    <span class="breakdown__label">{{ $kelas->nama_kelas }}</span>
                    <x-stack-bar :hadir="$hadir" :sakit="$rekap['sakit'] ?? 0"
                                 :izin="$rekap['izin'] ?? 0" :alpa="$rekap['alpa'] ?? 0" />
                    <span class="breakdown__value">{{ round($hadir / $total * 100) }}%</span>
                </div>
            @empty
                <p class="empty-state">Belum ada kelas yang diampu.</p>
            @endforelse
        </x-card>

        <x-card title="Jurnal Terakhir Saya" flush>
            <x-slot:actions>
                <a class="auth__link" href="{{ route('jurnal.index') }}">Lihat semua →</a>
            </x-slot:actions>

            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Kelas</th>
                            <th>Mata Pelajaran</th>
                            <th>Materi</th>
                            <th>Kehadiran</th>
                            <th class="is-num">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($jurnalTerakhir as $jurnal)
                            @php
                                $chip = $jurnal->kehadiranGuruChip();
                                $status = $jurnal->statusPengisian();
                            @endphp
                            <tr>
                                <td class="is-muted is-nowrap">{{ $jurnal->tanggal->format('d/m') }}</td>
                                <td class="is-strong is-nowrap">{{ $jurnal->jadwal?->kelas?->nama_kelas }}</td>
                                <td>{{ $jurnal->jadwal?->mataPelajaran?->nama }}</td>
                                <td class="is-muted is-nowrap">{{ Str::limit($jurnal->materi, 18) }}</td>
                                <td><x-chip :tone="$chip['tone']" :label="$chip['label']" /></td>
                                <td class="is-num"><x-chip :tone="$status['tone']" :label="$status['label']" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty-state">Belum ada jurnal.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    <x-card title="Kelengkapan Jurnal Saya">
        <x-slot:actions>
            <x-legend :swatch="true" :items="[
                'Belum diisi' => 'var(--n-200)',
                '' => 'var(--s-100)',
                ' ' => 'var(--p-100)',
                '  ' => 'var(--p-200)',
                'Lengkap' => 'var(--p-300)',
            ]" />
        </x-slot:actions>

        <x-heatmap :rows="$heatmap" />
    </x-card>
@endsection
