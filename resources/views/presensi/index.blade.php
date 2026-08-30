@extends('layouts.app')

@section('title', 'Presensi')

@section('content')
    @php
        $total = array_sum($rekap) ?: 1;
    @endphp

    <x-page-head
        title="Rekap Presensi Siswa"
        :sub="number_format($totalHari, 0, ',', '.') . ' hari tercatat · rata-rata ' . round($rekap['hadir'] / $total * 100) . '% hadir · ' . $periode->label()">
        <x-periode-filter :periode="$periode" />
    </x-page-head>

    @if ($pertemuanHariIni->isNotEmpty())
        {{-- A guru opens this screen with one job: mark the lessons they taught
             today. Each is its own subject, so each gets its own row and its own
             button rather than one "isi presensi" for the whole day. --}}
        <x-card title="Pertemuan Anda Hari Ini"
                :meta="now()->translatedFormat('l, j F Y')" flush>
            <x-slot:actions>
                @if ($belumDitandai)
                    <x-chip tone="red" :label="$belumDitandai . ' belum ditandai'" />
                @else
                    <x-chip tone="green" label="Semua sudah ditandai" />
                @endif
            </x-slot:actions>

            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Jam</th>
                            <th>Kelas</th>
                            <th>Mata Pelajaran</th>
                            <th>Presensi</th>
                            <th class="is-num">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pertemuanHariIni as $item)
                            <tr>
                                <td class="is-muted is-nowrap">JP {{ $item['jadwal']->jpLabel() }}</td>
                                <td class="is-strong">{{ $item['jadwal']->kelas?->nama_kelas }}</td>
                                <td>{{ $item['jadwal']->mataPelajaran?->nama }}</td>
                                <td>
                                    @if ($item['sudah'])
                                        <x-chip tone="green" :label="$item['ditandai'] . ' siswa ditandai'" />
                                    @else
                                        <x-chip tone="neutral" label="Belum ditandai" />
                                    @endif
                                </td>
                                <td class="is-num">
                                    {{-- POST, not a link: opening the roster may have to create the
                                         meeting's record when the class has not written its journal yet. --}}
                                    <form method="POST" action="{{ route('presensi-jurnal.mulai') }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="jadwal_id" value="{{ $item['jadwal']->id }}">
                                        <input type="hidden" name="tanggal" value="{{ now()->toDateString() }}">
                                        <button class="btn-hifi {{ $item['sudah'] ? 'btn-hifi--ghost' : '' }}" type="submit">
                                            {{ $item['sudah'] ? 'Perbarui' : 'Isi Presensi' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

    <div class="grid-row grid-row--4">
        <x-stat label="Hadir" :value="number_format($rekap['hadir'], 0, ',', '.')"
                :caption="round($rekap['hadir'] / $total * 100) . '% dari total'" />
        <x-stat label="Sakit" :value="number_format($rekap['sakit'], 0, ',', '.')"
                :caption="round($rekap['sakit'] / $total * 100) . '% dari total'" />
        <x-stat label="Izin" :value="number_format($rekap['izin'], 0, ',', '.')"
                :caption="round($rekap['izin'] / $total * 100) . '% dari total'" />
        <x-stat label="Alpa" :value="number_format($rekap['alpa'], 0, ',', '.')"
                :caption="round($rekap['alpa'] / $total * 100) . '% dari total'" />
    </div>

    <form class="filter-bar" method="GET">
        <x-query-hidden />

        <select class="select-hifi" name="kelas_id" style="width: 160px" data-searchable onchange="this.form.submit()">
            <option value="">Semua Kelas</option>
            @foreach ($kelasList as $kelas)
                <option value="{{ $kelas->id }}" @selected(($filters['kelas_id'] ?? null) == $kelas->id)>{{ $kelas->nama_kelas }}</option>
            @endforeach
        </select>

        <x-filter-tingkat-jurusan :filters="$filters" :kelas-list="$kelasList" />

        <span class="filter-bar__note">
            Menampilkan {{ $baris->count() }} dari {{ number_format($baris->total(), 0, ',', '.') }} hari
        </span>
    </form>

    @if ($bolehEkspor)
        {{-- Two exports, not one with a date range: a day is a roll call and a
             month is a recap, and they answer different questions. --}}
        <x-card title="Ekspor Excel" meta="Rekap presensi siswa">
            <div class="grid-row grid-row--2">
                <form method="GET" action="{{ route('presensi.ekspor') }}" class="filter-bar">
                    <input type="hidden" name="mode" value="harian">

                    <label class="field__label" for="eksporTanggal">Per Hari</label>
                    <input class="input-hifi" type="date" id="eksporTanggal" name="tanggal"
                           value="{{ now()->toDateString() }}" style="width: 160px">

                    <select class="select-hifi" name="kelas_id" style="width: 150px" data-searchable>
                        <option value="">Semua Kelas</option>
                        @foreach ($kelasList as $kelas)
                            <option value="{{ $kelas->id }}">{{ $kelas->nama_kelas }}</option>
                        @endforeach
                    </select>

                    <button class="btn-hifi" type="submit">
                        <x-ikon nama="download" /> Unduh Harian
                    </button>
                </form>

                <form method="GET" action="{{ route('presensi.ekspor') }}" class="filter-bar">
                    <input type="hidden" name="mode" value="bulanan">

                    <label class="field__label" for="eksporBulan">Per Bulan</label>
                    <input class="input-hifi" type="month" id="eksporBulan" name="bulan"
                           value="{{ now()->format('Y-m') }}" style="width: 160px">

                    <select class="select-hifi" name="kelas_id" style="width: 150px" data-searchable>
                        <option value="">Semua Kelas</option>
                        @foreach ($kelasList as $kelas)
                            <option value="{{ $kelas->id }}">{{ $kelas->nama_kelas }}</option>
                        @endforeach
                    </select>

                    <button class="btn-hifi" type="submit">
                        <x-ikon nama="download" /> Unduh Bulanan
                    </button>
                </form>
            </div>

            <p class="field__hint mt-2">
                <strong>Harian</strong>: satu baris per siswa berisi status hari itu.
                <strong>Bulanan</strong>: rekap H/S/I/A per siswa, plus lembar “Detail Harian” berisi
                tabel tanggal × siswa.
            </p>
        </x-card>
    @endif

    <x-card title="Presensi per Hari" flush>
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
                    <tr>
                        <th><x-th-sort kolom="tanggal" label="Tanggal" bawaan /></th>
                        <th><x-th-sort kolom="kelas" label="Kelas" /></th>
                        <th class="is-num"><x-th-sort kolom="siswa" label="Total" /></th>
                        <th class="is-num"><x-th-sort kolom="hadir" label="H" /></th>
                        <th class="is-num"><x-th-sort kolom="sakit" label="S" /></th>
                        <th class="is-num"><x-th-sort kolom="izin" label="I" /></th>
                        <th class="is-num"><x-th-sort kolom="alpa" label="A" /></th>
                        <th><x-th-sort kolom="persen" label="Persentase Kehadiran" /></th>
                        <th class="is-num">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($baris as $hari)
                        @php
                            $tanggal = \Illuminate\Support\Carbon::parse($hari->tanggal);
                            $persen = $hari->total_siswa ? round($hari->hadir / $hari->total_siswa * 100) : 0;
                        @endphp
                        <tr>
                            <td class="is-muted is-nowrap">{{ $tanggal->format('d/m/Y') }}</td>
                            <td class="is-strong is-nowrap">{{ $hari->nama_kelas }}</td>
                            <td class="is-num">{{ $hari->total_siswa }}</td>
                            <td class="is-num">{{ $hari->hadir }}</td>
                            <td class="is-num">{{ $hari->sakit }}</td>
                            <td class="is-num">{{ $hari->izin }}</td>
                            <td class="is-num">{{ $hari->alpa }}</td>
                            <td>
                                <span class="meter-cell">
                                    <x-stack-bar :hadir="$hari->hadir" :sakit="$hari->sakit"
                                                 :izin="$hari->izin" :alpa="$hari->alpa" />
                                    <span class="is-strong">{{ $persen }}%</span>
                                </span>
                            </td>
                            <td class="is-num tbl__aksi">
                                <a class="btn-hifi btn-hifi--ghost btn-hifi--sm"
                                   href="{{ route('presensi-harian.show', [$hari->kelas_id, 'tanggal' => $tanggal->toDateString()]) }}">
                                    Lihat
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="empty-state">Belum ada presensi tercatat pada periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:foot>
            <span>
                Menampilkan {{ $baris->firstItem() ?? 0 }}–{{ $baris->lastItem() ?? 0 }}
                dari {{ number_format($baris->total(), 0, ',', '.') }} hari
            </span>
            <x-pager :paginator="$baris" />
        </x-slot:foot>
    </x-card>
@endsection
