@extends('layouts.app')

@section('title', 'Rekap Presensi')

@section('content')
    @php $pembagi = $total ?: 1; @endphp

    <x-page-head
        title="Rekap Presensi"
        :sub="number_format($total, 0, ',', '.') . ' kehadiran tercatat · rata-rata ' . round($rekap['hadir'] / $pembagi * 100) . '% hadir'">
        <x-periode-filter :periode="$periode" />
        <a class="btn-hifi" href="{{ request()->fullUrlWithQuery(['ekspor' => 'xlsx']) }}">Ekspor Excel</a>
    </x-page-head>

    {{-- Each status tile drills into the students behind it, honouring the
         active period and any kelas/guru filter. --}}
    <div class="grid-row grid-row--4">
        @foreach (['hadir' => 'Hadir', 'sakit' => 'Sakit', 'izin' => 'Izin', 'alpa' => 'Alpa'] as $kunci => $label)
            <x-stat :label="$label" :value="number_format($rekap[$kunci], 0, ',', '.')"
                    :caption="round($rekap[$kunci] / $pembagi * 100) . '% dari total'"
                    class="is-clickable" role="button" tabindex="0"
                    data-detail-tipe="presensi" data-detail-status="{{ $kunci }}" />
        @endforeach
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

    {{-- Per-class comparison: which classes attend best, over the chosen period.
         Each row drills into that class's attendance. --}}
    <x-card title="Kehadiran per Kelas" :meta="$periode->label()">
        <x-slot:actions>
            <x-legend :items="[
                'Hadir' => 'var(--green-200)',
                'Sakit' => 'var(--s-300)',
                'Izin' => 'var(--yellow-200)',
                'Alpa' => 'var(--red-100)',
            ]" />
        </x-slot:actions>

        @php $adaData = false; @endphp
        @foreach ($kelasList as $kelas)
            @php
                $r = $kehadiranPerKelas[$kelas->id] ?? collect();
                $totalKelas = $r instanceof \Illuminate\Support\Collection ? $r->sum() : array_sum((array) $r);
            @endphp
            @if ($totalKelas > 0)
                @php $adaData = true; @endphp
                <div class="breakdown breakdown--wide mb-2 is-clickable" role="button" tabindex="0"
                     data-detail-tipe="presensi" data-detail-kelas="{{ $kelas->id }}">
                    <span class="breakdown__label">{{ $kelas->nama_kelas }}</span>
                    <x-stack-bar :hadir="$r['hadir'] ?? 0" :sakit="$r['sakit'] ?? 0"
                                 :izin="$r['izin'] ?? 0" :alpa="$r['alpa'] ?? 0" />
                    <span class="breakdown__value">{{ round(($r['hadir'] ?? 0) / max(1, $totalKelas) * 100) }}%</span>
                </div>
            @endif
        @endforeach
        @unless ($adaData)
            <p class="empty-state">Belum ada presensi pada periode ini.</p>
        @endunless
    </x-card>

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
                        <th><x-th-sort kolom="tanggal" label="Tanggal" bawaan /></th>
                        <th><x-th-sort kolom="kelas" label="Kelas" /></th>
                        <th><x-th-sort kolom="mapel" label="Mata Pelajaran" /></th>
                        <th><x-th-sort kolom="guru" label="Guru" /></th>
                        <th><x-th-sort kolom="kehadiran_guru" label="Kehadiran Guru" /></th>
                        <th class="is-num"><x-th-sort kolom="siswa" label="Total" /></th>
                        <th class="is-num"><x-th-sort kolom="hadir" label="H" /></th>
                        <th class="is-num"><x-th-sort kolom="sakit" label="S" /></th>
                        <th class="is-num"><x-th-sort kolom="izin" label="I" /></th>
                        <th class="is-num"><x-th-sort kolom="alpa" label="A" /></th>
                        <th><x-th-sort kolom="persen" label="Persentase Kehadiran" /></th>
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
                            <td><x-guru-link :guru="$jurnal->guru" /></td>
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

    <x-detail-modal />
@endsection
