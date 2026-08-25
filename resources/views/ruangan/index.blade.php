@extends('layouts.app')

@section('title', 'Ruangan')

@section('content')
    @php
        $jenisTone = [
            'kelas' => 'green',
            'laboratorium' => 'solid',
            'bengkel' => 'khaki',
            'aula' => 'neutral',
            'perpustakaan' => 'neutral',
            'olahraga' => 'yellow',
            'lainnya' => 'neutral',
        ];
        $statusTone = ['aktif' => 'green', 'perbaikan' => 'yellow', 'nonaktif' => 'neutral'];
    @endphp

    <x-page-head
        title="Ruangan"
        :sub="$statistik['total'] . ' ruangan terdaftar · kapasitas total ' . number_format($statistik['kapasitas'], 0, ',', '.') . ' kursi'">
        @if (Auth::user()->isAdmin())
            <a class="btn-hifi" href="{{ route('ruangan.create') }}">+ Tambah Ruangan</a>
        @endif
    </x-page-head>

    <div class="grid-row grid-row--4">
        <x-stat label="Total Ruangan" :value="$statistik['total']" caption="seluruh gedung" />
        <x-stat label="Siap Dipakai" :value="$statistik['aktif']"
                :caption="$statistik['total'] - $statistik['aktif'] . ' tidak tersedia'" />
        <x-stat label="Sedang Perbaikan" :value="$statistik['perbaikan']"
                :caption="$statistik['perbaikan'] === 0 ? 'tidak ada' : 'jangan dijadwalkan'" />
        <x-stat label="Belum Terpakai" :value="$statistik['menganggur']->count()"
                :caption="$statistik['menganggur']->isEmpty() ? 'semua terpakai' : $statistik['menganggur']->take(3)->join(', ')" />
    </div>

    <form class="filter-bar" method="GET">
        <x-query-hidden />

        <label class="filter-bar__search">
            <x-ikon nama="search" />
            <input class="input-hifi" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                   placeholder="Cari kode, nama, atau gedung...">
        </label>

        <select class="select-hifi" name="jenis" style="width: 180px" data-searchable onchange="this.form.submit()">
            <option value="">Semua Jenis</option>
            @foreach (\App\Models\Ruangan::JENIS as $value => $label)
                <option value="{{ $value }}" @selected(($filters['jenis'] ?? null) === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <select class="select-hifi" name="gedung" style="width: 170px" data-searchable onchange="this.form.submit()">
            <option value="">Semua Gedung</option>
            @foreach ($gedungList as $gedung)
                <option value="{{ $gedung }}" @selected(($filters['gedung'] ?? null) === $gedung)>{{ $gedung }}</option>
            @endforeach
        </select>

        <select class="select-hifi" name="status" style="width: 160px" onchange="this.form.submit()">
            <option value="">Semua Status</option>
            @foreach (['aktif' => 'Aktif', 'perbaikan' => 'Perbaikan', 'nonaktif' => 'Nonaktif'] as $value => $label)
                <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <span class="filter-bar__note">
            Menampilkan {{ $ruangan->count() }} dari {{ $ruangan->total() }}
        </span>
    </form>

    <x-card title="Daftar Ruangan" flush>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th><x-th-sort kolom="kode" label="Kode" bawaan /></th>
                        <th><x-th-sort kolom="nama" label="Nama Ruangan" /></th>
                        <th><x-th-sort kolom="jenis" label="Jenis" /></th>
                        <th><x-th-sort kolom="gedung" label="Gedung / Lantai" /></th>
                        <th class="is-num"><x-th-sort kolom="kapasitas" label="Kapasitas" /></th>
                        <th class="is-num"><x-th-sort kolom="jadwal" label="Jadwal" /></th>
                        <th>Kelas Menetap</th>
                        <th class="is-num">Status</th>
                        <th class="is-num">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ruangan as $r)
                        <tr>
                            <td class="is-strong is-nowrap">{{ $r->kode }}</td>
                            <td>{{ $r->nama }}</td>
                            <td><x-chip :tone="$jenisTone[$r->jenis] ?? 'neutral'" :label="$r->jenisLabel()" /></td>
                            <td class="is-muted">
                                {{ $r->gedung ?? '—' }}@if ($r->lantai) · Lt. {{ $r->lantai }}@endif
                            </td>
                            <td class="is-num">{{ $r->kapasitas }}</td>
                            <td class="is-num">{{ $r->jadwals_count }}</td>
                            <td class="is-muted">{{ $r->kelas_count > 0 ? $r->kelas_count . ' kelas' : '—' }}</td>
                            <td class="is-num"><x-chip :tone="$statusTone[$r->status] ?? 'neutral'" :label="ucfirst($r->status)" /></td>
                            <td class="is-num tbl__aksi">
                                <a class="btn-hifi btn-hifi--ghost btn-hifi--sm" href="{{ route('ruangan.show', $r) }}">Lihat</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="empty-state">Tidak ada ruangan yang cocok dengan filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:foot>
            <span>
                Menampilkan {{ $ruangan->firstItem() ?? 0 }}–{{ $ruangan->lastItem() ?? 0 }}
                dari {{ number_format($ruangan->total(), 0, ',', '.') }} ruangan
            </span>
            <x-pager :paginator="$ruangan" />
        </x-slot:foot>
    </x-card>
@endsection
