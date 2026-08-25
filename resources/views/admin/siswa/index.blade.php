@extends('layouts.app')

@section('title', 'Data Siswa')

@section('content')
    <x-page-head
        title="Data Siswa"
        :sub="number_format($statistik['total'], 0, ',', '.') . ' siswa terdaftar · ' . number_format($statistik['aktif'], 0, ',', '.') . ' aktif'">
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.akun.index', ['role' => 'siswa']) }}">Akun Siswa</a>
        <a class="btn-hifi" href="{{ route('admin.siswa.create') }}">+ Tambah Siswa</a>
    </x-page-head>

    <p class="field__hint mb-2">
        <x-ikon nama="info-circle" />
        Halaman ini adalah <strong>data siswa milik sekolah</strong> — nama, NIS, kelas.
        Username dan kata sandi untuk masuk dikelola di halaman <a href="{{ route('admin.akun.index') }}">Akun Pengguna</a>.
    </p>

    <div class="grid-row grid-row--4">
        <x-stat label="Total Siswa" :value="number_format($statistik['total'], 0, ',', '.')" caption="seluruh angkatan" />
        <x-stat label="Aktif" :value="number_format($statistik['aktif'], 0, ',', '.')"
                :caption="number_format($statistik['total'] - $statistik['aktif'], 0, ',', '.') . ' lulus/nonaktif'" />
        <x-stat label="Belum Punya Kelas" :value="$statistik['tanpaKelas']"
                :caption="$statistik['tanpaKelas'] === 0 ? 'semua sudah dikelaskan' : 'tidak masuk presensi manapun'" />
        <x-stat label="Ketua Kelas" :value="$statistik['ketua']" caption="berhak isi jurnal kelas" />
    </div>

    <form class="filter-bar" method="GET">
        <x-query-hidden />

        <label class="filter-bar__search">
            <x-ikon nama="search" />
            <input class="input-hifi" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                   placeholder="Cari nama, NIS, atau kelas...">
        </label>

        <x-filter-tingkat-jurusan :kelas-list="$kelasList" :filters="$filters" />

        <select class="select-hifi" name="kelas_id" style="width: 160px" data-searchable onchange="this.form.submit()">
            <option value="">Semua Kelas</option>
            @foreach ($kelasList as $kelas)
                <option value="{{ $kelas->id }}" @selected(($filters['kelas_id'] ?? null) == $kelas->id)>{{ $kelas->nama_kelas }}</option>
            @endforeach
        </select>

        <select class="select-hifi" name="status" style="width: 140px" onchange="this.form.submit()">
            <option value="">Semua Status</option>
            @foreach (['aktif' => 'Aktif', 'lulus' => 'Lulus', 'nonaktif' => 'Nonaktif'] as $value => $label)
                <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <span class="filter-bar__note">
            Menampilkan {{ $siswa->count() }} dari {{ number_format($siswa->total(), 0, ',', '.') }}
        </span>
    </form>

    <x-card title="Daftar Siswa" flush>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th><x-th-sort kolom="nis" label="NIS" bawaan /></th>
                        <th>Email</th>
                        <th><x-th-sort kolom="nama" label="Nama Siswa" /></th>
                        <th><x-th-sort kolom="kelas" label="Kelas" /></th>
                        <th>L/P</th>
                        <th>Akun</th>
                        <th class="is-num"><x-th-sort kolom="status" label="Status" /></th>
                        <th class="is-num">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($siswa as $s)
                        @php
                            $statusChip = match ($s->status) {
                                'lulus' => ['Lulus', 'khaki'],
                                'nonaktif' => ['Nonaktif', 'neutral'],
                                default => ['Aktif', 'green'],
                            };
                        @endphp
                        <tr>
                            <td class="is-strong is-nowrap">{{ $s->nis }}</td>
                            <td class="is-muted">{{ $s->akun?->email ?? '—' }}</td>
                            <td>
                                <a class="name-cell text-decoration-none text-reset" href="{{ route('admin.siswa.show', $s) }}">
                                    <span class="avatar avatar--xs">{{ $s->inisial() }}</span>
                                    {{ $s->nama }}
                                    @if ($s->is_ketua_kelas)
                                        <x-chip tone="yellow" label="Ketua" />
                                    @endif
                                </a>
                            </td>
                            <td class="is-muted">{{ $s->kelas?->nama_kelas ?? '—' }}</td>
                            <td class="is-muted">{{ $s->jenis_kelamin ?? '—' }}</td>
                            <td class="is-muted">{{ $s->akun ? $s->akun->username : 'belum ada' }}</td>
                            <td class="is-num"><x-chip :tone="$statusChip[1]" :label="$statusChip[0]" /></td>
                            <td class="is-num tbl__aksi">
                                <a class="btn-hifi btn-hifi--ghost btn-hifi--sm" href="{{ route('admin.siswa.edit', $s) }}">Ubah</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="empty-state">Tidak ada siswa yang cocok dengan filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:foot>
            <span>
                Menampilkan {{ $siswa->firstItem() ?? 0 }}–{{ $siswa->lastItem() ?? 0 }}
                dari {{ number_format($siswa->total(), 0, ',', '.') }} siswa
            </span>
            <x-pager :paginator="$siswa" />
        </x-slot:foot>
    </x-card>
@endsection
