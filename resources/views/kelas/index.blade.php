@extends('layouts.app')

@section('title', 'Kelas')

@section('content')
    <x-page-head
        title="Data Kelas"
        :sub="$statistik['totalKelas'] . ' rombongan belajar aktif · Semester Gasal ' . now()->year . '/' . (now()->year + 1)">
        <form method="GET">
            <select class="select-hifi" name="tingkat" style="width: 170px" onchange="this.form.submit()">
                <option value="">Semua Tingkat</option>
                @foreach (['X', 'XI', 'XII'] as $tingkat)
                    <option value="{{ $tingkat }}" @selected(($filters['tingkat'] ?? null) === $tingkat)>Tingkat {{ $tingkat }}</option>
                @endforeach
            </select>
        </form>
        @if (Auth::user()->isAdmin())
            <a class="btn-hifi" href="{{ route('kelas.create') }}">+ Tambah Kelas</a>
        @endif
    </x-page-head>

    <div class="grid-row grid-row--4">
        <x-stat label="Total Kelas" :value="$statistik['totalKelas']" caption="rombongan belajar aktif" />
        <x-stat label="Rata Siswa / Kelas" :value="$statistik['rataSiswa']" caption="kapasitas ideal 36" />
        <x-stat label="Wali Kelas Terisi" :value="$statistik['waliTerisi']"
                :caption="$statistik['tanpaWali']->isEmpty() ? 'seluruh kelas terisi' : $statistik['tanpaWali']->take(2)->join(', ') . ' kosong'" />
        <x-stat label="Kelengkapan Jurnal" :value="$statistik['rataKelengkapan'] . '%'" caption="rata-rata seluruh kelas" />
    </div>

    <form class="filter-bar" method="GET">
        <x-query-hidden />

        <label class="filter-bar__search">
            <x-ikon nama="search" />
            <input class="input-hifi" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                   placeholder="Cari kelas atau wali kelas...">
        </label>

        <select class="select-hifi" name="tingkat" style="width: 160px" onchange="this.form.submit()">
            <option value="">Semua Tingkat</option>
            @foreach (['X', 'XI', 'XII'] as $tingkat)
                <option value="{{ $tingkat }}" @selected(($filters['tingkat'] ?? null) === $tingkat)>Tingkat {{ $tingkat }}</option>
            @endforeach
        </select>

        <select class="select-hifi" name="jurusan" style="width: 160px" data-searchable onchange="this.form.submit()">
            <option value="">Semua Jurusan</option>
            @foreach ($jurusanList as $jurusan)
                <option value="{{ $jurusan }}" @selected(($filters['jurusan'] ?? null) === $jurusan)>{{ $jurusan }}</option>
            @endforeach
        </select>

        <span class="filter-bar__note">
            Menampilkan {{ $kelas->count() }} dari {{ $kelas->total() }}
        </span>
    </form>

    <x-card title="Daftar Kelas" flush>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th><x-th-sort kolom="nama" label="Kelas" /></th>
                        <th><x-th-sort kolom="wali" label="Wali Kelas" /></th>
                        <th><x-th-sort kolom="ruang" label="Ruang" /></th>
                        <th class="is-num"><x-th-sort kolom="siswa" label="Siswa" /></th>
                        <th><x-th-sort kolom="jadwal" label="Mata Pelajaran" /></th>
                        <th>Kelengkapan Jurnal</th>
                        <th class="is-num">Status</th>
                        <th class="is-num">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($kelas as $item)
                        @php $persen = $kelengkapan[$item->id] ?? 0; @endphp
                        <tr>
                            <td class="is-strong">{{ $item->nama_kelas }}</td>
                            <td>
                                @if ($item->waliKelas)
                                    <x-guru-link :guru="$item->waliKelas" />
                                @else
                                    <span class="is-muted">Belum ditetapkan</span>
                                @endif
                            </td>
                            <td class="is-muted">{{ $item->ruang ?? '—' }}</td>
                            <td class="is-num">{{ $item->siswa_count }}</td>
                            <td class="is-muted">{{ $item->jadwals_count }} jadwal</td>
                            <td>
                                <span class="meter-cell">
                                    <x-meter :percent="$persen" />
                                    <span class="is-strong">{{ round($persen) }}%</span>
                                </span>
                            </td>
                            <td class="is-num">
                                @if ($persen >= 85)
                                    <x-chip tone="green" label="Normal" />
                                @elseif ($persen >= 70)
                                    <x-chip tone="yellow" label="Perhatian" />
                                @else
                                    <x-chip tone="red" label="Kritis" />
                                @endif
                            </td>
                            <td class="is-num">
                                <a class="btn-hifi btn-hifi--ghost btn-hifi--sm" href="{{ route('kelas.show', $item) }}">Lihat</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="empty-state">Tidak ada kelas yang cocok dengan filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:foot>
            <span>Menampilkan {{ $kelas->firstItem() ?? 0 }}–{{ $kelas->lastItem() ?? 0 }} dari {{ $kelas->total() }} kelas</span>
            <x-pager :paginator="$kelas" />
        </x-slot:foot>
    </x-card>
@endsection
