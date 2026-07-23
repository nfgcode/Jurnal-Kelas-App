@extends('layouts.app')

@section('title', 'Jadwal')

@section('content')
    @php
        $hariList = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $jpTerakhir = 10;
        // JP 5 is the break; it carries no lesson in any class.
        $jpIstirahat = 5;

        // Walk each day once to work out which periods a block already covers,
        // so an empty cell is only drawn where nothing spans it.
        $terisi = [];
        foreach ($hariList as $hari) {
            foreach ($matriks[$hari] ?? [] as $mulai => $jadwal) {
                for ($jp = $mulai; $jp <= $jadwal->jam_ke_selesai; $jp++) {
                    $terisi[$hari][$jp] = $jp === $mulai ? $jadwal : true;
                }
            }
        }
    @endphp

    <x-page-head
        title="Jadwal"
        :sub="$statistik['totalJadwal'] . ' jadwal aktif · Semester Gasal ' . now()->year . '/' . (now()->year + 1)">
        <form method="GET">
            <select class="select-hifi" name="kelas_id" style="width: 180px" onchange="this.form.submit()">
                @foreach ($kelasList as $kelas)
                    <option value="{{ $kelas->id }}" @selected($kelasAktif?->id === $kelas->id)>Kelas {{ $kelas->nama_kelas }}</option>
                @endforeach
            </select>
        </form>
        @if (Auth::user()->isAdmin())
            <a class="btn-hifi" href="{{ route('jadwal.create') }}">+ Tambah Jadwal</a>
        @endif
    </x-page-head>

    <div class="grid-row grid-row--4">
        <x-stat label="Jadwal Aktif" :value="$statistik['totalJadwal']" caption="seluruh rombel" />
        <x-stat label="JP / Minggu" :value="$statistik['jpKelas']"
                :caption="'kelas ' . ($kelasAktif?->nama_kelas ?? '—')" />
        <x-stat label="Guru Terlibat" :value="$statistik['guruTerlibat']"
                :caption="$statistik['mapelTerlibat'] . ' mata pelajaran'" />
        <x-stat label="Slot Bentrok" :value="$statistik['bentrok']"
                :caption="'dicek ' . now()->format('d/m')" />
    </div>

    <form class="filter-bar" method="GET">
        <input type="hidden" name="kelas_id" value="{{ $kelasAktif?->id }}">

        <label class="filter-bar__search">
            <i class="bi bi-search"></i>
            <input class="input-hifi" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                   placeholder="Cari mapel, guru atau ruang...">
        </label>

        <select class="select-hifi" name="guru_id" style="width: 170px" onchange="this.form.submit()">
            <option value="">Semua Guru</option>
            @foreach ($guruList as $guru)
                <option value="{{ $guru->id }}" @selected(($filters['guru_id'] ?? null) == $guru->id)>{{ $guru->name }}</option>
            @endforeach
        </select>

        <select class="select-hifi" name="hari" style="width: 150px" onchange="this.form.submit()">
            <option value="">Semua Hari</option>
            @foreach ($hariList as $hari)
                <option value="{{ $hari }}" @selected(($filters['hari'] ?? null) === $hari)>{{ $hari }}</option>
            @endforeach
        </select>

        <span class="filter-bar__note">Tampilan: Matriks Mingguan</span>
    </form>

    <x-card :title="'Matriks Jadwal Mingguan — ' . ($kelasAktif?->nama_kelas ?? 'Tanpa Kelas')">
        <x-slot:actions>
            <x-legend :swatch="true" :items="[
                'Wajib' => '#d9e8d4',
                'Peminatan' => 'var(--s-100)',
                'Muatan Lokal' => '#9ff0c4',
                'Kejuruan' => 'var(--p-100)',
                'Kosong' => 'var(--n-200)',
            ]" />
        </x-slot:actions>

        <div class="scroll-x">
        <div class="matrix">
            <span></span>
            @foreach ($hariList as $hari)
                <span class="matrix__head">{{ $hari }}</span>
            @endforeach

            @for ($jp = 1; $jp <= $jpTerakhir; $jp++)
                <span class="matrix__jp" style="grid-column: 1; grid-row: {{ $jp + 1 }}">JP {{ $jp }}</span>

                @foreach ($hariList as $kolom => $hari)
                    @php $sel = $terisi[$hari][$jp] ?? null; @endphp

                    @if ($sel instanceof \App\Models\Jadwal)
                        <a class="matrix__slot matrix__slot--{{ $sel->mataPelajaran?->kelompok ?? 'wajib' }}"
                           href="{{ route('jadwal.show', $sel) }}"
                           style="grid-column: {{ $kolom + 2 }}; grid-row: {{ $jp + 1 }} / {{ $sel->jam_ke_selesai + 2 }}">
                            <span class="matrix__mapel">{{ $sel->mataPelajaran?->nama }}</span>
                            <span class="matrix__guru">{{ $sel->guru?->name }}</span>
                            <span class="matrix__ruang">{{ $sel->ruang ?? $kelasAktif?->ruang }}</span>
                        </a>
                    @elseif ($sel === null && $jp === $jpIstirahat)
                        <span class="matrix__slot matrix__slot--break"
                              style="grid-column: {{ $kolom + 2 }}; grid-row: {{ $jp + 1 }}">Istirahat</span>
                    @elseif ($sel === null)
                        <span class="matrix__slot matrix__slot--empty"
                              style="grid-column: {{ $kolom + 2 }}; grid-row: {{ $jp + 1 }}"></span>
                    @endif
                @endforeach
            @endfor
        </div>
        </div>
    </x-card>
@endsection
