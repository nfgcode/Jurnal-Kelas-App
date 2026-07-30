@extends('layouts.app')

@section('title', 'Presensi Kelas')

@section('content')
    @php $total = array_sum($rekap) ?: 1; @endphp

    <x-page-head
        title="Presensi Kelas {{ $kelas->nama_kelas }}"
        :sub="number_format($totalPertemuan, 0, ',', '.') . ' pertemuan · rata-rata ' . round($rekap['hadir'] / $total * 100) . '% hadir'">
        <x-kelas-switch :kelas-wali="$kelasWali" :kelas="$kelas" />
    </x-page-head>

    <div class="grid-row grid-row--4">
        <x-stat label="Hadir" :value="number_format($rekap['hadir'], 0, ',', '.')"
                :caption="round($rekap['hadir'] / $total * 100) . '% dari total'" />
        <x-stat label="Sakit" :value="number_format($rekap['sakit'], 0, ',', '.')"
                :caption="round($rekap['sakit'] / $total * 100) . '% dari total'" />
        <x-stat label="Izin" :value="number_format($rekap['izin'], 0, ',', '.')"
                :caption="round($rekap['izin'] / $total * 100) . '% dari total'" />
        <x-stat label="Alpa" :value="number_format($rekap['alpa'], 0, ',', '.')"
                :caption="round($rekap['alpa'] / $total * 100) . '% dari total'" />
    </div>

    {{-- The standing per-student picture first: this is what a wali follows up on. --}}
    <x-card title="Rekap Kehadiran per Siswa" flush>
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
                        <th>No</th>
                        <th>NIS</th>
                        <th>Nama Siswa</th>
                        <th class="is-num">Total</th>
                        <th class="is-num">H</th>
                        <th class="is-num">S</th>
                        <th class="is-num">I</th>
                        <th class="is-num">A</th>
                        <th>Persentase Kehadiran</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($siswa as $i => $s)
                        @php $r = $rekapSiswa[$s->id] ?? ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'alpa' => 0, 'total' => 0, 'persen' => 0]; @endphp
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
                            <td class="is-num">{{ $r['total'] }}</td>
                            <td class="is-num">{{ $r['hadir'] }}</td>
                            <td class="is-num">{{ $r['sakit'] }}</td>
                            <td class="is-num">{{ $r['izin'] }}</td>
                            <td class="is-num">{{ $r['alpa'] }}</td>
                            <td>
                                <span class="meter-cell">
                                    <x-stack-bar :hadir="$r['hadir']" :sakit="$r['sakit']"
                                                 :izin="$r['izin']" :alpa="$r['alpa']" />
                                    <span class="is-strong">{{ $r['persen'] }}%</span>
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="empty-state">Belum ada siswa di kelas ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <form class="filter-bar" method="GET">
        <input type="hidden" name="kelas_id" value="{{ $kelas->id }}">

        <label class="filter-bar__search">
            <i class="bi bi-search"></i>
            <input class="input-hifi" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                   placeholder="Cari materi, mapel atau guru...">
        </label>

        <select class="select-hifi" name="mata_pelajaran_id" style="width: 190px" onchange="this.form.submit()">
            <option value="">Semua Mapel</option>
            @foreach ($mapelList as $mapel)
                <option value="{{ $mapel->id }}" @selected(($filters['mata_pelajaran_id'] ?? null) == $mapel->id)>{{ $mapel->nama }}</option>
            @endforeach
        </select>

        <span class="filter-bar__note">
            Menampilkan {{ $pertemuan->count() }} dari {{ number_format($pertemuan->total(), 0, ',', '.') }}
        </span>
    </form>

    <x-card title="Histori Presensi per Pertemuan" flush>
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
                        <th class="is-num">Total</th>
                        <th class="is-num">H</th>
                        <th class="is-num">S</th>
                        <th class="is-num">I</th>
                        <th class="is-num">A</th>
                        <th>Persentase Kehadiran</th>
                        <th class="is-num">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pertemuan as $jurnal)
                        @php $persen = $jurnal->total_siswa ? round($jurnal->hadir_count / $jurnal->total_siswa * 100) : 0; @endphp
                        <tr>
                            <td class="is-muted is-nowrap">{{ $jurnal->tanggal->format('d/m/Y') }}</td>
                            <td class="is-muted">{{ $jurnal->jadwal?->jpLabel() }}</td>
                            <td class="is-strong">{{ $jurnal->jadwal?->mataPelajaran?->nama }}</td>
                            <td class="is-nowrap is-muted">{{ $jurnal->guru?->name ?? '—' }}</td>
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
                            <td class="is-num">
                                <a class="btn-hifi btn-hifi--ghost btn-hifi--sm" href="{{ route('presensi.create', $jurnal) }}">
                                    {{ $jurnal->total_siswa ? 'Ubah' : 'Tandai' }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="empty-state">Belum ada pertemuan yang cocok dengan filter.</td></tr>
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
