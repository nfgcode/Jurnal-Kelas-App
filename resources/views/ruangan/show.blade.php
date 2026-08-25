@extends('layouts.app')

@section('title', 'Ruangan')

@section('content')
    @php
        $statusTone = ['aktif' => 'green', 'perbaikan' => 'yellow', 'nonaktif' => 'neutral'];
        $urutHari = ['Senin' => 1, 'Selasa' => 2, 'Rabu' => 3, 'Kamis' => 4, 'Jumat' => 5, 'Sabtu' => 6];
        $jadwal = $ruangan->jadwals->sortBy([
            fn ($a, $b) => ($urutHari[$a->hari] ?? 9) <=> ($urutHari[$b->hari] ?? 9),
            fn ($a, $b) => $a->jam_ke_mulai <=> $b->jam_ke_mulai,
        ]);
    @endphp

    <x-page-head :title="$ruangan->kode" :sub="$ruangan->nama . ' · ' . $ruangan->jenisLabel() . ' · ' . $ruangan->kapasitas . ' kursi'">
        <x-chip :tone="$statusTone[$ruangan->status] ?? 'neutral'" :label="ucfirst($ruangan->status)" />
        @if (Auth::user()->isAdmin())
            <a class="btn-hifi btn-hifi--ghost" href="{{ route('ruangan.edit', $ruangan) }}">Ubah</a>
        @endif
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('ruangan.index') }}">Kembali</a>
    </x-page-head>

    <div class="grid-row grid-row--4">
        <x-stat label="Kapasitas" :value="$ruangan->kapasitas" caption="kursi" />
        <x-stat label="Jadwal Memakai" :value="$ruangan->jadwals->count()" caption="slot per minggu" />
        <x-stat label="Kelas Menetap" :value="$ruangan->kelas->count()"
                :caption="$ruangan->kelas->isEmpty() ? 'bukan ruang kelas tetap' : $ruangan->kelas->pluck('nama_kelas')->take(2)->join(', ')" />
        <x-stat label="Lokasi" :value="$ruangan->gedung ?? '—'"
                :caption="$ruangan->lantai ? 'Lantai ' . $ruangan->lantai : 'lantai tidak dicatat'" />
    </div>

    @if ($ruangan->keterangan)
        <p class="field__hint mb-2"><x-ikon nama="info-circle" /> {{ $ruangan->keterangan }}</p>
    @endif

    <x-card title="Jadwal Mingguan di Ruangan Ini" flush>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Hari</th>
                        <th>JP</th>
                        <th>Waktu</th>
                        <th>Kelas</th>
                        <th>Mata Pelajaran</th>
                        <th>Guru</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($jadwal as $j)
                        <tr>
                            <td class="is-strong">{{ $j->hari }}</td>
                            <td class="is-muted">JP {{ $j->jpLabel() }}</td>
                            <td class="is-nowrap">{{ $j->waktuLabel() }}</td>
                            <td>{{ $j->kelas?->nama_kelas ?? '—' }}</td>
                            <td>{{ $j->mataPelajaran?->nama ?? '—' }}</td>
                            <td class="is-muted">{{ $j->guru?->nama ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-state">Belum ada jadwal yang memakai ruangan ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    @if (Auth::user()->isAdmin())
        <x-card title="Hapus Ruangan">
            <p class="field__hint">
                Ruangan yang masih dipakai kelas atau jadwal tidak bisa dihapus — ubah statusnya
                jadi <strong>Nonaktif</strong> agar tidak lagi ditawarkan saat menyusun jadwal.
            </p>
            <form method="POST" action="{{ route('ruangan.destroy', $ruangan) }}"
                  onsubmit="return confirm('Hapus ruangan {{ $ruangan->kode }}?')">
                @csrf
                @method('DELETE')
                <button class="btn-hifi btn-hifi--ghost" type="submit">Hapus Ruangan</button>
            </form>
        </x-card>
    @endif
@endsection
