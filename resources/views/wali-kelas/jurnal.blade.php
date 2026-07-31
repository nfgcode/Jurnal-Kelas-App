@extends('layouts.app')

@section('title', 'Jurnal Kelas')

@section('content')
    @php
        $kehadiran = $statistik['kehadiran'];
        $tidakHadir = $kehadiran['ada_tugas'] + $kehadiran['tanpa_tugas'];
    @endphp

    <x-page-head
        title="Jurnal Kelas {{ $kelas->nama_kelas }}"
        :sub="number_format($statistik['total'], 0, ',', '.') . ' jurnal tercatat · seluruh mata pelajaran kelas ini'">
        <x-kelas-switch :kelas-wali="$kelasWali" :kelas="$kelas" />
    </x-page-head>

    <div class="grid-row grid-row--4">
        <x-stat label="Total Jurnal" :value="number_format($statistik['total'], 0, ',', '.')" caption="semester berjalan" />
        <x-stat label="Bulan Ini" :value="number_format($statistik['bulanIni'], 0, ',', '.')"
                :caption="now()->translatedFormat('F Y')" />
        <x-stat label="Kelengkapan" :value="$statistik['kelengkapan'] . '%'" caption="terisi vs terjadwal" />
        <x-stat label="Guru Tidak Hadir" :value="number_format($tidakHadir, 0, ',', '.')"
                :caption="$kehadiran['tanpa_tugas'] . ' tanpa tugas'" />
    </div>

    <form class="filter-bar" method="GET">
        <x-query-hidden />

        <input type="hidden" name="kelas_id" value="{{ $kelas->id }}">

        <label class="filter-bar__search">
            <i class="bi bi-search"></i>
            <input class="input-hifi" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                   placeholder="Cari materi, tugas atau guru...">
        </label>

        <select class="select-hifi" name="mata_pelajaran_id" style="width: 190px" onchange="this.form.submit()">
            <option value="">Semua Mapel</option>
            @foreach ($mapelList as $mapel)
                <option value="{{ $mapel->id }}" @selected(($filters['mata_pelajaran_id'] ?? null) == $mapel->id)>{{ $mapel->nama }}</option>
            @endforeach
        </select>

        <span class="filter-bar__note">
            Menampilkan {{ $jurnals->count() }} dari {{ number_format($jurnals->total(), 0, ',', '.') }}
        </span>
    </form>

    <x-card title="Histori Jurnal Kelas" flush>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th><x-th-sort kolom="tanggal" label="Tanggal" bawaan /></th>
                        <th><x-th-sort kolom="jam" label="Jam Ke" /></th>
                        <th><x-th-sort kolom="mapel" label="Mata Pelajaran" /></th>
                        <th><x-th-sort kolom="guru" label="Guru" /></th>
                        <th><x-th-sort kolom="materi" label="Materi" /></th>
                        <th><x-th-sort kolom="tugas" label="Tugas" /></th>
                        <th><x-th-sort kolom="persen" label="Kehadiran Siswa" /></th>
                        <th><x-th-sort kolom="kehadiran_guru" label="Kehadiran Guru" /></th>
                        <th class="is-num"><x-th-sort kolom="status" label="Status" /></th>
                        <th class="is-num">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($jurnals as $jurnal)
                        @php
                            $chip = $jurnal->kehadiranGuruChip();
                            $status = $jurnal->statusPengisian();
                        @endphp
                        <tr>
                            <td class="is-muted is-nowrap">{{ $jurnal->tanggal->format('d/m/Y') }}</td>
                            <td class="is-muted">{{ $jurnal->jadwal?->jpLabel() }}</td>
                            <td class="is-strong">{{ $jurnal->jadwal?->mataPelajaran?->nama }}</td>
                            <td class="is-nowrap is-muted">{{ $jurnal->guru?->name ?? '—' }}</td>
                            <td class="is-muted">{{ Str::limit($jurnal->materi, 28) }}</td>
                            <td class="is-muted">{{ $jurnal->tugas ? Str::limit($jurnal->tugas, 22) : '—' }}</td>
                            <td>
                                <span class="meter-cell">
                                    <x-meter :percent="$jurnal->total_siswa ? $jurnal->hadir_count / $jurnal->total_siswa * 100 : 0" />
                                    <span class="is-muted">{{ $jurnal->hadir_count }}/{{ $jurnal->total_siswa }}</span>
                                </span>
                            </td>
                            <td><x-chip :tone="$chip['tone']" :label="$chip['label']" /></td>
                            <td class="is-num"><x-chip :tone="$status['tone']" :label="$status['label']" /></td>
                            <td class="is-num">
                                <a class="btn-hifi btn-hifi--ghost btn-hifi--sm" href="{{ route('jurnal.show', $jurnal) }}">Lihat</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="empty-state">Belum ada jurnal yang cocok dengan filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:foot>
            <span>
                Menampilkan {{ $jurnals->firstItem() ?? 0 }}–{{ $jurnals->lastItem() ?? 0 }}
                dari {{ number_format($jurnals->total(), 0, ',', '.') }} jurnal
            </span>
            <x-pager :paginator="$jurnals" />
        </x-slot:foot>
    </x-card>
@endsection
