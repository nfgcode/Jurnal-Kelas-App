@extends('layouts.app')

@section('title', 'Wali Kelas')

@section('content')
    @php $q = ['kelas_id' => $kelas->id]; @endphp

    <x-page-head
        title="Kelas {{ $kelas->nama_kelas }}"
        :sub="'Tingkat ' . $kelas->tingkat . ($kelas->jurusan ? ' · ' . $kelas->jurusan : '') . ' · ' . $kpi['jumlah_siswa'] . ' siswa'">
        <x-kelas-switch :kelas-wali="$kelasWali" :kelas="$kelas" />
        <a class="btn-hifi" href="{{ route('wali-kelas.siswa', $q) }}">Data Kelas →</a>
    </x-page-head>

    <div class="grid-row grid-row--4">
        <x-stat label="Jumlah Siswa" :value="$kpi['jumlah_siswa']" caption="terdaftar di kelas ini" />
        <x-stat label="Rata Kehadiran" :value="$kpi['rata_kehadiran'] . '%'" caption="seluruh presensi kelas" />
        <x-stat label="Kelengkapan Jurnal" :value="$kpi['kelengkapan'] . '%'" caption="terisi vs terjadwal" />
        <x-stat label="Total Alpa" :value="number_format($kpi['alpa'], 0, ',', '.')" caption="tanpa keterangan" />
    </div>

    <div class="grid-row grid-row--2">
        <x-card title="Siswa dengan Kehadiran Terendah" flush>
            <x-slot:actions>
                <a class="card-hifi__meta text-reset" href="{{ route('wali-kelas.presensi', $q) }}">lihat semua →</a>
            </x-slot:actions>

            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th class="is-num">Alpa</th>
                            <th class="is-num">Kehadiran</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            // The roll a wali actually needs to act on: worst attendance first.
                            $perhatian = $siswa->sortBy(fn ($s) => $rekapSiswa[$s->id]['persen'] ?? 100)->take(8);
                        @endphp
                        @forelse ($perhatian as $s)
                            @php
                                $r = $rekapSiswa[$s->id] ?? ['alpa' => 0, 'persen' => 0];
                                $tone = $r['persen'] >= 85 ? 'green' : ($r['persen'] >= 70 ? 'yellow' : 'red');
                            @endphp
                            <tr>
                                <td class="is-muted">{{ $s->nis ?? '—' }}</td>
                                <td>
                                    <span class="name-cell">
                                        <span class="avatar avatar--xs">{{ $s->inisial() }}</span>
                                        {{ $s->name }}
                                        @if ($s->is_ketua_kelas)
                                            <x-chip tone="yellow" label="Ketua" />
                                        @endif
                                    </span>
                                </td>
                                <td class="is-num is-muted">{{ $r['alpa'] }}</td>
                                <td class="is-num"><x-chip :tone="$tone" :label="$r['persen'] . '%'" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty-state">Belum ada siswa di kelas ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <x-card title="Jurnal Terakhir" flush>
            <x-slot:actions>
                <a class="card-hifi__meta text-reset" href="{{ route('wali-kelas.jurnal', $q) }}">lihat semua →</a>
            </x-slot:actions>

            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Mata Pelajaran</th>
                            <th>Materi</th>
                            <th>Guru</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($jurnalTerakhir as $j)
                            <tr>
                                <td class="is-muted is-nowrap">{{ $j->tanggal->format('d/m/Y') }}</td>
                                <td class="is-strong">{{ $j->jadwal?->mataPelajaran?->nama ?? '—' }}</td>
                                <td>{{ Str::limit($j->materi, 40) }}</td>
                                <td class="is-muted">{{ $j->guru?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty-state">Belum ada jurnal untuk kelas ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
@endsection
