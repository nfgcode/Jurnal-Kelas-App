@extends('layouts.app')

@section('title', 'Rekap Jurnal')

@section('content')
    <x-page-head
        title="Rekap Jurnal Mengajar"
        :sub="collect([
            number_format($statistik['terisi'], 0, ',', '.') . ' jurnal ditulis guru',
            $statistik['otomatis'] > 0 ? number_format($statistik['otomatis'], 0, ',', '.') . ' diisi otomatis' : null,
            $statistik['kelengkapan'] . '% kelengkapan',
            $statistik['diedit_lewat_hari'] > 0 ? number_format($statistik['diedit_lewat_hari'], 0, ',', '.') . ' diedit setelah hari-H' : null,
            $periode->label(),
        ])->filter()->join(' · ')">
        <x-periode-filter :periode="$periode" />
        <a class="btn-hifi" href="{{ request()->fullUrlWithQuery(['ekspor' => 'xlsx']) }}">Ekspor Excel</a>
    </x-page-head>

    {{-- Each tile drills into the meetings behind it — e.g. "Belum Diisi" lists
         the scheduled meetings still missing a journal, and whose guru. --}}
    <div class="grid-row grid-row--5">
        <x-stat label="Diisi Guru" :value="number_format($statistik['terisi'], 0, ',', '.')" caption="ditulis sendiri"
                class="is-clickable" role="button" tabindex="0" data-detail-tipe="terisi" />
        <x-stat label="Diisi Otomatis" :value="number_format($statistik['otomatis'], 0, ',', '.')" caption="guru belum mengisi"
                class="is-clickable" role="button" tabindex="0" data-detail-tipe="otomatis" />
        <x-stat label="Belum Diisi" :value="number_format($statistik['belum'], 0, ',', '.')" caption="perlu ditindaklanjuti"
                class="is-clickable" role="button" tabindex="0" data-detail-tipe="belum" />
        <x-stat label="Terlambat Isi" :value="number_format($statistik['telat'], 0, ',', '.')" caption=">24 jam setelah KBM"
                class="is-clickable" role="button" tabindex="0" data-detail-tipe="telat" />
        <x-stat label="Kelengkapan" :value="$statistik['kelengkapan'] . '%'" caption="tulisan guru · target 90%"
                class="is-clickable" role="button" tabindex="0" data-detail-tipe="kelengkapan" />
    </div>

    <form class="filter-bar" method="GET">
        <x-query-hidden />

        <label class="filter-bar__search">
            <x-ikon nama="search" />
            <input class="input-hifi" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                   placeholder="Cari kelas, guru atau materi...">
        </label>

        <select class="select-hifi" name="kelas_id" style="width: 150px" data-searchable onchange="this.form.submit()">
            <option value="">Semua Kelas</option>
            @foreach ($kelasList as $kelas)
                <option value="{{ $kelas->id }}" @selected(($filters['kelas_id'] ?? null) == $kelas->id)>{{ $kelas->nama_kelas }}</option>
            @endforeach
        </select>

        <x-filter-tingkat-jurusan :filters="$filters" :kelas-list="$kelasList" />

        <select class="select-hifi" name="guru_id" style="width: 160px" data-searchable onchange="this.form.submit()">
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

        <select class="select-hifi" name="edit_lewat_hari" style="width: 200px" onchange="this.form.submit()">
            <option value="">Semua Riwayat Edit</option>
            <option value="1" @selected(($filters['edit_lewat_hari'] ?? null) === '1')>Diedit setelah hari-H</option>
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
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th><x-th-sort kolom="tanggal" label="Tanggal" bawaan /></th>
                        <th><x-th-sort kolom="kelas" label="Kelas" /></th>
                        <th><x-th-sort kolom="mapel" label="Mata Pelajaran" /></th>
                        <th><x-th-sort kolom="guru" label="Guru" /></th>
                        <th><x-th-sort kolom="materi" label="Materi" /></th>
                        <th><x-th-sort kolom="jam" label="JP" /></th>
                        <th><x-th-sort kolom="persen" label="Kehadiran" /></th>
                        <th><x-th-sort kolom="kehadiran_guru" label="Kehadiran Guru" /></th>
                        <th class="is-num"><x-th-sort kolom="status" label="Status" /></th>
                        <th class="is-num">Aksi</th>
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
                                <span class="meter-cell">
                                    <x-meter :percent="$jurnal->total_siswa ? $jurnal->hadir_count / $jurnal->total_siswa * 100 : 0" />
                                    <span class="is-muted">{{ $jurnal->hadir_count }}/{{ $jurnal->total_siswa }}</span>
                                </span>
                            </td>
                            <td><x-chip :tone="$guruChip['tone']" :label="$guruChip['label']" /></td>
                            <td class="is-num"><x-chip :tone="$statusChip['tone']" :label="$statusChip['label']" /> <x-jurnal-edit-badge :jurnal="$jurnal" /></td>
                            <td class="is-num tbl__aksi">
                                <a class="btn-hifi btn-hifi--ghost btn-hifi--sm" href="{{ route('jurnal.show', $jurnal) }}">Lihat</a>
                                {{-- The attendance link the meter bar used to hide. --}}
                                <a class="btn-hifi btn-hifi--ghost btn-hifi--sm" href="{{ route('presensi.show', $jurnal) }}">Presensi</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="empty-state">Tidak ada jurnal yang cocok dengan filter.</td></tr>
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
