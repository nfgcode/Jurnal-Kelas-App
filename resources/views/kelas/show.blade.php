@extends('layouts.app')

@section('title', 'Kelas')

@section('content')
    <x-page-head
        :title="'Kelas ' . $kelas->nama_kelas"
        :sub="collect([$kelas->jurusan, $kelas->ruang, $kelas->tahun_ajaran])->filter()->join(' · ')">
        @if (Auth::user()->isAdmin())
            <a class="btn-hifi btn-hifi--ghost" href="{{ route('kelas.edit', $kelas) }}">Ubah</a>
        @endif
        <a class="btn-hifi" href="{{ route('jadwal.index', ['kelas_id' => $kelas->id]) }}">Lihat Jadwal</a>
    </x-page-head>

    <div class="grid-row grid-row--4">
        <x-stat label="Jumlah Siswa" :value="$kelas->siswa->count()" :caption="'kapasitas ' . $kelas->kapasitas" />
        <x-stat label="Tingkat" :value="$kelas->tingkat" :caption="$kelas->jurusan ?? 'tanpa jurusan'" />
        <x-stat label="Jadwal" :value="$kelas->jadwals->count()" caption="slot per minggu" />
        <x-stat label="Ruang" :value="$kelas->ruang ?? '—'" caption="ruang kelas utama" />
    </div>

    <div class="grid-row grid-row--2">
        <x-card title="Daftar Siswa" flush>
            <x-slot:actions>
                <span class="card-hifi__meta">{{ $kelas->siswa->count() }} siswa</span>
            </x-slot:actions>

            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr><th>No</th><th>NIS</th><th>Nama</th><th class="is-num">Peran</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($kelas->siswa as $index => $siswa)
                            <tr>
                                <td class="is-muted">{{ $index + 1 }}</td>
                                <td class="is-muted">{{ $siswa->nis }}</td>
                                <td>
                                    <span class="name-cell">
                                        <span class="avatar avatar--xs">{{ $siswa->inisial() }}</span>
                                        {{ $siswa->name }}
                                    </span>
                                </td>
                                <td class="is-num">
                                    @if ($siswa->is_ketua_kelas)
                                        <x-chip tone="solid" label="Ketua Kelas" />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty-state">Belum ada siswa di kelas ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <x-card title="Jadwal Pelajaran" flush>
            <x-slot:actions>
                <span class="card-hifi__meta">{{ $kelas->jadwals->count() }} slot</span>
            </x-slot:actions>

            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr><th>Hari</th><th>JP</th><th>Mata Pelajaran</th><th>Guru</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($kelas->jadwals->sortBy(['hari', 'jam_ke_mulai']) as $jadwal)
                            <tr>
                                <td class="is-muted is-nowrap">{{ $jadwal->hari }}</td>
                                <td class="is-muted">{{ $jadwal->jpLabel() }}</td>
                                <td class="is-strong">{{ $jadwal->mataPelajaran?->nama }}</td>
                                <td class="is-nowrap"><x-guru-link :guru="$jadwal->guru" :avatar="false" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty-state">Belum ada jadwal untuk kelas ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
@endsection
