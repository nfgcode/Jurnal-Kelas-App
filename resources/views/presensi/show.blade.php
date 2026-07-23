@extends('layouts.app')

@section('title', 'Presensi')

@section('content')
    @php
        $total = array_sum($rekap) ?: 1;
        $kelas = $jurnal->jadwal?->kelas;
    @endphp

    <x-page-head
        title="Presensi Pertemuan"
        :sub="collect([$kelas?->nama_kelas, $jurnal->jadwal?->mataPelajaran?->nama, 'JP ' . $jurnal->jadwal?->jpLabel(), $jurnal->tanggal->translatedFormat('l, j F Y')])->filter()->join(' · ')">
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('jurnal.show', $jurnal) }}">Lihat Jurnal</a>
        <a class="btn-hifi" href="{{ route('presensi.create', $jurnal->id) }}">Ubah Presensi</a>
    </x-page-head>

    <div class="grid-row grid-row--4">
        <x-stat label="Hadir" :value="$rekap['hadir']" :caption="round($rekap['hadir'] / $total * 100) . '% dari kelas'" />
        <x-stat label="Sakit" :value="$rekap['sakit']" caption="dengan keterangan" />
        <x-stat label="Izin" :value="$rekap['izin']" caption="dengan keterangan" />
        <x-stat label="Alpa" :value="$rekap['alpa']" caption="tanpa keterangan" />
    </div>

    <x-card :title="'Daftar Kehadiran — ' . ($kelas?->nama_kelas ?? '')" flush>
        <x-slot:actions>
            <x-legend :items="[
                'Hadir' => 'var(--green-200)',
                'Sakit' => 'var(--s-300)',
                'Izin' => 'var(--yellow-200)',
                'Alpa' => 'var(--red-100)',
            ]" />
        </x-slot:actions>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr><th>No</th><th>NIS</th><th>Nama Siswa</th><th>Status</th><th class="is-num">Keterangan</th></tr>
                </thead>
                <tbody>
                    @forelse ($jurnal->presensis->sortBy('siswa.name') as $presensi)
                        @php
                            $tone = match ($presensi->status) {
                                'hadir' => 'green',
                                'sakit' => 'khaki',
                                'izin' => 'yellow',
                                default => 'red',
                            };
                        @endphp
                        <tr>
                            <td class="is-muted">{{ $loop->iteration }}</td>
                            <td class="is-muted">{{ $presensi->siswa?->nis }}</td>
                            <td>
                                <span class="name-cell">
                                    <span class="avatar avatar--xs">{{ $presensi->siswa?->inisial() }}</span>
                                    {{ $presensi->siswa?->name }}
                                </span>
                            </td>
                            <td><x-chip :tone="$tone" :label="ucfirst($presensi->status)" /></td>
                            <td class="is-num is-muted">{{ $presensi->keterangan ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-state">Presensi belum ditandai untuk pertemuan ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:foot>
            <span>{{ $jurnal->presensis->count() }} siswa ditandai</span>
            <a class="btn-hifi btn-hifi--ghost btn-hifi--sm" href="{{ route('presensi.index') }}">Kembali ke daftar</a>
        </x-slot:foot>
    </x-card>
@endsection
