@extends('layouts.app')

@section('title', 'Mata Pelajaran')

@section('content')
    @php
        $pengampu = $mataPelajaran->jadwals->pluck('guru.name')->filter()->unique();
        $kelasDiajar = $mataPelajaran->jadwals->pluck('kelas.nama_kelas')->filter()->unique();
        $kelompokTone = ['wajib' => 'green', 'peminatan' => 'khaki', 'muatan_lokal' => 'neutral', 'kejuruan' => 'solid'];
    @endphp

    <x-page-head :title="$mataPelajaran->nama" :sub="$mataPelajaran->kode . ' · ' . $mataPelajaran->kelompokLabel()">
        @if (Auth::user()->isAdmin())
            <a class="btn-hifi btn-hifi--ghost" href="{{ route('mata-pelajaran.edit', $mataPelajaran) }}">Ubah</a>
        @endif
    </x-page-head>

    <div class="grid-row" style="grid-template-columns: repeat(4, minmax(0, 1fr))">
        <x-stat label="JP per Minggu" :value="$mataPelajaran->jp_per_minggu" caption="per rombel" />
        <x-stat label="Diajarkan Di" :value="$kelasDiajar->count()" caption="kelas" />
        <x-stat label="Guru Pengampu" :value="$pengampu->count()" :caption="$pengampu->first() ?? 'belum ditetapkan'" />
        <x-stat label="Total Jadwal" :value="$mataPelajaran->jadwals->count()" caption="slot per minggu" />
    </div>

    @if ($mataPelajaran->deskripsi)
        <x-card title="Deskripsi">
            <p class="mb-0" style="font-size: 12px; color: var(--n-800)">{{ $mataPelajaran->deskripsi }}</p>
        </x-card>
    @endif

    <x-card title="Jadwal Mata Pelajaran" flush>
        <x-slot:actions>
            <x-chip :tone="$kelompokTone[$mataPelajaran->kelompok] ?? 'neutral'" :label="$mataPelajaran->kelompokLabel()" />
        </x-slot:actions>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr><th>Hari</th><th>JP</th><th>Kelas</th><th>Guru</th><th class="is-num">Ruang</th></tr>
                </thead>
                <tbody>
                    @forelse ($mataPelajaran->jadwals->sortBy(['hari', 'jam_ke_mulai']) as $jadwal)
                        <tr>
                            <td class="is-muted is-nowrap">{{ $jadwal->hari }}</td>
                            <td class="is-muted">{{ $jadwal->jpLabel() }}</td>
                            <td class="is-strong">{{ $jadwal->kelas?->nama_kelas }}</td>
                            <td class="is-muted">{{ $jadwal->guru?->name }}</td>
                            <td class="is-num is-muted">{{ $jadwal->ruang ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-state">Mata pelajaran ini belum terhubung ke jadwal manapun.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
