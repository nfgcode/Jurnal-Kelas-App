@extends('layouts.app')

@section('title', 'Jurusan')

{{--
    Data Jurusan (admin). Frontend only — the controller is still to be built
    (see docs/backend-jurusan.md). Expects:
      $jurusan     LengthAwarePaginator<Jurusan>, withCount(['kelas', 'siswa'])
      $filters     ['q' => ?string, 'bidang' => ?string, 'status' => 'aktif'|'nonaktif'|null]
      $bidangList  Collection<string> — distinct, non-empty bidang
      $statistik   ['total' => int, 'aktif' => int, 'bidang' => int, 'kelas' => int, 'siswa' => int]
    Sort keys (?sort=&dir=): kode (default), nama, bidang, kelas, siswa.
--}}

@section('content')
    <x-page-head
        title="Data Jurusan"
        :sub="$statistik['total'] . ' jurusan · ' . $statistik['aktif'] . ' aktif · dipakai ' . $statistik['kelas'] . ' kelas'">
        <a class="btn-hifi" href="{{ route('admin.jurusan.create') }}">+ Tambah Jurusan</a>
    </x-page-head>

    <div class="grid-row grid-row--4">
        <x-stat label="Total Jurusan" :value="$statistik['total']"
                :caption="$statistik['aktif'] . ' aktif · ' . ($statistik['total'] - $statistik['aktif']) . ' nonaktif'" />
        <x-stat label="Bidang Keahlian" :value="$statistik['bidang']"
                :caption="$bidangList->isEmpty() ? 'belum ada bidang' : $bidangList->take(2)->join(', ')" />
        <x-stat label="Kelas Terhubung" :value="$statistik['kelas']" caption="rombel memakai jurusan" />
        <x-stat label="Siswa Terdaftar" :value="number_format($statistik['siswa'], 0, ',', '.')" caption="tercatat di jurusan" />
    </div>

    <form class="filter-bar" method="GET">
        <x-query-hidden />

        <label class="filter-bar__search">
            <x-ikon nama="search" />
            <input class="input-hifi" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                   placeholder="Cari kode, nama, atau bidang...">
        </label>

        <select class="select-hifi" name="bidang" style="width: 210px" onchange="this.form.submit()">
            <option value="">Semua Bidang</option>
            @foreach ($bidangList as $bidang)
                <option value="{{ $bidang }}" @selected(($filters['bidang'] ?? null) === $bidang)>{{ $bidang }}</option>
            @endforeach
        </select>

        <select class="select-hifi" name="status" style="width: 160px" onchange="this.form.submit()">
            <option value="">Semua Status</option>
            <option value="aktif" @selected(($filters['status'] ?? null) === 'aktif')>Aktif</option>
            <option value="nonaktif" @selected(($filters['status'] ?? null) === 'nonaktif')>Nonaktif</option>
        </select>

        <span class="filter-bar__note">
            Menampilkan {{ $jurusan->count() }} dari {{ $jurusan->total() }}
        </span>
    </form>

    <x-card title="Daftar Jurusan" flush>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th><x-th-sort kolom="kode" label="Kode" bawaan /></th>
                        <th><x-th-sort kolom="nama" label="Nama Jurusan" /></th>
                        <th><x-th-sort kolom="bidang" label="Bidang Keahlian" /></th>
                        <th><x-th-sort kolom="kelas" label="Kelas" /></th>
                        <th><x-th-sort kolom="siswa" label="Siswa" /></th>
                        <th class="is-num">Status</th>
                        <th class="is-num">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($jurusan as $j)
                        <tr>
                            <td class="is-strong is-nowrap">{{ $j->kode }}</td>
                            <td>{{ $j->nama }}</td>
                            <td class="is-muted">{{ $j->bidang ?: '—' }}</td>
                            <td class="is-muted is-nowrap">{{ $j->kelas_count ?? 0 }} kelas</td>
                            <td class="is-muted is-nowrap">{{ number_format($j->siswa_count ?? 0, 0, ',', '.') }} siswa</td>
                            <td class="is-num">
                                @if ($j->aktif)
                                    <x-chip tone="green" label="Aktif" />
                                @else
                                    <x-chip tone="neutral" label="Nonaktif" />
                                @endif
                            </td>
                            <td class="is-num tbl__aksi">
                                <a class="btn-hifi btn-hifi--ghost btn-hifi--sm" href="{{ route('admin.jurusan.edit', $j) }}">Ubah</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-state">
                                @if (array_filter($filters ?? []))
                                    Tidak ada jurusan yang cocok dengan filter.
                                @else
                                    Belum ada jurusan. Tambahkan jurusan pertama lewat tombol "+ Tambah Jurusan".
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:foot>
            <span>
                Menampilkan {{ $jurusan->firstItem() ?? 0 }}–{{ $jurusan->lastItem() ?? 0 }}
                dari {{ $jurusan->total() }} jurusan
            </span>
            <x-pager :paginator="$jurusan" />
        </x-slot:foot>
    </x-card>
@endsection
