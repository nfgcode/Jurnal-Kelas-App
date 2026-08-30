@extends('layouts.app')

@section('title', 'Jurnal')

@section('content')
    @php
        $ditandai = array_sum($presensi);
        $aksi = $jurnal ? route('jurnal.update', $jurnal) : route('jurnal.store');
        $pilihan = old('kehadiran_guru', $jurnal
            ? ($jurnal->kehadiran_guru_status === 'hadir' ? 'hadir' : ($jurnal->kehadiran_guru_ada_tugas ? 'ada_tugas' : 'tanpa_tugas'))
            : 'hadir');
    @endphp

    <x-page-head
        :title="$jurnal ? 'Ubah Jurnal Kelas' : 'Mengisi Jurnal Kelas'"
        :sub="collect([$kelas?->nama_kelas, $jadwal?->mataPelajaran?->nama, $jadwal ? 'JP ' . $jadwal->jpLabel() : null, now()->translatedFormat('l, j F Y')])->filter()->join(' · ')">
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('jurnal.index') }}">← Daftar Jurnal</a>
        {{-- The primary action, repeated at the top: on a long form the only
             submit used to be below the fold. --}}
        <button class="btn-hifi" type="submit" form="formJurnalKelas">
            {{ $jurnal ? 'Simpan Perubahan' : 'Kirim Jurnal' }}
        </button>
    </x-page-head>

    <div class="grid-row grid-row--editor">
        <x-card title="Form Jurnal Kelas">
            <x-slot:actions>
                <span class="card-hifi__meta">* wajib diisi</span>
            </x-slot:actions>

            <form method="POST" action="{{ $aksi }}" class="form-grid" id="formJurnalKelas">
                @csrf
                @if ($jurnal) @method('PUT') @endif

                <x-jurnal-attestasi :aktif="$dariSistem ?? false" />

                <x-jurnal-slot label="Jadwal (Mata Pelajaran · Jam)"
                               :jadwal="$jadwal" :jadwal-list="$jadwalList" :jadwal-terisi="$jadwalTerisi"
                               :tanggal-aktif="$tanggalAktif" :jurnal="$jurnal" :kelas="$kelas" />

                <x-field label="Guru Pengajar">
                    <input class="input-hifi" type="text" value="{{ $jadwal?->guru?->nama ?? '—' }}" readonly>
                </x-field>

                <x-field label="Materi yang Diajarkan" name="materi" required>
                    <textarea class="input-hifi" name="materi" required
                              placeholder="Tulis materi yang disampaikan guru pada pertemuan ini...">{{ old('materi', $jurnal?->materi) }}</textarea>
                </x-field>

                <x-field label="Tugas / Pekerjaan Rumah" name="tugas">
                    <textarea class="input-hifi" name="tugas" style="min-height: 60px"
                              placeholder="Kosongkan bila tidak ada tugas.">{{ old('tugas', $jurnal?->tugas) }}</textarea>
                </x-field>

                <x-field label="Kehadiran Guru" name="kehadiran_guru" required
                         hint="Tandai kehadiran guru yang mengajar jam ini. Kehadiran siswa bukan bagian dari jurnal — guru pengajar yang menandainya.">
                    <div class="form-grid form-grid--3">
                        @foreach ([
                            'hadir' => 'Hadir',
                            'ada_tugas' => 'Tidak Hadir – Ada Tugas',
                            'tanpa_tugas' => 'Tidak Hadir – Tidak Ada Tugas',
                        ] as $nilai => $label)
                            <label class="radio-card">
                                <input type="radio" name="kehadiran_guru" value="{{ $nilai }}" @checked($pilihan === $nilai)>
                                <span class="radio-card__dot"></span>{{ $label }}
                            </label>
                        @endforeach
                    </div>
                </x-field>

                <x-field label="Keterangan Ketidakhadiran" name="kehadiran_guru_keterangan">
                    <input class="input-hifi" type="text" name="kehadiran_guru_keterangan"
                           value="{{ old('kehadiran_guru_keterangan', $jurnal?->kehadiran_guru_keterangan) }}"
                           placeholder="Isi bila guru tidak hadir — mis. digantikan guru piket, ada tugas...">
                </x-field>

                <div>
                    <div class="d-flex align-items-baseline justify-content-between mb-2">
                        <span class="field__label">Presensi Siswa (dari guru pengajar)</span>
                        <span class="field__hint">
                            @if ($ditandai)
                                {{ $ditandai }} dari {{ $jumlahSiswa }} siswa sudah ditandai guru
                            @else
                                belum ditandai guru — hanya tampilan, bukan isian Anda
                            @endif
                        </span>
                    </div>

                    <div class="form-grid form-grid--4">
                        @foreach ([
                            'Hadir' => ['hadir', 'var(--green-200)'],
                            'Sakit' => ['sakit', 'var(--s-300)'],
                            'Izin' => ['izin', 'var(--yellow-200)'],
                            'Alpa' => ['alpa', 'var(--red-100)'],
                        ] as $label => [$kunci, $warna])
                            <div class="counter">
                                <span class="counter__label">
                                    <span class="legend__dot" style="background: {{ $warna }}"></span>{{ $label }}
                                </span>
                                <div class="counter__row">
                                    <span class="counter__value">{{ $presensi[$kunci] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if ($pertemuanTerakhir->isNotEmpty())
                    <div>
                        <div class="d-flex align-items-baseline justify-content-between mb-1">
                            <span class="field__label">Pertemuan Terakhir di {{ $kelas?->nama_kelas }}</span>
                            <span class="field__hint">kehadiran guru</span>
                        </div>
                        <div class="tbl-wrap">
                        <table class="tbl">
                            <tbody>
                                @foreach ($pertemuanTerakhir as $lalu)
                                    @php $chip = $lalu->kehadiranGuruChip(); @endphp
                                    <tr>
                                        <td class="is-muted is-nowrap" style="padding-left: 0">{{ $lalu->tanggal->format('d/m') }}</td>
                                        <td class="is-strong">{{ $lalu->jadwal?->mataPelajaran?->nama }}</td>
                                        <td class="is-muted">{{ $lalu->guru?->nama }}</td>
                                        <td class="is-muted">{{ Str::limit($lalu->materi, 28) }}</td>
                                        <td class="is-num" style="padding-right: 0">
                                            <x-chip :tone="$chip['tone']" :label="$chip['label']" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        </div>
                    </div>
                @endif

                <div class="d-flex justify-content-end gap-2">
                    <a class="btn-hifi btn-hifi--ghost" href="{{ route('jurnal.index') }}">Batal</a>
                    {{-- See jurnal/isi.blade.php. --}}
                    @unless ($jadwalList->isEmpty())
                        <button class="btn-hifi" type="submit">{{ $jurnal ? 'Simpan Perubahan' : 'Kirim Jurnal' }}</button>
                    @endunless
                </div>
            </form>
        </x-card>

        <div class="d-flex flex-column gap-3">
            <x-card title="Konteks Jadwal">
                <div class="deflist">
                    <div class="deflist__row"><span class="deflist__key">Kelas</span><span class="deflist__val">{{ $kelas?->nama_kelas ?? '—' }}</span></div>
                    <div class="deflist__row"><span class="deflist__key">Mata Pelajaran</span><span class="deflist__val">{{ $jadwal?->mataPelajaran?->nama ?? '—' }}</span></div>
                    <div class="deflist__row"><span class="deflist__key">Guru</span><span class="deflist__val">{{ $jadwal?->guru?->nama ?? '—' }}</span></div>
                    <div class="deflist__row"><span class="deflist__key">Jam Ke</span><span class="deflist__val">{{ $jadwal?->jpLabel() ?? '—' }}</span></div>
                    <div class="deflist__row"><span class="deflist__key">Ruang</span><span class="deflist__val">{{ $jadwal?->ruang ?? $kelas?->ruang ?? '—' }}</span></div>
                    <div class="deflist__row">
                        <span class="deflist__key">Diisi Oleh</span>
                        <span class="deflist__val">{{ Auth::user()->nama }}{{ Auth::user()->isKetuaKelas() ? ' (Ketua)' : '' }}</span>
                    </div>
                </div>
            </x-card>

            <x-card title="Kehadiran Guru Bulan Ini" :meta="$kelas?->nama_kelas">
                @foreach ([
                    ['Hadir', $rekapKehadiran['hadir'], 'var(--green-200)'],
                    ['Tidak Hadir – Ada Tugas', $rekapKehadiran['ada_tugas'], 'var(--yellow-200)'],
                    ['Tidak Hadir – Tanpa Tugas', $rekapKehadiran['tanpa_tugas'], 'var(--red-100)'],
                ] as [$label, $nilai, $warna])
                    <div class="d-flex align-items-center justify-content-between mb-2" style="font-size: 11.5px">
                        <span class="legend__item"><span class="legend__dot" style="background: {{ $warna }}"></span>{{ $label }}</span>
                        <span class="breakdown__value">{{ $nilai }}</span>
                    </div>
                @endforeach
            </x-card>

            <x-card title="Presensi Pertemuan Ini" :meta="$ditandai . ' dari ' . $jumlahSiswa . ' siswa'">
                <x-stack-bar :hadir="$presensi['hadir']" :sakit="$presensi['sakit']"
                             :izin="$presensi['izin']" :alpa="$presensi['alpa']" />

                <div class="d-flex justify-content-between mt-3">
                    @foreach ([
                        'Hadir' => ['hadir', 'var(--green-200)'],
                        'Sakit' => ['sakit', 'var(--s-300)'],
                        'Izin' => ['izin', 'var(--yellow-200)'],
                        'Alpa' => ['alpa', 'var(--red-100)'],
                    ] as $label => [$kunci, $warna])
                        <div>
                            <div class="counter__label"><span class="legend__dot" style="background: {{ $warna }}"></span>{{ $label }}</div>
                            <div class="counter__value">{{ $presensi[$kunci] }}</div>
                        </div>
                    @endforeach
                </div>

                <p class="field__hint mt-3 mb-0">
                    Presensi mata pelajaran ini ditandai oleh guru pengajarnya, bukan lewat jurnal.
                    Angka di atas hanya menampilkan apa yang sudah beliau catat.
                </p>
            </x-card>

            <x-card title="Sebelum Menyimpan">
                <div class="checklist">
                    @foreach ([
                        'Kehadiran guru sudah ditandai' => true,
                        'Materi sesuai yang diajarkan' => filled(old('materi', $jurnal?->materi)),
                        'Tugas dicatat bila ada' => true,
                        'Konfirmasi ke guru sebelum kirim' => false,
                    ] as $label => $selesai)
                        <div class="checklist__item {{ $selesai ? 'checklist__item--done' : 'checklist__item--todo' }}">
                            <span class="checklist__box"><x-ikon nama="check-lg" /></span>{{ $label }}
                        </div>
                    @endforeach
                </div>
            </x-card>
        </div>
    </div>
@endsection
