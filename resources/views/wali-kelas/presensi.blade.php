@extends('layouts.app')

@section('title', 'Presensi Kelas')

@section('content')
    @php $total = array_sum($rekap) ?: 1; @endphp

    <x-page-head
        title="Presensi Kelas {{ $kelas->nama_kelas }}"
        :sub="number_format($totalHari, 0, ',', '.') . ' hari tercatat · rata-rata ' . round($rekap['hadir'] / $total * 100) . '% hadir · ' . $periode->label()">
        <x-kelas-switch :kelas-wali="$kelasWali" :kelas="$kelas" />
        <x-periode-filter :periode="$periode" />
        <a class="btn-hifi btn-hifi--ghost"
           href="{{ route('presensi.ekspor', ['mode' => 'bulanan', 'bulan' => now()->format('Y-m'), 'kelas_id' => $kelas->id]) }}">
            <x-ikon nama="download" /> Ekspor Bulan Ini
        </a>
    </x-page-head>

    <p class="field__hint mb-2">
        <x-ikon nama="info-circle" /> Presensi siswa diisi sekali sehari oleh ketua kelas.
        Halaman ini untuk memantau; koreksi tanggal yang sudah lewat dilakukan oleh admin.
    </p>

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
                        <th class="is-num">Total Hari</th>
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

    <x-card title="Histori Presensi Harian" flush>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th><x-th-sort kolom="tanggal" label="Tanggal" bawaan /></th>
                        <th>Hari</th>
                        <th class="is-num"><x-th-sort kolom="siswa" label="Total" /></th>
                        <th class="is-num"><x-th-sort kolom="hadir" label="H" /></th>
                        <th class="is-num"><x-th-sort kolom="sakit" label="S" /></th>
                        <th class="is-num"><x-th-sort kolom="izin" label="I" /></th>
                        <th class="is-num"><x-th-sort kolom="alpa" label="A" /></th>
                        <th><x-th-sort kolom="persen" label="Persentase Kehadiran" /></th>
                        <th class="is-num">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($hari as $baris)
                        @php
                            $tanggal = \Illuminate\Support\Carbon::parse($baris->tanggal);
                            $persen = $baris->total_siswa ? round($baris->hadir / $baris->total_siswa * 100) : 0;
                        @endphp
                        <tr>
                            <td class="is-strong is-nowrap">{{ $tanggal->format('d/m/Y') }}</td>
                            <td class="is-muted">{{ $tanggal->translatedFormat('l') }}</td>
                            <td class="is-num">{{ $baris->total_siswa }}</td>
                            <td class="is-num">{{ $baris->hadir }}</td>
                            <td class="is-num">{{ $baris->sakit }}</td>
                            <td class="is-num">{{ $baris->izin }}</td>
                            <td class="is-num">{{ $baris->alpa }}</td>
                            <td>
                                <span class="meter-cell">
                                    <x-stack-bar :hadir="$baris->hadir" :sakit="$baris->sakit"
                                                 :izin="$baris->izin" :alpa="$baris->alpa" />
                                    <span class="is-strong">{{ $persen }}%</span>
                                </span>
                            </td>
                            <td class="is-num tbl__aksi">
                                <a class="btn-hifi btn-hifi--ghost btn-hifi--sm"
                                   href="{{ route('presensi-harian.show', [$kelas, 'tanggal' => $tanggal->toDateString()]) }}">
                                    Lihat
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="empty-state">Belum ada presensi tercatat pada periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:foot>
            <span>
                Menampilkan {{ $hari->firstItem() ?? 0 }}–{{ $hari->lastItem() ?? 0 }}
                dari {{ number_format($hari->total(), 0, ',', '.') }} hari
            </span>
            <x-pager :paginator="$hari" />
        </x-slot:foot>
    </x-card>
@endsection
