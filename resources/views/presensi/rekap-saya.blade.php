@extends('layouts.app')

@section('title', 'Presensi')

@section('content')
    @php
        $total = array_sum($rekap) ?: 1;
        $user = Auth::user();
    @endphp

    <x-page-head
        title="Rekap Kehadiran Saya"
        :sub="collect([$user->name, $user->kelas?->nama_kelas, number_format(array_sum($rekap), 0, ',', '.') . ' pertemuan tercatat', $periode->label()])->filter()->join(' · ')">
        <x-periode-filter :periode="$periode" />
        {{-- Exporting a recap is an admin job; a student only reads their own. --}}
    </x-page-head>

    <div class="grid-row grid-row--4">
        <x-stat label="Hadir" :value="number_format($rekap['hadir'], 0, ',', '.')"
                :caption="'dari ' . number_format(array_sum($rekap), 0, ',', '.') . ' pertemuan'" />
        <x-stat label="Sakit" :value="$rekap['sakit']" :caption="round($rekap['sakit'] / $total * 100) . '% dari total'" />
        <x-stat label="Izin" :value="$rekap['izin']" :caption="round($rekap['izin'] / $total * 100) . '% dari total'" />
        <x-stat label="Alpa" :value="$rekap['alpa']" caption="batas maksimal 10%" />
    </div>

    <form class="filter-bar" method="GET">
        <x-query-hidden />

        <label class="filter-bar__search">
            <i class="bi bi-search"></i>
            <input class="input-hifi" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                   placeholder="Cari mata pelajaran...">
        </label>

        <select class="select-hifi" name="mata_pelajaran_id" style="width: 180px" onchange="this.form.submit()">
            <option value="">Semua Mapel</option>
            @foreach ($mapelList as $mapel)
                <option value="{{ $mapel->id }}" @selected(($filters['mata_pelajaran_id'] ?? null) == $mapel->id)>{{ $mapel->nama }}</option>
            @endforeach
        </select>

        <span class="filter-bar__note">Menampilkan {{ $perMapel->count() }} mata pelajaran</span>
    </form>

    <x-card title="Kehadiran per Mata Pelajaran" flush>
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
                        <th>Mata Pelajaran</th>
                        <th>Guru</th>
                        <th class="is-num">Total</th>
                        <th class="is-num">H</th>
                        <th class="is-num">S</th>
                        <th class="is-num">I</th>
                        <th class="is-num">A</th>
                        <th>Persentase Kehadiran</th>
                        <th class="is-num">Predikat</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($perMapel as $nama => $baris)
                        @php
                            $status = $baris['status'];
                            $hadir = (int) ($status['hadir'] ?? 0);
                            $persen = round($hadir / max(1, $baris['total']) * 100);
                            $predikat = match (true) {
                                $persen >= 95 => ['Sangat Baik', 'green'],
                                $persen >= 90 => ['Baik', 'green'],
                                $persen >= 80 => ['Cukup', 'khaki'],
                                default => ['Perhatian', 'yellow'],
                            };
                        @endphp
                        <tr>
                            <td class="is-strong is-nowrap">{{ $nama }}</td>
                            <td class="is-muted is-nowrap">{{ $baris['guru'] }}</td>
                            <td class="is-num">{{ $baris['total'] }}</td>
                            <td class="is-num">{{ $hadir }}</td>
                            <td class="is-num">{{ $status['sakit'] ?? 0 }}</td>
                            <td class="is-num">{{ $status['izin'] ?? 0 }}</td>
                            <td class="is-num">{{ $status['alpa'] ?? 0 }}</td>
                            <td>
                                <span class="meter-cell">
                                    <x-stack-bar :hadir="$hadir" :sakit="$status['sakit'] ?? 0"
                                                 :izin="$status['izin'] ?? 0" :alpa="$status['alpa'] ?? 0" />
                                    <span class="is-strong">{{ $persen }}%</span>
                                </span>
                            </td>
                            <td class="is-num"><x-chip :tone="$predikat[1]" :label="$predikat[0]" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="empty-state">Belum ada catatan kehadiran.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:foot>
            <span>Menampilkan {{ $perMapel->count() }} mata pelajaran</span>
        </x-slot:foot>
    </x-card>
@endsection
