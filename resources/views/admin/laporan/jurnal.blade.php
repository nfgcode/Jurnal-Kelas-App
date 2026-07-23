@extends('layouts.app')

@section('title', 'Rekap Jurnal')

@section('content')
    <x-page-head
        title="Rekap Jurnal Mengajar"
        :sub="number_format($statistik['terisi'], 0, ',', '.') . ' jurnal tercatat · ' . $statistik['kelengkapan'] . '% kelengkapan · ' . $periode->label()">
        <x-periode-filter :periode="$periode" />
        <a class="btn-hifi" href="{{ request()->fullUrlWithQuery(['ekspor' => 'excel']) }}">Ekspor Excel</a>
    </x-page-head>

    {{-- Each tile drills into the meetings behind it — e.g. "Belum Diisi" lists
         the scheduled meetings still missing a journal, and whose guru. --}}
    <div class="grid-row grid-row--4">
        <x-stat label="Jurnal Terisi" :value="number_format($statistik['terisi'], 0, ',', '.')" :caption="$periode->label()"
                class="is-clickable" role="button" tabindex="0" data-detail-tipe="terisi" />
        <x-stat label="Belum Diisi" :value="number_format($statistik['belum'], 0, ',', '.')" caption="perlu ditindaklanjuti"
                class="is-clickable" role="button" tabindex="0" data-detail-tipe="belum" />
        <x-stat label="Terlambat Isi" :value="number_format($statistik['telat'], 0, ',', '.')" caption=">24 jam setelah KBM"
                class="is-clickable" role="button" tabindex="0" data-detail-tipe="telat" />
        <x-stat label="Kelengkapan" :value="$statistik['kelengkapan'] . '%'" caption="target 90%"
                class="is-clickable" role="button" tabindex="0" data-detail-tipe="kelengkapan" />
    </div>

    <form class="filter-bar" method="GET">
        <label class="filter-bar__search">
            <i class="bi bi-search"></i>
            <input class="input-hifi" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                   placeholder="Cari kelas, guru atau materi...">
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

        <select class="select-hifi" name="status" style="width: 150px" onchange="this.form.submit()">
            <option value="">Semua Status</option>
            <option value="terisi" @selected(($filters['status'] ?? null) === 'terisi')>Terisi</option>
            <option value="telat" @selected(($filters['status'] ?? null) === 'telat')>Telat</option>
        </select>

        <span class="filter-bar__note">
            Menampilkan {{ $jurnals->count() }} dari {{ number_format($jurnals->total(), 0, ',', '.') }}
        </span>
    </form>

    {{-- Per-class comparison of journal completeness over the chosen period.
         Each row drills into that class's journals. --}}
    <x-card title="Kelengkapan Jurnal per Kelas" :meta="$periode->label()">
        @forelse ($kelasList as $kelas)
            @php $persen = (int) round($kelengkapan[$kelas->id] ?? 0); @endphp
            <div class="breakdown breakdown--wide mb-2 is-clickable" role="button" tabindex="0"
                 data-detail-tipe="kelas" data-detail-kelas="{{ $kelas->id }}">
                <span class="breakdown__label">{{ $kelas->nama_kelas }}</span>
                <span class="meter" style="width: 100%">
                    <span class="meter__fill" style="width: {{ $persen }}%"></span>
                </span>
                <span class="breakdown__value">{{ $persen }}%</span>
            </div>
        @empty
            <p class="empty-state">Belum ada kelas.</p>
        @endforelse
    </x-card>

    <x-card title="Rekap Jurnal Mengajar" flush>
        <x-slot:actions>
            <span class="card-hifi__meta">diurutkan: terbaru</span>
        </x-slot:actions>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Kelas</th>
                        <th>Mata Pelajaran</th>
                        <th>Guru</th>
                        <th>Materi</th>
                        <th>JP</th>
                        <th>Kehadiran</th>
                        <th>Kehadiran Guru</th>
                        <th class="is-num">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($jurnals as $jurnal)
                        @php
                            $guruChip = $jurnal->kehadiranGuruChip();
                            $statusChip = $jurnal->statusPengisian();
                            $jp = $jurnal->jadwal ? $jurnal->jadwal->jam_ke_selesai - $jurnal->jadwal->jam_ke_mulai + 1 : 0;
                        @endphp
                        <tr>
                            <td class="is-muted">{{ $jurnal->tanggal->format('d/m/Y') }}</td>
                            <td class="is-strong">{{ $jurnal->jadwal?->kelas?->nama_kelas }}</td>
                            <td>{{ $jurnal->jadwal?->mataPelajaran?->nama }}</td>
                            <td><x-guru-link :guru="$jurnal->guru" /></td>
                            <td class="is-muted">{{ Str::limit($jurnal->materi, 28) }}</td>
                            <td class="is-muted">{{ $jp }} JP</td>
                            <td>
                                <a class="meter-cell text-reset text-decoration-none" href="{{ route('presensi.show', $jurnal->id) }}">
                                    <x-meter :percent="$jurnal->total_siswa ? $jurnal->hadir_count / $jurnal->total_siswa * 100 : 0" />
                                    <span class="is-muted">{{ $jurnal->hadir_count }}/{{ $jurnal->total_siswa }}</span>
                                </a>
                            </td>
                            <td><x-chip :tone="$guruChip['tone']" :label="$guruChip['label']" /></td>
                            <td class="is-num"><x-chip :tone="$statusChip['tone']" :label="$statusChip['label']" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="empty-state">Tidak ada jurnal yang cocok dengan filter.</td></tr>
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

    <x-detail-modal />
@endsection
