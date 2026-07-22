@extends('layouts.app')

@section('title', 'Jurnal')

@section('content')
    <x-page-head
        title="Riwayat Jurnal Kelas"
        :sub="collect([$kelas?->nama_kelas, number_format($statistik['total'], 0, ',', '.') . ' pertemuan', 'Semester Gasal ' . now()->year . '/' . (now()->year + 1)])->filter()->join(' · ')">
        <span class="select-hifi" style="width: 170px">{{ now()->translatedFormat('F Y') }}</span>
        @if (Auth::user()->isKetuaKelas())
            <a class="btn-hifi" href="{{ route('jurnal.create') }}">Isi Jurnal Kelas</a>
        @endif
    </x-page-head>

    <div class="grid-row" style="grid-template-columns: repeat(4, minmax(0, 1fr))">
        <x-stat label="Total Pertemuan" :value="number_format($statistik['total'], 0, ',', '.')" caption="semester berjalan" />
        <x-stat label="Jurnal Terisi" :value="number_format($statistik['total'], 0, ',', '.')"
                :caption="round($statistik['kelengkapan']) . '% kelengkapan'" />
        <x-stat label="Kelengkapan" :value="round($statistik['kelengkapan']) . '%'" caption="dari jadwal terjadwal" />
        <x-stat label="Tugas Diberikan" :value="number_format($statistik['tugas'], 0, ',', '.')" caption="pertemuan dengan tugas" />
    </div>

    <form class="filter-bar" method="GET">
        <label class="filter-bar__search">
            <i class="bi bi-search"></i>
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
        <x-slot:actions>
            <span class="card-hifi__meta">diurutkan: terbaru</span>
        </x-slot:actions>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Jam Ke</th>
                        <th>Mata Pelajaran</th>
                        <th>Guru</th>
                        <th>Kehadiran Guru</th>
                        <th>Materi</th>
                        <th>Tugas</th>
                        <th>Presensi Saya</th>
                        <th class="is-num">Jurnal</th>
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
                            <td class="is-strong is-nowrap">
                                <a class="text-reset text-decoration-none" href="{{ route('jurnal.show', $jurnal) }}">
                                    {{ $jurnal->jadwal?->mataPelajaran?->nama }}
                                </a>
                            </td>
                            <td class="is-muted is-nowrap">{{ $jurnal->guru?->name }}</td>
                            <td><x-chip :tone="$chip['tone']" :label="$chip['label']" /></td>
                            <td class="is-muted">{{ Str::limit($jurnal->materi, 22) }}</td>
                            <td class="is-muted">{{ $jurnal->tugas ? Str::limit($jurnal->tugas, 20) : '—' }}</td>
                            <td><x-chip :tone="$tonePresensi" :label="$saya ? ucfirst($saya->status) : '—'" /></td>
                            <td class="is-num"><x-chip :tone="$status['tone']" :label="$status['label']" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="empty-state">Belum ada jurnal kelas.</td></tr>
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
