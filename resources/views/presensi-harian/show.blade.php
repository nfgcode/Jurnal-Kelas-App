@extends('layouts.app')

@section('title', 'Presensi Harian')

@section('content')
    @php
        $total = array_sum($rekap) ?: 1;
        $ditandai = array_sum($rekap);
    @endphp

    <x-page-head
        title="Presensi Harian"
        :sub="collect([$kelas->nama_kelas, $tanggal->translatedFormat('l, j F Y'), $pengisi ? 'diisi oleh ' . $pengisi->name : null])->filter()->join(' · ')">
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('presensi.index') }}">← Rekap Presensi</a>

        <form method="GET" class="d-inline-block">
            <input class="input-hifi" type="date" name="tanggal" value="{{ $tanggal->toDateString() }}"
                   onchange="this.form.submit()" aria-label="Pilih tanggal">
        </form>

        @if ($bolehIsi)
            <a class="btn-hifi" href="{{ route('presensi-harian.edit', [$kelas, 'tanggal' => $tanggal->toDateString()]) }}">
                {{ $ditandai ? 'Perbarui Presensi' : 'Isi Presensi' }}
            </a>
        @endif
    </x-page-head>

    <div class="grid-row grid-row--4">
        <x-stat label="Hadir" :value="$rekap['hadir']" :caption="round($rekap['hadir'] / $total * 100) . '% dari kelas'" />
        <x-stat label="Sakit" :value="$rekap['sakit']" caption="dengan keterangan" />
        <x-stat label="Izin" :value="$rekap['izin']" caption="dengan keterangan" />
        <x-stat label="Alpa" :value="$rekap['alpa']" caption="tanpa keterangan" />
    </div>

    <x-card :title="'Daftar Kehadiran — ' . $kelas->nama_kelas" flush>
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
                    <tr><th>No</th><th>NIS</th><th>Nama Siswa</th><th>Status</th><th class="is-num">Keterangan</th></tr>
                </thead>
                <tbody>
                    @forelse ($baris as $presensi)
                        @php
                            $tone = match ($presensi->status) {
                                'hadir' => 'green',
                                'sakit' => 'khaki',
                                'izin' => 'yellow',
                                default => 'red',
                            };
                        @endphp
                        <tr>
                            <td class="is-muted">{{ $loop->iteration }}</td>
                            <td class="is-muted">{{ $presensi->siswa?->nis }}</td>
                            <td>
                                <span class="name-cell">
                                    <span class="avatar avatar--xs">{{ $presensi->siswa?->inisial() }}</span>
                                    {{ $presensi->siswa?->name }}
                                </span>
                            </td>
                            <td><x-chip :tone="$tone" :label="ucfirst($presensi->status)" /></td>
                            <td class="is-num is-muted">{{ $presensi->keterangan ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-state">
                                Presensi kelas ini belum diisi untuk {{ $tanggal->translatedFormat('j F Y') }}.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:foot>
            <span>{{ $baris->count() }} siswa tercatat</span>
            <a class="btn-hifi btn-hifi--ghost btn-hifi--sm" href="{{ route('presensi.index') }}">Kembali ke rekap</a>
        </x-slot:foot>
    </x-card>

    @if ($riwayat->isNotEmpty())
        <x-card title="Riwayat Pengisian" meta="Terbaru di atas" flush>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr><th>Waktu</th><th>Diisi Oleh</th><th class="is-num">Jumlah Siswa</th><th class="is-num">Jenis</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($riwayat as $entri)
                            <tr>
                                <td class="is-muted is-nowrap">{{ $entri->created_at?->translatedFormat('j M Y, H:i') }}</td>
                                <td>{{ $entri->dieditOleh?->name ?? '—' }}</td>
                                <td class="is-num">{{ $entri->jumlah_siswa }}</td>
                                <td class="is-num">
                                    <x-chip :tone="$entri->koreksi ? 'yellow' : 'green'"
                                            :label="$entri->koreksi ? 'Koreksi' : 'Pengisian Awal'" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif
@endsection
