@extends('layouts.app')

@section('title', 'Data Alumni')

@section('content')
    <x-page-head
        title="Data Alumni"
        sub="Kelola rekam jejak kelulusan siswa, status siklus hidup, dan peninjauan kebijakan retensi data.">
        <x-chip tone="khaki" :label="number_format($statistik['total'], 0, ',', '.') . ' Alumni Terdaftar'" />
    </x-page-head>

    {{-- Filter Bar --}}
    <form class="filter-bar mb-4" method="GET">
        <x-query-hidden />

        <label class="filter-bar__search">
            <x-ikon nama="search" />
            <input class="input-hifi" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                   placeholder="Cari nama atau NIS alumni...">
        </label>

        <select class="select-hifi" name="tahun_lulus" style="width: 180px" onchange="this.form.submit()">
            <option value="">Semua Tahun Lulus</option>
            @foreach ($tahunLulusList as $th)
                <option value="{{ $th }}" @selected(($filters['tahun_lulus'] ?? null) === $th)>
                    Tahun Lulus {{ $th }}
                </option>
            @endforeach
        </select>

        <span class="filter-bar__note">
            Menampilkan {{ $alumni->count() }} dari {{ number_format($alumni->total(), 0, ',', '.') }} alumni
        </span>
    </form>

    {{-- Tabel Alumni --}}
    <x-card title="Daftar Alumni & Evaluasi Retensi" flush>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>NIS</th>
                        <th>Nama Siswa</th>
                        <th>Rombel Terakhir</th>
                        <th>Tahun Lulus</th>
                        <th>Tanggal Lulus</th>
                        <th>Siklus Hidup &amp; Retensi</th>
                        <th>Akun Login</th>
                        <th class="is-num">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($alumni as $item)
                        @php
                            $eval = $item->evaluasi;
                            $siswa = $item->siswa;
                            $akun = $siswa?->akun;
                        @endphp
                        <tr>
                            <td class="is-strong is-nowrap">{{ $item->siswa_nis }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="avatar avatar--xs">{{ $siswa?->inisial() ?? '?' }}</span>
                                    <span class="fw-semibold">{{ $siswa?->nama ?? '—' }}</span>
                                </div>
                            </td>
                            <td class="is-muted">{{ $item->kelasTerakhir?->nama_kelas ?? '—' }}</td>
                            <td class="is-strong">{{ $item->tahun_lulus }}</td>
                            <td class="is-muted is-nowrap">{{ $item->tanggal_lulus ? $item->tanggal_lulus->format('d/m/Y') : '—' }}</td>
                            <td>
                                <x-chip :tone="$eval['tone_siklus_hidup']" :label="$eval['label_siklus_hidup']" />
                                <span class="d-block small text-muted mt-1">Inaktif: {{ $eval['usia_inaktif_teks'] }}</span>
                            </td>
                            <td>
                                @if ($akun)
                                    <x-chip :tone="$akun->status === 'aktif' ? 'green' : 'neutral'" :label="ucfirst($akun->status)" />
                                @else
                                    <span class="text-muted small">Belum ada</span>
                                @endif
                            </td>
                            <td class="is-num tbl__aksi">
                                <a class="btn-hifi btn-hifi--ghost btn-hifi--sm" href="{{ route('admin.alumni.show', $item) }}">
                                    Detail &amp; Tinjauan
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-state">
                                Belum ada data alumni yang terdaftar. Data alumni akan terisi otomatis saat proses kelulusan tingkat XII dijalankan pada menu Kenaikan &amp; Kelulusan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($alumni->hasPages())
            <x-slot:foot>
                <span>
                    Menampilkan {{ $alumni->firstItem() ?? 0 }}–{{ $alumni->lastItem() ?? 0 }}
                    dari {{ number_format($alumni->total(), 0, ',', '.') }} alumni
                </span>
                <x-pager :paginator="$alumni" />
            </x-slot:foot>
        @endif
    </x-card>
@endsection
