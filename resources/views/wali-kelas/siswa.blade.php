@extends('layouts.app')

@section('title', 'Data Kelas')

@section('content')
    <x-page-head
        title="Data Kelas {{ $kelas->nama_kelas }}"
        :sub="collect(['Tingkat ' . $kelas->tingkat, $kelas->jurusan, $kelas->tahun_ajaran])->filter()->join(' · ')">
        <x-kelas-switch :kelas-wali="$kelasWali" :kelas="$kelas" />
    </x-page-head>

    <div class="grid-row grid-row--4">
        <x-stat label="Jumlah Siswa" :value="$kelas->siswa_count" :caption="'kapasitas ' . $kelas->kapasitas" />
        <x-stat label="Ruang" :value="$kelas->ruang ?? '—'" caption="ruang kelas utama" />
        <x-stat label="Slot Jadwal" :value="$kelas->jadwals_count" caption="pertemuan per minggu" />
        <x-stat label="Ketua Kelas" :value="$jumlahKetua" caption="ditunjuk sebagai ketua" />
    </div>

    <form class="filter-bar" method="GET">
        <x-query-hidden />

        <input type="hidden" name="kelas_id" value="{{ $kelas->id }}">

        <label class="filter-bar__search">
            <x-ikon nama="search" />
            <input class="input-hifi" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                   placeholder="Cari nama atau NIS siswa...">
        </label>

        <span class="filter-bar__note">
            Menampilkan {{ $siswa->count() }} dari {{ $kelas->siswa_count }} siswa
        </span>
    </form>

    <x-card title="Daftar Siswa" flush>
        <x-slot:actions>
            <span class="card-hifi__meta">wali kelas: {{ $kelas->waliKelas?->name ?? '—' }}</span>
        </x-slot:actions>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>NIS</th>
                        <th>Nama Siswa</th>
                        <th>Email</th>
                        <th class="is-num">Pertemuan</th>
                        <th class="is-num">Alpa</th>
                        <th class="is-num">Kehadiran</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($siswa as $i => $s)
                        @php
                            $r = $rekapSiswa[$s->id] ?? ['total' => 0, 'alpa' => 0, 'persen' => 0];
                            $tone = $r['persen'] >= 85 ? 'green' : ($r['persen'] >= 70 ? 'yellow' : 'red');
                        @endphp
                        <tr>
                            <td class="is-muted">{{ $i + 1 }}</td>
                            <td class="is-muted">{{ $s->nis ?? '—' }}</td>
                            <td>
                                <span class="name-cell">
                                    <span class="avatar avatar--xs">{{ $s->inisial() }}</span>
                                    {{ $s->name }}
                                    @if ($s->is_ketua_kelas)
                                        <x-chip tone="solid" label="Ketua Kelas" />
                                    @endif
                                </span>
                            </td>
                            <td class="is-muted">{{ $s->email }}</td>
                            <td class="is-num is-muted">{{ $r['total'] }}</td>
                            <td class="is-num is-muted">{{ $r['alpa'] }}</td>
                            <td class="is-num"><x-chip :tone="$tone" :label="$r['persen'] . '%'" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty-state">Tidak ada siswa yang cocok dengan pencarian.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
