@extends('layouts.app')

@section('title', 'Data Siswa')

@section('content')
    @php
        $total = max(1, $rekapPresensi->sum());
        $hadir = (int) ($rekapPresensi['hadir'] ?? 0);
        $statusChip = match ($siswa->status) {
            'lulus' => ['Lulus', 'khaki'],
            'nonaktif' => ['Nonaktif', 'neutral'],
            default => ['Aktif', 'green'],
        };
    @endphp

    <x-page-head :title="$siswa->nama"
                 :sub="'NIS ' . $siswa->nis . ' · ' . ($siswa->kelas?->nama_kelas ?? 'belum dikelaskan')">
        <x-chip :tone="$statusChip[1]" :label="$statusChip[0]" />
        @if ($siswa->is_ketua_kelas)
            <x-chip tone="yellow" label="Ketua Kelas" />
        @endif
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.siswa.edit', $siswa) }}">Ubah</a>
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.siswa.index') }}">Kembali</a>
    </x-page-head>

    <div class="grid-row grid-row--4">
        <x-stat label="Kehadiran" :value="round($hadir / $total * 100) . '%'"
                :caption="number_format($rekapPresensi->sum(), 0, ',', '.') . ' hari tercatat'" />
        <x-stat label="Sakit / Izin" :value="(int) ($rekapPresensi['sakit'] ?? 0) + (int) ($rekapPresensi['izin'] ?? 0)"
                caption="hari berketerangan" />
        <x-stat label="Alpa" :value="(int) ($rekapPresensi['alpa'] ?? 0)" caption="tanpa keterangan" />
        <x-stat label="Wali Kelas" :value="$siswa->kelas?->waliKelas?->nama ?? '—'"
                :caption="$siswa->kelas?->nama_kelas ?? 'belum dikelaskan'" />
    </div>

    <div class="grid-row grid-row--2">
        <x-card title="Data Sekolah">
            <div class="deflist">
                <div class="deflist__row"><span class="deflist__key">NIS</span><span class="deflist__val is-strong">{{ $siswa->nis }}</span></div>
                <div class="deflist__row"><span class="deflist__key">NISN</span><span class="deflist__val">{{ $siswa->nisn ?? '—' }}</span></div>
                <div class="deflist__row"><span class="deflist__key">Nama</span><span class="deflist__val">{{ $siswa->nama }}</span></div>
                <div class="deflist__row"><span class="deflist__key">Jenis Kelamin</span><span class="deflist__val">{{ ['L' => 'Laki-laki', 'P' => 'Perempuan'][$siswa->jenis_kelamin] ?? '—' }}</span></div>
                <div class="deflist__row"><span class="deflist__key">Kelas</span><span class="deflist__val">{{ $siswa->kelas?->nama_kelas ?? '—' }}</span></div>
                <div class="deflist__row"><span class="deflist__key">No. HP</span><span class="deflist__val">{{ $siswa->no_hp ?? '—' }}</span></div>
                <div class="deflist__row"><span class="deflist__key">Alamat</span><span class="deflist__val">{{ $siswa->alamat ?? '—' }}</span></div>
            </div>
        </x-card>

        <x-card title="Akun untuk Masuk">
            @if ($siswa->akun)
                <div class="deflist">
                    <div class="deflist__row"><span class="deflist__key">Username</span><span class="deflist__val is-strong">{{ $siswa->akun->username }}</span></div>
                    <div class="deflist__row"><span class="deflist__key">Email</span><span class="deflist__val">{{ $siswa->akun->email }}</span></div>
                    <div class="deflist__row"><span class="deflist__key">Status Akun</span><span class="deflist__val">{{ ucfirst($siswa->akun->status) }}</span></div>
                    <div class="deflist__row"><span class="deflist__key">Terakhir Aktif</span><span class="deflist__val">{{ $siswa->akun->last_active_at?->format('d/m/Y H:i') ?? 'belum pernah masuk' }}</span></div>
                </div>
                <a class="btn-hifi btn-hifi--ghost btn-hifi--sm mt-2" href="{{ route('admin.akun.show', $siswa->akun) }}">Kelola Akun</a>
            @else
                <p class="empty-state mb-0">
                    Siswa ini belum punya akun. Data kehadirannya tetap tercatat —
                    hanya saja ia belum bisa masuk ke sistem.
                </p>
            @endif
        </x-card>
    </div>
@endsection
