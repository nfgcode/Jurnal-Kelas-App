@extends('layouts.app')

@section('title', 'Jadwal Kelas')

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
        title="Jadwal Kelas {{ $kelas->nama_kelas }}"
        :sub="$statistik['totalJadwal'] . ' slot · ' . $statistik['jp'] . ' JP per minggu · Semester Gasal ' . now()->year . '/' . (now()->year + 1)">
        <x-kelas-switch :kelas-wali="$kelasWali" :kelas="$kelas" />
    </x-page-head>

    <div class="grid-row grid-row--4">
        <x-stat label="Slot Jadwal" :value="$statistik['totalJadwal']" caption="pertemuan per minggu" />
        <x-stat label="JP / Minggu" :value="$statistik['jp']" :caption="'kelas ' . $kelas->nama_kelas" />
        <x-stat label="Guru Mengajar" :value="$statistik['guru']" caption="di kelas ini" />
        <x-stat label="Mata Pelajaran" :value="$statistik['mapel']" caption="diajarkan" />
    </div>

    <x-card :title="'Matriks Jadwal Mingguan — ' . $kelas->nama_kelas">
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
                        <span class="matrix__slot matrix__slot--{{ $sel->mataPelajaran?->kelompok ?? 'wajib' }}"
                              style="grid-column: {{ $kolom + 2 }}; grid-row: {{ $jp + 1 }} / {{ $sel->jam_ke_selesai + 2 }}">
                            <span class="matrix__mapel">{{ $sel->mataPelajaran?->nama }}</span>
                            <span class="matrix__guru">{{ $sel->guru?->name }}</span>
                            <span class="matrix__ruang">{{ $sel->ruang ?? $kelas->ruang }}</span>
                        </span>
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
