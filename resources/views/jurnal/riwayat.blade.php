@extends('layouts.app')

@section('title', 'Jurnal')

@section('content')
    <x-page-head
        title="Riwayat Jurnal Kelas"
        :sub="collect([$kelas?->nama_kelas, number_format($statistik['total'], 0, ',', '.') . ' pertemuan', $periode->label()])->filter()->join(' · ')">
        <x-periode-filter :periode="$periode" />
        @if (Auth::user()->isKetuaKelas())
            <a class="btn-hifi" href="{{ route('jurnal.create') }}">Isi Jurnal Kelas</a>
        @endif
    </x-page-head>

    <div class="grid-row grid-row--4">
        <x-stat label="Total Pertemuan" :value="number_format($statistik['total'], 0, ',', '.')"
                :caption="$periode->label()" />
        <x-stat label="Jurnal Terisi" :value="number_format($statistik['total'], 0, ',', '.')"
                :caption="round($statistik['kelengkapan']) . '% kelengkapan'" />
        <x-stat label="Kelengkapan" :value="round($statistik['kelengkapan']) . '%'" caption="dari jadwal terjadwal" />
        <x-stat label="Tugas Diberikan" :value="number_format($statistik['tugas'], 0, ',', '.')" caption="pertemuan dengan tugas" />
    </div>

    <form class="filter-bar" method="GET">
        <x-query-hidden />

        <label class="filter-bar__search">
            <x-ikon nama="search" />
            <input class="input-hifi" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                   placeholder="Cari materi...">
        </label>

        <select class="select-hifi" name="mata_pelajaran_id" style="width: 170px" onchange="this.form.submit()">
            <option value="">Semua Mapel</option>
            @foreach ($mapelList as $mapel)
                <option value="{{ $mapel->id }}" @selected(($filters['mata_pelajaran_id'] ?? null) == $mapel->id)>{{ $mapel->nama }}</option>
            @endforeach
        </select>

        <span class="filter-bar__note">
            Menampilkan {{ $jurnals->count() }} dari {{ number_format($jurnals->total(), 0, ',', '.') }}
        </span>
    </form>

    <x-card :title="'Jurnal Kelas ' . ($kelas?->nama_kelas ?? '')" flush>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th><x-th-sort kolom="tanggal" label="Tanggal" bawaan /></th>
                        <th><x-th-sort kolom="jam" label="Jam Ke" /></th>
                        <th><x-th-sort kolom="mapel" label="Mata Pelajaran" /></th>
                        <th><x-th-sort kolom="guru" label="Guru" /></th>
                        <th><x-th-sort kolom="kehadiran_guru" label="Kehadiran Guru" /></th>
                        <th><x-th-sort kolom="materi" label="Materi" /></th>
                        <th><x-th-sort kolom="tugas" label="Tugas" /></th>
                        <th><x-th-sort kolom="presensi_saya" label="Presensi Saya" /></th>
                        <th class="is-num"><x-th-sort kolom="status" label="Jurnal" /></th>
                        <th class="is-num">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($jurnals as $jurnal)
                        @php
                            $chip = $jurnal->kehadiranGuruChip();
                            $status = $jurnal->statusPengisian();
                            $saya = $presensiSaya[$jurnal->id] ?? null;
                            $tonePresensi = match ($saya?->status) {
                                'hadir' => 'green',
                                'sakit' => 'khaki',
                                'izin' => 'yellow',
                                'alpa' => 'red',
                                default => 'neutral',
                            };
                        @endphp
                        <tr>
                            <td class="is-muted is-nowrap">{{ $jurnal->tanggal->format('d/m/Y') }}</td>
                            <td class="is-muted">{{ $jurnal->jadwal?->jpLabel() }}</td>
                            <td class="is-strong is-nowrap">{{ $jurnal->jadwal?->mataPelajaran?->nama }}</td>
                            <td class="is-muted is-nowrap">{{ $jurnal->guru?->name }}</td>
                            <td><x-chip :tone="$chip['tone']" :label="$chip['label']" /></td>
                            <td class="is-muted">{{ Str::limit($jurnal->materi, 22) }}</td>
                            <td class="is-muted">{{ $jurnal->tugas ? Str::limit($jurnal->tugas, 20) : '—' }}</td>
                            <td><x-chip :tone="$tonePresensi" :label="$saya ? ucfirst($saya->status) : '—'" /></td>
                            <td class="is-num"><x-chip :tone="$status['tone']" :label="$status['label']" /></td>
                            <td class="is-num">
                                <a class="btn-hifi btn-hifi--ghost btn-hifi--sm" href="{{ route('jurnal.show', $jurnal) }}">Lihat</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="empty-state">Belum ada jurnal kelas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:foot>
            <span>
                Menampilkan {{ $jurnals->firstItem() ?? 0 }}–{{ $jurnals->lastItem() ?? 0 }}
                dari {{ number_format($jurnals->total(), 0, ',', '.') }} pertemuan
            </span>
            <x-pager :paginator="$jurnals" />
        </x-slot:foot>
    </x-card>
@endsection
