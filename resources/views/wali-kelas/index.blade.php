@extends('layouts.app')

@section('title', 'Wali Kelas')

@section('content')
    <x-page-head
        title="Kelas {{ $kelas->nama_kelas }}"
        :sub="'Tingkat ' . $kelas->tingkat . ($kelas->jurusan ? ' · ' . $kelas->jurusan : '') . ' · ' . $kpi['jumlah_siswa'] . ' siswa'">
        @if ($kelasWali->count() > 1)
            <form method="GET">
                <select class="select-hifi" name="kelas_id" onchange="this.form.submit()" style="width: 200px">
                    @foreach ($kelasWali as $k)
                        <option value="{{ $k->id }}" @selected($k->id === $kelas->id)>{{ $k->nama_kelas }}</option>
                    @endforeach
                </select>
            </form>
        @endif
        <a class="btn-hifi" href="{{ route('kelas.show', $kelas) }}">Detail Kelas →</a>
    </x-page-head>

    <div class="grid-row grid-row--4">
        <x-stat label="Jumlah Siswa" :value="$kpi['jumlah_siswa']" caption="terdaftar di kelas ini" />
        <x-stat label="Rata Kehadiran" :value="$kpi['rata_kehadiran'] . '%'" caption="seluruh presensi kelas" />
        <x-stat label="Kelengkapan Jurnal" :value="$kpi['kelengkapan'] . '%'" caption="terisi vs terjadwal" />
        <x-stat label="Total Alpa" :value="number_format($kpi['alpa'], 0, ',', '.')" caption="tanpa keterangan" />
    </div>

    <div class="grid-row grid-row--2">
        <x-card title="Daftar Siswa & Kehadiran" flush>
            <x-slot:actions>
                <span class="card-hifi__meta">{{ $kpi['jumlah_siswa'] }} siswa</span>
            </x-slot:actions>

            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th class="is-num">Kehadiran</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($siswa as $i => $s)
                            @php
                                $pct = $persentase[$s->id] ?? 0;
                                $tone = $pct >= 85 ? 'green' : ($pct >= 70 ? 'yellow' : 'red');
                            @endphp
                            <tr>
                                <td class="is-muted">{{ $i + 1 }}</td>
                                <td class="is-muted">{{ $s->nis ?? '—' }}</td>
                                <td>
                                    <span class="name-cell">
                                        <span class="avatar avatar--xs">{{ $s->inisial() }}</span>
                                        {{ $s->name }}
                                        @if ($s->is_ketua_kelas)
                                            <x-chip tone="yellow" label="Ketua" />
                                        @endif
                                    </span>
                                </td>
                                <td class="is-num"><x-chip :tone="$tone" :label="$pct . '%'" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty-state">Belum ada siswa di kelas ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <x-card title="Jurnal Terakhir" flush>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Mata Pelajaran</th>
                            <th>Materi</th>
                            <th>Guru</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($jurnalTerakhir as $j)
                            <tr>
                                <td class="is-muted">{{ $j->tanggal->format('d/m/Y') }}</td>
                                <td class="is-strong">{{ $j->jadwal?->mataPelajaran?->nama ?? '—' }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($j->materi, 40) }}</td>
                                <td class="is-muted">{{ $j->guru?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty-state">Belum ada jurnal untuk kelas ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
@endsection
