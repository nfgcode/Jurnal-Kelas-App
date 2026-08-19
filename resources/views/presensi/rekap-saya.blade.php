@extends('layouts.app')

@section('title', 'Presensi')

@section('content')
    @php
        $jumlah = array_sum($rekap);
        $total = $jumlah ?: 1;
        $user = Auth::user();
        $persen = round($rekap['hadir'] / $total * 100);
        $predikat = match (true) {
            $persen >= 95 => ['Sangat Baik', 'green'],
            $persen >= 90 => ['Baik', 'green'],
            $persen >= 80 => ['Cukup', 'khaki'],
            default => ['Perhatian', 'yellow'],
        };
    @endphp

    <x-page-head
        title="Rekap Kehadiran Saya"
        :sub="collect([$user->name, $user->kelas?->nama_kelas, number_format($jumlah, 0, ',', '.') . ' hari tercatat', $periode->label()])->filter()->join(' · ')">
        <x-periode-filter :periode="$periode" />
        {{-- Exporting a recap is a guru/admin job; a student only reads their own. --}}
    </x-page-head>

    <div class="grid-row grid-row--4">
        <x-stat label="Hadir" :value="number_format($rekap['hadir'], 0, ',', '.')"
                :caption="'dari ' . number_format($jumlah, 0, ',', '.') . ' hari sekolah'" />
        <x-stat label="Sakit" :value="$rekap['sakit']" :caption="round($rekap['sakit'] / $total * 100) . '% dari total'" />
        <x-stat label="Izin" :value="$rekap['izin']" :caption="round($rekap['izin'] / $total * 100) . '% dari total'" />
        <x-stat label="Alpa" :value="$rekap['alpa']" caption="batas maksimal 10%" />
    </div>

    <div class="grid-row">
        <x-card title="Persentase Kehadiran" :meta="$periode->label()">
            <span class="meter-cell">
                <x-stack-bar :hadir="$rekap['hadir']" :sakit="$rekap['sakit']"
                             :izin="$rekap['izin']" :alpa="$rekap['alpa']" />
                <span class="is-strong">{{ $persen }}%</span>
                <x-chip :tone="$predikat[1]" :label="$predikat[0]" />
            </span>
        </x-card>
    </div>

    <form class="filter-bar" method="GET">
        <x-query-hidden />

        <select class="select-hifi" name="status" style="width: 180px" onchange="this.form.submit()">
            <option value="">Semua Status</option>
            @foreach (['hadir' => 'Hadir', 'sakit' => 'Sakit', 'izin' => 'Izin', 'alpa' => 'Alpa'] as $nilai => $label)
                <option value="{{ $nilai }}" @selected(($filters['status'] ?? null) === $nilai)>{{ $label }}</option>
            @endforeach
        </select>

        <span class="filter-bar__note">
            Menampilkan {{ $riwayat->count() }} dari {{ number_format($riwayat->total(), 0, ',', '.') }} hari
        </span>
    </form>

    <x-card title="Riwayat Kehadiran Harian" flush>
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
                    <tr><th>Tanggal</th><th>Hari</th><th>Kelas</th><th>Status</th><th class="is-num">Keterangan</th></tr>
                </thead>
                <tbody>
                    @forelse ($riwayat as $baris)
                        @php
                            $tone = match ($baris->status) {
                                'hadir' => 'green',
                                'sakit' => 'khaki',
                                'izin' => 'yellow',
                                default => 'red',
                            };
                        @endphp
                        <tr>
                            <td class="is-strong is-nowrap">{{ $baris->tanggal->format('d/m/Y') }}</td>
                            <td class="is-muted">{{ $baris->tanggal->translatedFormat('l') }}</td>
                            <td class="is-muted">{{ $baris->kelas?->nama_kelas }}</td>
                            <td><x-chip :tone="$tone" :label="ucfirst($baris->status)" /></td>
                            <td class="is-num is-muted">{{ $baris->keterangan ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-state">Belum ada catatan kehadiran pada periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:foot>
            <span>
                Menampilkan {{ $riwayat->firstItem() ?? 0 }}–{{ $riwayat->lastItem() ?? 0 }}
                dari {{ number_format($riwayat->total(), 0, ',', '.') }} hari
            </span>
            <x-pager :paginator="$riwayat" />
        </x-slot:foot>
    </x-card>
@endsection
