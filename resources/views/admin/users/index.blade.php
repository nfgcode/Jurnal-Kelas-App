@extends('layouts.app')

@section('title', 'Pengguna')

@section('content')
    <x-page-head
        title="Kelola Pengguna"
        :sub="number_format($statistik['total'], 0, ',', '.') . ' akun terdaftar · ' . $jumlahPerRole['guru'] . ' guru · ' . number_format($jumlahPerRole['siswa'], 0, ',', '.') . ' siswa'">
        <form method="GET" class="d-flex gap-2">
            <select class="select-hifi" name="role" style="width: 170px" onchange="this.form.submit()">
                <option value="">Semua Peran</option>
                @foreach (['admin' => 'Administrator', 'guru' => 'Guru', 'siswa' => 'Siswa'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['role'] ?? null) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </form>
        <a class="btn-hifi" href="{{ route('admin.users.create') }}">+ Tambah Pengguna</a>
    </x-page-head>

    <div class="grid-row" style="grid-template-columns: repeat(4, minmax(0, 1fr))">
        <x-stat label="Total Pengguna" :value="number_format($statistik['total'], 0, ',', '.')"
                caption="seluruh peran" />
        <x-stat label="Guru Aktif" :value="$statistik['guruAktif']"
                :caption="$statistik['guruPending'] . ' menunggu verifikasi'" />
        <x-stat label="Siswa Aktif" :value="number_format($statistik['siswaAktif'], 0, ',', '.')"
                :caption="'tersebar di ' . $statistik['kelas'] . ' kelas'" />
        <x-stat label="Akun Nonaktif" :value="$statistik['nonaktif']" caption="tidak dapat masuk" />
    </div>

    <form class="filter-bar" method="GET">
        <label class="filter-bar__search">
            <i class="bi bi-search"></i>
            <input class="input-hifi" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                   placeholder="Cari nama, NIP atau NIS...">
        </label>

        <select class="select-hifi" name="role" style="width: 150px" onchange="this.form.submit()">
            <option value="">Semua Peran</option>
            @foreach (['admin' => 'Administrator', 'guru' => 'Guru', 'siswa' => 'Siswa'] as $value => $label)
                <option value="{{ $value }}" @selected(($filters['role'] ?? null) === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <select class="select-hifi" name="kelas_id" style="width: 150px" onchange="this.form.submit()">
            <option value="">Semua Kelas</option>
            @foreach ($kelasList as $kelas)
                <option value="{{ $kelas->id }}" @selected(($filters['kelas_id'] ?? null) == $kelas->id)>{{ $kelas->nama_kelas }}</option>
            @endforeach
        </select>

        <select class="select-hifi" name="status" style="width: 130px" onchange="this.form.submit()">
            <option value="">Status</option>
            @foreach (['aktif' => 'Aktif', 'pending' => 'Pending', 'nonaktif' => 'Nonaktif'] as $value => $label)
                <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <span class="filter-bar__note">
            Menampilkan {{ $users->count() }} dari {{ number_format($users->total(), 0, ',', '.') }}
        </span>
    </form>

    <x-card title="Daftar Pengguna" flush>
        <x-slot:actions>
            <span class="card-hifi__meta">diurutkan: terakhir aktif</span>
        </x-slot:actions>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>NIP / NIS</th>
                        <th>Peran</th>
                        <th>Mata Pelajaran</th>
                        <th>Kelas</th>
                        <th>Terakhir Aktif</th>
                        <th class="is-num">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        @php
                            $mapel = $user->jadwals->pluck('mataPelajaran.nama')->filter()->unique();
                            $kelasDiampu = $user->jadwals->pluck('kelas.nama_kelas')->filter()->unique();
                            $statusChip = match ($user->status) {
                                'nonaktif' => ['Nonaktif', 'neutral'],
                                'pending' => ['Pending', 'yellow'],
                                default => ['Aktif', 'green'],
                            };
                        @endphp
                        <tr>
                            <td>
                                <a class="name-cell text-decoration-none text-reset" href="{{ route('admin.users.show', $user) }}">
                                    <span class="avatar avatar--xs">{{ $user->inisial() }}</span>
                                    {{ $user->name }}
                                </a>
                            </td>
                            <td class="is-muted">{{ $user->nip ?? $user->nis ?? '—' }}</td>
                            <td class="is-strong">{{ ucfirst($user->role) }}</td>
                            <td class="is-muted">{{ $mapel->isEmpty() ? '—' : $mapel->take(2)->join(', ') . ($mapel->count() > 2 ? ' +' . ($mapel->count() - 2) : '') }}</td>
                            <td class="is-muted">
                                @if ($user->isGuru())
                                    {{ $kelasDiampu->count() }} kelas
                                @else
                                    {{ $user->kelas?->nama_kelas ?? '—' }}
                                @endif
                            </td>
                            <td class="is-muted">{{ $user->last_active_at?->format('d/m/Y') ?? '—' }}</td>
                            <td class="is-num"><x-chip :tone="$statusChip[1]" :label="$statusChip[0]" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty-state">Tidak ada pengguna yang cocok dengan filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:foot>
            <span>
                Menampilkan {{ $users->firstItem() ?? 0 }}–{{ $users->lastItem() ?? 0 }}
                dari {{ number_format($users->total(), 0, ',', '.') }} pengguna
            </span>
            <x-pager :paginator="$users" />
        </x-slot:foot>
    </x-card>
@endsection
