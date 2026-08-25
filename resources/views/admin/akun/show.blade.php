@extends('layouts.app')

@section('title', 'Akun Pengguna')

@section('content')
    @php
        $statusChip = match ($akun->status) {
            'nonaktif' => ['Nonaktif', 'neutral'],
            'pending' => ['Pending', 'yellow'],
            default => ['Aktif', 'green'],
        };
        $halamanOrang = $akun->guru
            ? route('admin.guru.show', $akun->guru)
            : ($akun->siswa ? route('admin.siswa.show', $akun->siswa) : null);
    @endphp

    <x-page-head :title="$akun->username" :sub="$akun->nama . ' · ' . ucfirst($akun->role)">
        <x-chip :tone="$statusChip[1]" :label="$statusChip[0]" />
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.akun.edit', $akun) }}">Ubah</a>
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.akun.index') }}">Kembali</a>
    </x-page-head>

    <div class="grid-row grid-row--2">
        <x-card title="Kredensial">
            <div class="deflist">
                <div class="deflist__row"><span class="deflist__key">Username</span><span class="deflist__val is-strong">{{ $akun->username }}</span></div>
                <div class="deflist__row"><span class="deflist__key">Email</span><span class="deflist__val">{{ $akun->email }}</span></div>
                <div class="deflist__row"><span class="deflist__key">Peran</span><span class="deflist__val">{{ ucfirst($akun->role) }}</span></div>
                <div class="deflist__row"><span class="deflist__key">Status</span><span class="deflist__val">{{ ucfirst($akun->status) }}</span></div>
                <div class="deflist__row"><span class="deflist__key">Terakhir Aktif</span><span class="deflist__val">{{ $akun->last_active_at?->format('d/m/Y H:i') ?? 'belum pernah masuk' }}</span></div>
                <div class="deflist__row"><span class="deflist__key">Dibuat</span><span class="deflist__val">{{ $akun->created_at?->format('d/m/Y') }}</span></div>
            </div>
        </x-card>

        <x-card title="Data Orang">
            @if ($halamanOrang)
                <div class="deflist">
                    <div class="deflist__row"><span class="deflist__key">{{ $akun->nip ? 'NIP' : 'NIS' }}</span><span class="deflist__val is-strong">{{ $akun->nip ?? $akun->nis }}</span></div>
                    <div class="deflist__row"><span class="deflist__key">Nama</span><span class="deflist__val">{{ $akun->nama }}</span></div>
                    @if ($akun->guru)
                        <div class="deflist__row">
                            <span class="deflist__key">Mapel Diampu</span>
                            <span class="deflist__val">{{ $akun->guru->mataPelajaran->pluck('nama')->join(', ') ?: '—' }}</span>
                        </div>
                        <div class="deflist__row">
                            <span class="deflist__key">Wali Kelas</span>
                            <span class="deflist__val">{{ $akun->guru->kelasWali->pluck('nama_kelas')->join(', ') ?: '—' }}</span>
                        </div>
                    @else
                        <div class="deflist__row"><span class="deflist__key">Kelas</span><span class="deflist__val">{{ $akun->siswa->kelas?->nama_kelas ?? '—' }}</span></div>
                    @endif
                </div>
                <a class="btn-hifi btn-hifi--ghost btn-hifi--sm mt-2" href="{{ $halamanOrang }}">Buka Data Lengkap</a>
            @else
                <p class="empty-state mb-0">
                    Akun administrator tidak terhubung ke data guru atau siswa manapun —
                    ia bukan pengajar maupun murid, jadi namanya tersimpan di akun ini sendiri.
                </p>
            @endif
        </x-card>
    </div>
@endsection
