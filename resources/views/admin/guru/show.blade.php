@extends('layouts.app')

@section('title', 'Data Guru')

@section('content')
    @php
        $urutHari = ['Senin' => 1, 'Selasa' => 2, 'Rabu' => 3, 'Kamis' => 4, 'Jumat' => 5, 'Sabtu' => 6];
        $terurut = $jadwals->sortBy([
            fn ($a, $b) => ($urutHari[$a->hari] ?? 9) <=> ($urutHari[$b->hari] ?? 9),
            fn ($a, $b) => $a->jam_ke_mulai <=> $b->jam_ke_mulai,
        ]);
        $utama = $guru->mataPelajaran->firstWhere('pivot.utama', true);
    @endphp

    <x-page-head :title="$guru->nama" :sub="'NIP ' . $guru->nip . ' · ' . $guru->jadwals_count . ' jadwal mingguan'">
        <x-chip :tone="$guru->status === 'aktif' ? 'green' : 'neutral'" :label="ucfirst($guru->status)" />
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.guru.edit', $guru) }}">Ubah</a>
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.guru.index') }}">Kembali</a>
    </x-page-head>

    <div class="grid-row grid-row--4">
        <x-stat label="Mata Pelajaran" :value="$guru->mataPelajaran->count()"
                :caption="$utama?->nama ?? ($guru->mataPelajaran->first()?->nama ?? 'belum ditetapkan')" />
        <x-stat label="Jadwal Mingguan" :value="$guru->jadwals_count" caption="slot per minggu" />
        <x-stat label="Jurnal Ditulis" :value="number_format($guru->jurnals_count, 0, ',', '.')" caption="sepanjang waktu" />
        <x-stat label="Wali Kelas" :value="$guru->kelasWali->count()"
                :caption="$guru->kelasWali->pluck('nama_kelas')->join(', ') ?: 'tidak memegang perwalian'" />
    </div>

    <div class="grid-row grid-row--2">
        <x-card title="Data Kepegawaian">
            <div class="deflist">
                <div class="deflist__row"><span class="deflist__key">NIP</span><span class="deflist__val is-strong">{{ $guru->nip }}</span></div>
                <div class="deflist__row"><span class="deflist__key">Nama</span><span class="deflist__val">{{ $guru->nama }}</span></div>
                <div class="deflist__row"><span class="deflist__key">Jenis Kelamin</span><span class="deflist__val">{{ ['L' => 'Laki-laki', 'P' => 'Perempuan'][$guru->jenis_kelamin] ?? '—' }}</span></div>
                <div class="deflist__row"><span class="deflist__key">No. HP</span><span class="deflist__val">{{ $guru->no_hp ?? '—' }}</span></div>
                <div class="deflist__row"><span class="deflist__key">Alamat</span><span class="deflist__val">{{ $guru->alamat ?? '—' }}</span></div>
                <div class="deflist__row">
                    <span class="deflist__key">Mapel Diampu</span>
                    <span class="deflist__val">
                        @forelse ($guru->mataPelajaran as $mapel)
                            <x-chip :tone="$mapel->pivot->utama ? 'green' : 'neutral'" :label="$mapel->nama" />
                        @empty
                            <x-chip tone="yellow" label="Belum ada" />
                        @endforelse
                    </span>
                </div>
            </div>
        </x-card>

        <x-card title="Akun untuk Masuk">
            @if ($guru->akun)
                <div class="deflist">
                    <div class="deflist__row"><span class="deflist__key">Username</span><span class="deflist__val is-strong">{{ $guru->akun->username }}</span></div>
                    <div class="deflist__row"><span class="deflist__key">Email</span><span class="deflist__val">{{ $guru->akun->email }}</span></div>
                    <div class="deflist__row"><span class="deflist__key">Status Akun</span><span class="deflist__val">{{ ucfirst($guru->akun->status) }}</span></div>
                    <div class="deflist__row"><span class="deflist__key">Terakhir Aktif</span><span class="deflist__val">{{ $guru->akun->last_active_at?->format('d/m/Y H:i') ?? 'belum pernah masuk' }}</span></div>
                </div>
                <a class="btn-hifi btn-hifi--ghost btn-hifi--sm mt-2" href="{{ route('admin.akun.show', $guru->akun) }}">Kelola Akun</a>
            @else
                <p class="empty-state mb-0">
                    Guru ini belum punya akun. Jadwal dan jurnalnya tetap tercatat atas namanya —
                    ia hanya belum bisa masuk ke sistem.
                </p>
            @endif
        </x-card>
    </div>

    <x-card title="Jadwal Mengajar" flush>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr><th>Hari</th><th>JP</th><th>Waktu</th><th>Kelas</th><th>Mata Pelajaran</th><th class="is-num">Ruang</th></tr>
                </thead>
                <tbody>
                    @forelse ($terurut as $jadwal)
                        <tr>
                            <td class="is-strong is-nowrap">{{ $jadwal->hari }}</td>
                            <td class="is-muted">JP {{ $jadwal->jpLabel() }}</td>
                            <td class="is-nowrap">{{ $jadwal->waktuLabel() }}</td>
                            <td>{{ $jadwal->kelas?->nama_kelas ?? '—' }}</td>
                            <td class="is-muted">{{ $jadwal->mataPelajaran?->nama ?? '—' }}</td>
                            <td class="is-num is-muted">{{ $jadwal->ruang ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-state">Guru ini belum punya jadwal mengajar.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
