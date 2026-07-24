@extends('layouts.app')

@section('title', 'Jurnal')

@section('content')
    @php $kehadiran = $statistik['kehadiran']; @endphp

    <x-page-head
        title="Histori Jurnal"
        :sub="number_format($statistik['total'], 0, ',', '.') . ' jurnal · ' . $statistik['kelas'] . ' kelas · Semester Gasal ' . now()->year . '/' . (now()->year + 1)">
        <span class="select-hifi" style="width: 170px">{{ now()->translatedFormat('F Y') }}</span>
        <a class="btn-hifi" href="{{ route('jurnal.create') }}">Isi Jurnal</a>
    </x-page-head>

    <div class="grid-row grid-row--4">
        <x-stat label="Jurnal Saya" :value="number_format($statistik['total'], 0, ',', '.')" caption="semester berjalan" />
        <x-stat label="Bulan Ini" :value="number_format($statistik['bulanIni'], 0, ',', '.')"
                :caption="now()->translatedFormat('F Y')" />
        <x-stat label="Hadir Mengajar" :value="number_format($kehadiran['hadir'], 0, ',', '.')"
                :caption="'dari ' . number_format($kehadiran['total'], 0, ',', '.') . ' pertemuan'" />
        <x-stat label="Tanpa Tugas" :value="$kehadiran['tanpa_tugas']" caption="perlu ditindaklanjuti" />
    </div>

    <form class="filter-bar" method="GET">
        <label class="filter-bar__search">
            <i class="bi bi-search"></i>
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
        <x-slot:actions>
            <span class="card-hifi__meta">diurutkan: terbaru</span>
        </x-slot:actions>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Jam Ke</th>
                        <th>Kelas</th>
                        <th>Mata Pelajaran</th>
                        <th>Materi</th>
                        <th>Tugas</th>
                        <th>Kehadiran</th>
                        <th>Kehadiran Saya</th>
                        <th class="is-num">Status</th>
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
                            <td class="is-strong is-nowrap">
                                <a class="text-reset text-decoration-none" href="{{ route('jurnal.show', $jurnal) }}">
                                    {{ $jurnal->jadwal?->kelas?->nama_kelas }}
                                </a>
                            </td>
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
                        </tr>
                    @empty
                        <tr><td colspan="9" class="empty-state">Belum ada jurnal yang cocok dengan filter.</td></tr>
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
