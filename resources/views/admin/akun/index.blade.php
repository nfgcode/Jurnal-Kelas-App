@extends('layouts.app')

@section('title', 'Akun Pengguna')

@section('content')
    <x-page-head
        title="Akun Pengguna"
        :sub="number_format($statistik['total'], 0, ',', '.') . ' akun · ' . $jumlahPerRole['admin'] . ' admin · ' . $jumlahPerRole['guru'] . ' guru · ' . number_format($jumlahPerRole['siswa'], 0, ',', '.') . ' siswa'">
        <a class="btn-hifi" href="{{ route('admin.akun.create') }}">+ Tambah Akun Admin</a>
    </x-page-head>

    <p class="field__hint mb-2">
        <x-ikon nama="shield-lock" />
        Halaman ini hanya mengatur <strong>hak masuk</strong>: username, email, kata sandi, status.
        Nama dan data orangnya ada di <a href="{{ route('admin.guru.index') }}">Data Guru</a> dan
        <a href="{{ route('admin.siswa.index') }}">Data Siswa</a> — akun guru dan siswa juga dibuat dari sana.
    </p>

    <div class="grid-row grid-row--4">
        <x-stat label="Total Akun" :value="number_format($statistik['total'], 0, ',', '.')" caption="seluruh peran" />
        <x-stat label="Aktif" :value="number_format($statistik['aktif'], 0, ',', '.')"
                :caption="$statistik['pending'] . ' menunggu verifikasi'" />
        <x-stat label="Nonaktif" :value="$statistik['nonaktif']" caption="tidak dapat masuk" />
        <x-stat label="Belum Pernah Masuk" :value="number_format($statistik['belumPernahMasuk'], 0, ',', '.')"
                caption="kredensial belum dipakai" />
    </div>

    <form class="filter-bar" method="GET">
        <x-query-hidden />

        <label class="filter-bar__search">
            <x-ikon nama="search" />
            <input class="input-hifi" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                   placeholder="Cari username, email, NIP, atau NIS...">
        </label>

        <select class="select-hifi" name="role" style="width: 160px" onchange="this.form.submit()">
            <option value="">Semua Peran</option>
            @foreach (['admin' => 'Administrator', 'guru' => 'Guru', 'siswa' => 'Siswa'] as $value => $label)
                <option value="{{ $value }}" @selected(($filters['role'] ?? null) === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <select class="select-hifi" name="tertaut" style="width: 180px" onchange="this.form.submit()">
            <option value="">Semua Akun</option>
            <option value="ya" @selected(($filters['tertaut'] ?? null) === 'ya')>Tertaut ke Data Orang</option>
            <option value="tidak" @selected(($filters['tertaut'] ?? null) === 'tidak')>Tanpa Data Orang</option>
        </select>

        <select class="select-hifi" name="status" style="width: 140px" onchange="this.form.submit()">
            <option value="">Semua Status</option>
            @foreach (['aktif' => 'Aktif', 'pending' => 'Pending', 'nonaktif' => 'Nonaktif'] as $value => $label)
                <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <span class="filter-bar__note">
            Menampilkan {{ $akun->count() }} dari {{ number_format($akun->total(), 0, ',', '.') }}
        </span>
    </form>

    <x-card title="Daftar Akun" flush>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th><x-th-sort kolom="nip_nis" label="NIP / NIS" /></th>
                        <th><x-th-sort kolom="email" label="Email" /></th>
                        <th><x-th-sort kolom="username" label="Username" /></th>
                        <th>Nama Pemilik</th>
                        <th><x-th-sort kolom="peran" label="Peran" /></th>
                        <th><x-th-sort kolom="aktif" label="Terakhir Aktif" bawaan /></th>
                        <th class="is-num"><x-th-sort kolom="status" label="Status" /></th>
                        <th class="is-num">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($akun as $a)
                        @php
                            $statusChip = match ($a->status) {
                                'nonaktif' => ['Nonaktif', 'neutral'],
                                'pending' => ['Pending', 'yellow'],
                                default => ['Aktif', 'green'],
                            };
                            $halamanOrang = $a->guru
                                ? route('admin.guru.show', $a->guru)
                                : ($a->siswa ? route('admin.siswa.show', $a->siswa) : null);
                        @endphp
                        <tr>
                            <td class="is-strong is-nowrap">{{ $a->nip ?? $a->nis ?? '—' }}</td>
                            <td class="is-muted">{{ $a->email }}</td>
                            <td>
                                <a class="name-cell text-decoration-none text-reset" href="{{ route('admin.akun.show', $a) }}">
                                    <span class="avatar avatar--xs">{{ $a->inisial() }}</span>
                                    {{ $a->username }}
                                </a>
                            </td>
                            <td class="is-muted">
                                @if ($halamanOrang)
                                    <a href="{{ $halamanOrang }}">{{ $a->nama }}</a>
                                @else
                                    {{ $a->nama ?? '—' }}
                                @endif
                            </td>
                            <td class="is-strong">{{ ucfirst($a->role) }}</td>
                            <td class="is-muted">{{ $a->last_active_at?->format('d/m/Y') ?? 'belum pernah' }}</td>
                            <td class="is-num"><x-chip :tone="$statusChip[1]" :label="$statusChip[0]" /></td>
                            <td class="is-num tbl__aksi">
                                <a class="btn-hifi btn-hifi--ghost btn-hifi--sm" href="{{ route('admin.akun.edit', $a) }}">Ubah</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="empty-state">Tidak ada akun yang cocok dengan filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:foot>
            <span>
                Menampilkan {{ $akun->firstItem() ?? 0 }}–{{ $akun->lastItem() ?? 0 }}
                dari {{ number_format($akun->total(), 0, ',', '.') }} akun
            </span>
            <x-pager :paginator="$akun" />
        </x-slot:foot>
    </x-card>
@endsection
