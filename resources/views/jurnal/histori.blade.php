@extends('layouts.app')

@section('title', 'Jurnal')

@section('content')
    @php $kehadiran = $statistik['kehadiran']; @endphp

    <x-page-head
        title="Histori Jurnal"
        :sub="number_format($statistik['periode'], 0, ',', '.') . ' jurnal · ' . $statistik['kelas'] . ' kelas · ' . $periode->label()">
        <x-periode-filter :periode="$periode" />
        <a class="btn-hifi" href="{{ route('jurnal.create') }}">Isi Jurnal</a>
    </x-page-head>

    <div class="grid-row grid-row--4">
        <x-stat label="Jurnal Periode Ini" :value="number_format($statistik['periode'], 0, ',', '.')"
                :caption="$periode->label()" />
        <x-stat label="Total Keseluruhan" :value="number_format($statistik['total'], 0, ',', '.')"
                caption="seluruh jurnal saya" />
        <x-stat label="Hadir Mengajar" :value="number_format($kehadiran['hadir'], 0, ',', '.')"
                :caption="'dari ' . number_format($kehadiran['total'], 0, ',', '.') . ' pertemuan'" />
        <x-stat label="Tanpa Tugas" :value="$kehadiran['tanpa_tugas']" caption="perlu ditindaklanjuti" />
    </div>

    <form class="filter-bar" method="GET">
        <x-query-hidden />

        <label class="filter-bar__search">
            <x-ikon nama="search" />
            <input class="input-hifi" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                   placeholder="Cari materi...">
        </label>

        <select class="select-hifi" name="kelas_id" style="width: 150px" onchange="this.form.submit()">
            <option value="">Semua Kelas</option>
            @foreach ($kelasList as $kelas)
                <option value="{{ $kelas->id }}" @selected(($filters['kelas_id'] ?? null) == $kelas->id)>{{ $kelas->nama_kelas }}</option>
            @endforeach
        </select>

        <select class="select-hifi" name="mata_pelajaran_id" style="width: 170px" data-searchable onchange="this.form.submit()">
            <option value="">Semua Mapel</option>
            @foreach ($mapelList as $mapel)
                <option value="{{ $mapel->id }}" @selected(($filters['mata_pelajaran_id'] ?? null) == $mapel->id)>{{ $mapel->nama }}</option>
            @endforeach
        </select>

        <span class="filter-bar__note">
            Menampilkan {{ $jurnals->count() }} dari {{ number_format($jurnals->total(), 0, ',', '.') }}
        </span>
    </form>

    <x-card title="Riwayat Jurnal Mengajar" flush>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th><x-th-sort kolom="tanggal" label="Tanggal" bawaan /></th>
                        <th><x-th-sort kolom="jam" label="Jam Ke" /></th>
                        <th><x-th-sort kolom="kelas" label="Kelas" /></th>
                        <th><x-th-sort kolom="mapel" label="Mata Pelajaran" /></th>
                        <th><x-th-sort kolom="materi" label="Materi" /></th>
                        <th><x-th-sort kolom="tugas" label="Tugas" /></th>
                        <th><x-th-sort kolom="persen" label="Kehadiran" /></th>
                        <th><x-th-sort kolom="kehadiran_guru" label="Kehadiran Saya" /></th>
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
                            <td class="is-strong is-nowrap">{{ $jurnal->jadwal?->kelas?->nama_kelas }}</td>
                            <td>{{ $jurnal->jadwal?->mataPelajaran?->nama }}</td>
                            <td class="is-muted">{{ Str::limit($jurnal->materi, 24) }}</td>
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
