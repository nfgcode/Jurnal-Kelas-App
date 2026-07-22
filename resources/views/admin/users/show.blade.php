@extends('layouts.app')

@section('title', 'Pengguna')

@section('content')
    @php
        $statusChip = match ($user->status) {
            'nonaktif' => ['Nonaktif', 'neutral'],
            'pending' => ['Pending', 'yellow'],
            default => ['Aktif', 'green'],
        };
        $totalPresensi = $rekapPresensi->sum() ?: 1;
    @endphp

    <x-page-head :title="$user->name" :sub="ucfirst($user->role) . ' · ' . ($user->nip ?? $user->nis ?? $user->email)">
        <x-chip :tone="$statusChip[1]" :label="$statusChip[0]" />
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.users.edit', $user) }}">Ubah</a>
    </x-page-head>

    <div class="grid-row" style="grid-template-columns: repeat(4, minmax(0, 1fr))">
        <x-stat label="Peran" :value="ucfirst($user->role)" :caption="$user->email" />
        <x-stat label="Terakhir Aktif" :value="$user->last_active_at?->format('d/m/Y') ?? '—'"
                :caption="$user->last_active_at?->diffForHumans() ?? 'belum pernah masuk'" />
        @if ($user->isGuru())
            <x-stat label="Jadwal Mengajar" :value="$user->jadwals_count ?? 0" caption="slot per minggu" />
            <x-stat label="Jurnal Ditulis" :value="$user->jurnals_count ?? 0" caption="semester berjalan" />
        @elseif ($user->isSiswa())
            <x-stat label="Kelas" :value="$user->kelas?->nama_kelas ?? '—'"
                    :caption="$user->is_ketua_kelas ? 'ketua kelas' : 'anggota kelas'" />
            <x-stat label="Kehadiran" :value="round(($rekapPresensi['hadir'] ?? 0) / $totalPresensi * 100) . '%'"
                    :caption="$rekapPresensi->sum() . ' pertemuan'" />
        @else
            <x-stat label="Akses" value="Penuh" caption="seluruh modul" />
            <x-stat label="Dibuat" :value="$user->created_at?->format('d/m/Y') ?? '—'" caption="tanggal pendaftaran" />
        @endif
    </div>

    @if ($user->isGuru())
        <x-card title="Jadwal Mengajar" flush>
            <x-slot:actions><span class="card-hifi__meta">{{ $jadwals->count() }} slot</span></x-slot:actions>

            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr><th>Hari</th><th>JP</th><th>Kelas</th><th>Mata Pelajaran</th><th class="is-num">Ruang</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($jadwals->sortBy(['hari', 'jam_ke_mulai']) as $jadwal)
                            <tr>
                                <td class="is-muted is-nowrap">{{ $jadwal->hari }}</td>
                                <td class="is-muted">{{ $jadwal->jpLabel() }}</td>
                                <td class="is-strong">{{ $jadwal->kelas?->nama_kelas }}</td>
                                <td>{{ $jadwal->mataPelajaran?->nama }}</td>
                                <td class="is-num is-muted">{{ $jadwal->ruang ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty-state">Guru ini belum memiliki jadwal.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

    @if ($user->isSiswa())
        <x-card title="Rekap Kehadiran">
            <x-breakdown
                :items="[
                    'Hadir' => $rekapPresensi['hadir'] ?? 0,
                    'Sakit' => $rekapPresensi['sakit'] ?? 0,
                    'Izin' => $rekapPresensi['izin'] ?? 0,
                    'Alpa' => $rekapPresensi['alpa'] ?? 0,
                ]"
                :tones="['Hadir' => 'hadir', 'Sakit' => 'sakit', 'Izin' => 'izin', 'Alpa' => 'alpa']" />
        </x-card>
    @endif
@endsection
