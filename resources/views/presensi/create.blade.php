@extends('layouts.app')

@section('title', 'Presensi')

@section('content')
    @php
        $kelas = $jurnal->jadwal?->kelas;
        $ditandai = array_sum($rekap);
        $tidakHadir = $rekap['sakit'] + $rekap['izin'] + $rekap['alpa'];
        $totalSiswa = $siswaList->count();
    @endphp

    <x-page-head
        title="Presensi Siswa"
        :sub="collect([$kelas?->nama_kelas, $jurnal->jadwal?->mataPelajaran?->nama, 'JP ' . $jurnal->jadwal?->jpLabel(), $jurnal->tanggal->translatedFormat('l, j F Y')])->filter()->join(' · ')">
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('presensi.index') }}">← Daftar Presensi</a>
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('jurnal.show', $jurnal) }}">Lihat Jurnal</a>
        <button class="btn-hifi" type="submit" form="formPresensi">Simpan Presensi</button>
    </x-page-head>

    <div class="grid-row grid-row--4">
        <x-stat label="Total Siswa" :value="$totalSiswa" caption="terdaftar di rombel" />
        <x-stat label="Hadir" :value="$rekap['hadir']"
                :caption="$ditandai ? round($rekap['hadir'] / $ditandai * 100) . '% sudah ditandai' : 'belum ditandai'" />
        <x-stat label="Tidak Hadir" :value="$tidakHadir"
                :caption="$rekap['sakit'] . ' sakit · ' . $rekap['izin'] . ' izin · ' . $rekap['alpa'] . ' alpa'" />
        <x-stat label="Belum Ditandai" :value="max(0, $totalSiswa - $ditandai)"
                :caption="$ditandai >= $totalSiswa ? 'siap disimpan' : 'perlu dilengkapi'" />
    </div>

    <div class="filter-bar">
        <label class="filter-bar__search">
            <i class="bi bi-search"></i>
            <input class="input-hifi" type="search" id="cariSiswa" placeholder="Cari nama atau NIS...">
        </label>

        <button class="btn-hifi btn-hifi--ghost" type="button" id="tandaiSemua">Tandai semua: Hadir</button>

        <span class="filter-bar__note">{{ $totalSiswa }} siswa terdaftar</span>
    </div>

    <form method="POST" action="{{ route('presensi.store') }}" id="formPresensi">
        @csrf
        <input type="hidden" name="jurnal_id" value="{{ $jurnal->public_id }}">

        <x-card :title="'Daftar Siswa — ' . ($kelas?->nama_kelas ?? '')" flush>
            <x-slot:actions>
                <span class="card-hifi__meta">{{ $totalSiswa }} siswa terdaftar</span>
            </x-slot:actions>

            <div class="tbl-wrap">
                <table class="tbl" id="tabelSiswa">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th>Kehadiran</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($siswaList as $index => $siswa)
                            @php $tersimpan = $presensiTersimpan[$siswa->id] ?? null; @endphp
                            <tr data-nama="{{ Str::lower($siswa->name) }}" data-nis="{{ $siswa->nis }}">
                                <td class="is-muted">{{ $index + 1 }}</td>
                                <td class="is-muted">{{ $siswa->nis }}</td>
                                <td>
                                    <span class="name-cell">
                                        <span class="avatar avatar--xs">{{ $siswa->inisial() }}</span>
                                        {{ $siswa->name }}
                                    </span>
                                    <input type="hidden" name="presensi[{{ $index }}][siswa_id]" value="{{ $siswa->id }}">
                                </td>
                                <td>
                                    <span class="seg">
                                        @foreach (['hadir' => 'H', 'sakit' => 'S', 'izin' => 'I', 'alpa' => 'A'] as $nilai => $huruf)
                                            <label class="seg__opt seg__opt--{{ substr($nilai, 0, 1) }}">
                                                <input type="radio" name="presensi[{{ $index }}][status]" value="{{ $nilai }}"
                                                       @checked(($tersimpan->status ?? 'hadir') === $nilai) required>
                                                {{ $huruf }}
                                            </label>
                                        @endforeach
                                    </span>
                                </td>
                                <td>
                                    <input class="input-hifi" type="text" style="height: 28px; font-size: 11px"
                                           name="presensi[{{ $index }}][keterangan]"
                                           value="{{ $tersimpan->keterangan ?? '' }}" placeholder="—">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-slot:foot>
                <span>Menampilkan {{ $totalSiswa }} siswa</span>
                <button class="btn-hifi" type="submit">Simpan Presensi</button>
            </x-slot:foot>
        </x-card>
    </form>

    @push('scripts')
        <script>
            const baris = [...document.querySelectorAll('#tabelSiswa tbody tr')];

            document.getElementById('cariSiswa')?.addEventListener('input', (event) => {
                const kata = event.target.value.toLowerCase().trim();
                baris.forEach((tr) => {
                    const cocok = tr.dataset.nama.includes(kata) || tr.dataset.nis.includes(kata);
                    tr.style.display = cocok ? '' : 'none';
                });
            });

            document.getElementById('tandaiSemua')?.addEventListener('click', () => {
                baris.forEach((tr) => {
                    const hadir = tr.querySelector('input[value="hadir"]');
                    if (hadir) hadir.checked = true;
                });
            });
        </script>
    @endpush
@endsection
