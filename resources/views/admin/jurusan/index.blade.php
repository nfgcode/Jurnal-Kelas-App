@extends('layouts.app')

@section('title', 'Jurusan')
@section('processing', 'Memuat daftar jurusan...')

@section('content')
    <x-page-head title="Jurusan" sub="Master jurusan sekolah yang digunakan kelas dan siswa.">
        <a class="btn-hifi" href="{{ route('admin.jurusan.create') }}">Tambah Jurusan</a>
    </x-page-head>

    <form class="filter-bar" method="GET" data-processing="Menerapkan filter jurusan...">
        <x-query-hidden />
        <label class="filter-bar__search">
            <span class="visually-hidden">Cari jurusan</span>
            <input class="input-hifi" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari kode, nama, atau bidang">
        </label>
        <label>
            <span class="visually-hidden">Bidang</span>
            <select class="select-hifi" name="bidang">
                <option value="">Semua Bidang</option>
                @foreach ($bidangList as $bidang)
                    <option value="{{ $bidang }}" @selected(($filters['bidang'] ?? null) === $bidang)>{{ $bidang }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span class="visually-hidden">Status jurusan</span>
            <select class="select-hifi" name="aktif">
                <option value="">Semua Status</option>
                <option value="1" @selected((string) ($filters['aktif'] ?? '') === '1')>Aktif</option>
                <option value="0" @selected((string) ($filters['aktif'] ?? '') === '0')>Nonaktif</option>
            </select>
        </label>
        <button class="btn-hifi btn-hifi--ghost" type="submit">Terapkan Filter</button>
    </form>

    <x-card title="Daftar Jurusan" flush>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th><x-th-sort kolom="kode" label="Kode" bawaan /></th>
                        <th><x-th-sort kolom="nama" label="Nama" /></th>
                        <th><x-th-sort kolom="bidang" label="Bidang" /></th>
                        <th class="is-num"><x-th-sort kolom="kelas" label="Kelas" /></th>
                        <th class="is-num"><x-th-sort kolom="siswa" label="Siswa" /></th>
                        <th><x-th-sort kolom="aktif" label="Status" /></th>
                        <th class="is-num">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($jurusan as $j)
                        <tr>
                            <td class="is-strong">{{ $j->kode }}</td>
                            <td>{{ $j->nama }}</td>
                            <td>{{ $j->bidang ?? 'Belum ditentukan' }}</td>
                            <td class="is-num">{{ $j->kelas_count }}</td>
                            <td class="is-num">{{ $j->siswa_count }}</td>
                            <td><x-chip :tone="$j->aktif ? 'green' : 'neutral'" :label="$j->aktif ? 'Aktif' : 'Nonaktif'" /></td>
                            <td class="is-num">
                                <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.jurusan.edit', $j) }}" aria-label="Ubah jurusan {{ $j->kode }}">Ubah</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty-state">Belum ada jurusan yang cocok dengan filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-slot:foot>
            <span>Menampilkan {{ $jurusan->count() }} dari {{ $jurusan->total() }} jurusan</span>
            <x-pager :paginator="$jurusan" :processing="true" />
        </x-slot:foot>
    </x-card>
@endsection
