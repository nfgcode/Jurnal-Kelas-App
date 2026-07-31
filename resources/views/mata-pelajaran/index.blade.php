@extends('layouts.app')

@section('title', 'Mata Pelajaran')

@section('content')
    @php
        $kelompokTone = [
            'wajib' => 'green',
            'peminatan' => 'khaki',
            'muatan_lokal' => 'neutral',
            'kejuruan' => 'solid',
        ];
    @endphp

    <x-page-head
        title="Mata Pelajaran"
        :sub="$statistik['total'] . ' mata pelajaran aktif · ' . number_format($statistik['totalJadwal'], 0, ',', '.') . ' jadwal terhubung'">
        <form method="GET">
            <select class="select-hifi" name="kelompok" style="width: 180px" onchange="this.form.submit()">
                <option value="">Semua Kelompok</option>
                @foreach (['wajib' => 'Wajib', 'peminatan' => 'Peminatan', 'muatan_lokal' => 'Muatan Lokal', 'kejuruan' => 'Kejuruan'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['kelompok'] ?? null) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </form>
        @if (Auth::user()->isAdmin())
            <a class="btn-hifi" href="{{ route('mata-pelajaran.create') }}">+ Tambah Mapel</a>
        @endif
    </x-page-head>

    <div class="grid-row grid-row--4">
        <x-stat label="Total Mapel" :value="$statistik['total']" caption="aktif semester ini" />
        <x-stat label="Total JP / Minggu" :value="number_format($statistik['totalJp'], 0, ',', '.')" caption="seluruh rombel" />
        <x-stat label="Guru Pengampu" :value="$statistik['guruPengampu']"
                :caption="'dari ' . $statistik['totalGuru'] . ' guru terdaftar'" />
        <x-stat label="Mapel Tanpa Guru" :value="$statistik['tanpaGuru']->count()"
                :caption="$statistik['tanpaGuru']->isEmpty() ? 'semua terisi' : $statistik['tanpaGuru']->take(2)->join(', ')" />
    </div>

    <form class="filter-bar" method="GET">
        <x-query-hidden />

        <label class="filter-bar__search">
            <x-ikon nama="search" />
            <input class="input-hifi" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                   placeholder="Cari mata pelajaran...">
        </label>

        <select class="select-hifi" name="kelompok" style="width: 180px" onchange="this.form.submit()">
            <option value="">Semua Kelompok</option>
            @foreach (['wajib' => 'Wajib', 'peminatan' => 'Peminatan', 'muatan_lokal' => 'Muatan Lokal', 'kejuruan' => 'Kejuruan'] as $value => $label)
                <option value="{{ $value }}" @selected(($filters['kelompok'] ?? null) === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <span class="filter-bar__note">
            Menampilkan {{ $mataPelajaran->count() }} dari {{ $mataPelajaran->total() }}
        </span>
    </form>

    <x-card title="Daftar Mata Pelajaran" flush>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th><x-th-sort kolom="nama" label="Mata Pelajaran" /></th>
                        <th><x-th-sort kolom="kelompok" label="Kelompok" /></th>
                        <th><x-th-sort kolom="jp" label="JP / Minggu" bawaan /></th>
                        <th>Guru Pengampu</th>
                        <th>Diajarkan Di</th>
                        <th>Kelengkapan Jurnal</th>
                        <th class="is-num">Status</th>
                        <th class="is-num">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($mataPelajaran as $mapel)
                        @php
                            $persen = $kelengkapan[$mapel->id] ?? 0;
                            $info = $ringkasan[$mapel->id] ?? null;
                        @endphp
                        <tr>
                            <td class="is-strong">{{ $mapel->nama }}</td>
                            <td><x-chip :tone="$kelompokTone[$mapel->kelompok] ?? 'neutral'" :label="$mapel->kelompokLabel()" /></td>
                            <td class="is-muted">{{ $mapel->jp_per_minggu }} JP</td>
                            <td>{{ $info?->guru_nama ?? 'Belum ditetapkan' }}</td>
                            <td class="is-muted">{{ $info->kelas_count ?? 0 }} kelas</td>
                            <td>
                                <span class="meter-cell">
                                    <x-meter :percent="$persen" />
                                    <span class="is-strong">{{ round($persen) }}%</span>
                                </span>
                            </td>
                            <td class="is-num">
                                @if (! $info)
                                    <x-chip tone="yellow" label="Perlu Guru" />
                                @else
                                    <x-chip tone="green" label="Aktif" />
                                @endif
                            </td>
                            <td class="is-num">
                                <a class="btn-hifi btn-hifi--ghost btn-hifi--sm" href="{{ route('mata-pelajaran.show', $mapel) }}">Lihat</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="empty-state">Tidak ada mata pelajaran yang cocok dengan filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:foot>
            <span>
                Menampilkan {{ $mataPelajaran->firstItem() ?? 0 }}–{{ $mataPelajaran->lastItem() ?? 0 }}
                dari {{ $mataPelajaran->total() }} mata pelajaran
            </span>
            <x-pager :paginator="$mataPelajaran" />
        </x-slot:foot>
    </x-card>
@endsection
