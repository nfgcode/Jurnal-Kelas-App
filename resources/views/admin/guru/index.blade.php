@extends('layouts.app')

@section('title', 'Data Guru')

@section('content')
    <x-page-head
        title="Data Guru"
        :sub="$statistik['total'] . ' guru terdaftar · ' . $statistik['aktif'] . ' aktif · ' . $statistik['wali'] . ' wali kelas'">
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.akun.index', ['role' => 'guru']) }}">Akun Guru</a>
        <a class="btn-hifi" href="{{ route('admin.guru.create') }}">+ Tambah Guru</a>
    </x-page-head>

    <p class="field__hint mb-2">
        <x-ikon nama="info-circle" />
        Halaman ini adalah <strong>data guru milik sekolah</strong> — nama, NIP, mapel yang diampu, perwalian.
        Username dan kata sandi dikelola di halaman <a href="{{ route('admin.akun.index') }}">Akun Pengguna</a>.
    </p>

    <div class="grid-row grid-row--4">
        <x-stat label="Total Guru" :value="$statistik['total']" caption="seluruh status" />
        <x-stat label="Aktif" :value="$statistik['aktif']"
                :caption="$statistik['total'] - $statistik['aktif'] . ' nonaktif'" />
        <x-stat label="Belum Punya Mapel" :value="$statistik['tanpaMapel']"
                :caption="$statistik['tanpaMapel'] === 0 ? 'semua siap dijadwalkan' : 'tidak bisa masuk jadwal'" />
        <x-stat label="Wali Kelas" :value="$statistik['wali']" caption="memegang perwalian" />
    </div>

    <form class="filter-bar" method="GET">
        <x-query-hidden />

        <label class="filter-bar__search">
            <x-ikon nama="search" />
            <input class="input-hifi" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                   placeholder="Cari nama, NIP, atau mapel...">
        </label>

        <select class="select-hifi" name="mata_pelajaran_id" style="width: 180px" data-searchable onchange="this.form.submit()">
            <option value="">Semua Mapel</option>
            @foreach ($mapelList as $mapel)
                <option value="{{ $mapel->id }}" @selected(($filters['mata_pelajaran_id'] ?? null) == $mapel->id)>{{ $mapel->nama }}</option>
            @endforeach
        </select>

        <select class="select-hifi" name="wali" style="width: 160px" onchange="this.form.submit()">
            <option value="">Semua Guru</option>
            <option value="ya" @selected(($filters['wali'] ?? null) === 'ya')>Wali Kelas</option>
            <option value="tidak" @selected(($filters['wali'] ?? null) === 'tidak')>Bukan Wali</option>
        </select>

        <select class="select-hifi" name="status" style="width: 140px" onchange="this.form.submit()">
            <option value="">Semua Status</option>
            @foreach (['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'] as $value => $label)
                <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <span class="filter-bar__note">
            Menampilkan {{ $guru->count() }} dari {{ number_format($guru->total(), 0, ',', '.') }}
        </span>
    </form>

    <x-card title="Daftar Guru" flush>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th><x-th-sort kolom="nip" label="NIP" bawaan /></th>
                        <th>Email</th>
                        <th><x-th-sort kolom="nama" label="Nama Guru" /></th>
                        <th>Mata Pelajaran</th>
                        <th>Wali Kelas</th>
                        <th class="is-num"><x-th-sort kolom="jadwal" label="Jadwal" /></th>
                        <th class="is-num"><x-th-sort kolom="status" label="Status" /></th>
                        <th class="is-num">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($guru as $g)
                        @php $mapel = $g->mataPelajaran->pluck('nama'); @endphp
                        <tr>
                            <td class="is-strong is-nowrap">{{ $g->nip }}</td>
                            <td class="is-muted">{{ $g->akun?->email ?? '—' }}</td>
                            <td>
                                <a class="name-cell text-decoration-none text-reset" href="{{ route('admin.guru.show', $g) }}">
                                    <span class="avatar avatar--xs">{{ $g->inisial() }}</span>
                                    {{ $g->nama }}
                                </a>
                            </td>
                            <td class="is-muted">
                                @if ($mapel->isEmpty())
                                    <x-chip tone="yellow" label="Belum ada" />
                                @else
                                    {{ $mapel->take(2)->join(', ') }}{{ $mapel->count() > 2 ? ' +' . ($mapel->count() - 2) : '' }}
                                @endif
                            </td>
                            <td class="is-muted">{{ $g->kelasWali->pluck('nama_kelas')->join(', ') ?: '—' }}</td>
                            <td class="is-num">{{ $g->jadwals_count }}</td>
                            <td class="is-num">
                                <x-chip :tone="$g->status === 'aktif' ? 'green' : 'neutral'" :label="ucfirst($g->status)" />
                            </td>
                            <td class="is-num tbl__aksi">
                                <a class="btn-hifi btn-hifi--ghost btn-hifi--sm" href="{{ route('admin.guru.edit', $g) }}">Ubah</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="empty-state">Tidak ada guru yang cocok dengan filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:foot>
            <span>
                Menampilkan {{ $guru->firstItem() ?? 0 }}–{{ $guru->lastItem() ?? 0 }}
                dari {{ number_format($guru->total(), 0, ',', '.') }} guru
            </span>
            <x-pager :paginator="$guru" />
        </x-slot:foot>
    </x-card>
@endsection
