@extends('layouts.app')

@section('title', 'Rekap Presensi')

@section('content')
    @php $pembagi = $total ?: 1; @endphp

    <x-page-head
        title="Rekap Presensi"
        :sub="number_format($total, 0, ',', '.') . ' kehadiran tercatat · rata-rata ' . round($rekap['hadir'] / $pembagi * 100) . '% hadir'">
        <span class="select-hifi" style="width: 170px">{{ now()->translatedFormat('F Y') }}</span>
        <a class="btn-hifi" href="{{ request()->fullUrlWithQuery(['ekspor' => 'excel']) }}">Ekspor Excel</a>
    </x-page-head>

    <div class="grid-row" style="grid-template-columns: repeat(4, minmax(0, 1fr))">
        <x-stat label="Hadir" :value="number_format($rekap['hadir'], 0, ',', '.')"
                :caption="round($rekap['hadir'] / $pembagi * 100) . '% dari total'" />
        <x-stat label="Sakit" :value="number_format($rekap['sakit'], 0, ',', '.')"
                :caption="round($rekap['sakit'] / $pembagi * 100) . '% dari total'" />
        <x-stat label="Izin" :value="number_format($rekap['izin'], 0, ',', '.')"
                :caption="round($rekap['izin'] / $pembagi * 100) . '% dari total'" />
        <x-stat label="Alpa" :value="number_format($rekap['alpa'], 0, ',', '.')"
                :caption="round($rekap['alpa'] / $pembagi * 100) . '% dari total'" />
    </div>

    <form class="filter-bar" method="GET">
        <label class="filter-bar__search">
            <i class="bi bi-search"></i>
            <input class="input-hifi" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                   placeholder="Cari kelas, guru atau mapel...">
        </label>

        <select class="select-hifi" name="kelas_id" style="width: 150px" onchange="this.form.submit()">
            <option value="">Semua Kelas</option>
            @foreach ($kelasList as $kelas)
                <option value="{{ $kelas->id }}" @selected(($filters['kelas_id'] ?? null) == $kelas->id)>{{ $kelas->nama_kelas }}</option>
            @endforeach
        </select>

        <select class="select-hifi" name="guru_id" style="width: 160px" onchange="this.form.submit()">
            <option value="">Semua Guru</option>
            @foreach ($guruList as $guru)
                <option value="{{ $guru->id }}" @selected(($filters['guru_id'] ?? null) == $guru->id)>{{ $guru->name }}</option>
            @endforeach
        </select>

        <span class="filter-bar__note">
            Menampilkan {{ $pertemuan->count() }} dari {{ number_format($pertemuan->total(), 0, ',', '.') }}
        </span>
    </form>

    <x-card title="Rekap Presensi per Pertemuan" flush>
        <x-slot:actions>
            <x-legend :items="[
                'Hadir' => 'var(--green-200)',
                'Sakit' => 'var(--s-300)',
                'Izin' => 'var(--yellow-200)',
                'Alpa' => 'var(--red-100)',
            ]" />
        </x-slot:actions>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Kelas</th>
                        <th>Mata Pelajaran</th>
                        <th>Guru</th>
                        <th>Kehadiran Guru</th>
                        <th class="is-num">Total</th>
                        <th class="is-num">H</th>
                        <th class="is-num">S</th>
                        <th class="is-num">I</th>
                        <th class="is-num">A</th>
                        <th>Persentase Kehadiran</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pertemuan as $jurnal)
                        @php
                            $guruChip = $jurnal->kehadiranGuruChip();
                            $persen = $jurnal->total_siswa ? round($jurnal->hadir_count / $jurnal->total_siswa * 100) : 0;
                        @endphp
                        <tr>
                            <td class="is-muted">{{ $jurnal->tanggal->format('d/m/Y') }}</td>
                            <td class="is-strong">{{ $jurnal->jadwal?->kelas?->nama_kelas }}</td>
                            <td>{{ $jurnal->jadwal?->mataPelajaran?->nama }}</td>
                            <td>
                                <span class="name-cell">
                                    <span class="avatar avatar--xs">{{ $jurnal->guru?->inisial() }}</span>
                                    {{ $jurnal->guru?->name }}
                                </span>
                            </td>
                            <td><x-chip :tone="$guruChip['tone']" :label="$guruChip['label']" /></td>
                            <td class="is-num">{{ $jurnal->total_siswa }}</td>
                            <td class="is-num">{{ $jurnal->hadir_count }}</td>
                            <td class="is-num">{{ $jurnal->sakit_count }}</td>
                            <td class="is-num">{{ $jurnal->izin_count }}</td>
                            <td class="is-num">{{ $jurnal->alpa_count }}</td>
                            <td>
                                <span class="meter-cell">
                                    <x-stack-bar :hadir="$jurnal->hadir_count" :sakit="$jurnal->sakit_count"
                                                 :izin="$jurnal->izin_count" :alpa="$jurnal->alpa_count" />
                                    <span class="is-strong">{{ $persen }}%</span>
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="empty-state">Tidak ada pertemuan yang cocok dengan filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:foot>
            <span>
                Menampilkan {{ $pertemuan->firstItem() ?? 0 }}–{{ $pertemuan->lastItem() ?? 0 }}
                dari {{ number_format($pertemuan->total(), 0, ',', '.') }} pertemuan
            </span>
            <x-pager :paginator="$pertemuan" />
        </x-slot:foot>
    </x-card>
@endsection
