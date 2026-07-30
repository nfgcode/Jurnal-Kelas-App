@extends('layouts.app')

@section('title', 'Jurnal')

@section('content')
    @php
        $totalKehadiran = $rekapKehadiran['total'] ?: 1;
        $ditandai = array_sum($presensi);
        $aksi = $jurnal ? route('jurnal.update', $jurnal) : route('jurnal.store');
        $pilihan = old('kehadiran_guru', $jurnal
            ? ($jurnal->kehadiran_guru_status === 'hadir' ? 'hadir' : ($jurnal->kehadiran_guru_ada_tugas ? 'ada_tugas' : 'tanpa_tugas'))
            : 'hadir');
    @endphp

    <x-page-head
        :title="$jurnal ? 'Ubah Jurnal Mengajar' : 'Isi Jurnal Mengajar'"
        :sub="collect([$kelas?->nama_kelas, $jadwal?->mataPelajaran?->nama, $jadwal ? 'JP ' . $jadwal->jpLabel() : null, now()->translatedFormat('l, j F Y')])->filter()->join(' · ')">
        <span class="btn-hifi btn-hifi--ghost">Draf tersimpan otomatis</span>
    </x-page-head>

    <div class="grid-row grid-row--editor">
        <x-card :title="$jurnal ? 'Form Jurnal Mengajar' : 'Form Jurnal Mengajar'">
            <x-slot:actions>
                <span class="card-hifi__meta">* wajib diisi</span>
            </x-slot:actions>

            <form method="POST" action="{{ $aksi }}" class="form-grid" id="formJurnal">
                @csrf
                @if ($jurnal) @method('PUT') @endif

                <div class="form-grid form-grid--3">
                    <x-field label="Tanggal" name="tanggal" required>
                        <input class="input-hifi" type="date" name="tanggal"
                               value="{{ old('tanggal', $jurnal?->tanggal?->toDateString() ?? today()->toDateString()) }}" required>
                    </x-field>

                    <x-field label="Jam Ke (Mulai)">
                        <input class="input-hifi" type="text" value="JP {{ $jadwal?->jam_ke_mulai ?? '—' }}" readonly>
                    </x-field>

                    <x-field label="Jam Ke (Selesai)">
                        <input class="input-hifi" type="text" value="JP {{ $jadwal?->jam_ke_selesai ?? '—' }}" readonly>
                    </x-field>
                </div>

                <div class="form-grid form-grid--2">
                    <x-field label="Jadwal (Kelas · Mata Pelajaran)" name="jadwal_id" required>
                        <select class="select-hifi" name="jadwal_id" id="jadwalSelect" data-searchable required
                                onchange="window.location = '{{ route('jurnal.create') }}?jadwal_id=' + this.value">
                            @foreach ($jadwalList as $pilihanJadwal)
                                <option value="{{ $pilihanJadwal->id }}" @selected($jadwal?->id === $pilihanJadwal->id)>
                                    {{ $pilihanJadwal->kelas?->nama_kelas }} · {{ $pilihanJadwal->mataPelajaran?->nama }}
                                    · {{ $pilihanJadwal->hari }} JP {{ $pilihanJadwal->jpLabel() }}
                                </option>
                            @endforeach
                        </select>
                    </x-field>

                    <x-field label="Ruang">
                        <input class="input-hifi" type="text" value="{{ $jadwal?->ruang ?? $kelas?->ruang ?? '—' }}" readonly>
                    </x-field>
                </div>

                <x-field label="Guru Pengajar">
                    <input class="input-hifi" type="text" value="{{ Auth::user()->name }}" readonly>
                </x-field>

                <x-field label="Materi yang Diajarkan" name="materi" required>
                    <textarea class="input-hifi" name="materi" required
                              placeholder="Uraikan materi yang disampaikan pada pertemuan ini...">{{ old('materi', $jurnal?->materi) }}</textarea>
                </x-field>

                <x-field label="Tugas / Pekerjaan Rumah" name="tugas">
                    <textarea class="input-hifi" name="tugas" style="min-height: 60px"
                              placeholder="Kosongkan bila tidak ada tugas.">{{ old('tugas', $jurnal?->tugas) }}</textarea>
                </x-field>

                <x-field label="Kehadiran Guru" name="kehadiran_guru" required
                         hint="Isi sesuai kondisi Anda pada jam ini.">
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

                {{-- Attendance is recorded per student on the presensi screen,
                     so this block reports the tally rather than editing it. --}}
                <div>
                    <div class="d-flex align-items-baseline justify-content-between mb-2">
                        <span class="field__label">Rekap Kehadiran Siswa</span>
                        <span class="field__hint">
                            {{ $jumlahSiswa }} siswa terdaftar · {{ max(0, $jumlahSiswa - $ditandai) }} belum ditandai
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

                    @if ($jurnal)
                        <a class="auth__link d-inline-block mt-2" href="{{ route('presensi.create', $jurnal) }}">
                            Tandai presensi per siswa →
                        </a>
                    @else
                        <span class="field__hint d-block mt-2">
                            Presensi per siswa ditandai setelah jurnal disimpan.
                        </span>
                    @endif
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
                                        <td class="is-muted">{{ $lalu->guru?->name }}</td>
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
                    <button class="btn-hifi" type="submit">{{ $jurnal ? 'Simpan Perubahan' : 'Simpan Jurnal' }}</button>
                </div>
            </form>
        </x-card>

        <div class="d-flex flex-column gap-3">
            <x-card title="Konteks Jadwal">
                <div class="deflist">
                    <div class="deflist__row"><span class="deflist__key">Kelas</span><span class="deflist__val">{{ $kelas?->nama_kelas ?? '—' }}</span></div>
                    <div class="deflist__row"><span class="deflist__key">Mata Pelajaran</span><span class="deflist__val">{{ $jadwal?->mataPelajaran?->nama ?? '—' }}</span></div>
                    <div class="deflist__row"><span class="deflist__key">Jam Ke</span><span class="deflist__val">{{ $jadwal?->jpLabel() ?? '—' }}</span></div>
                    <div class="deflist__row"><span class="deflist__key">Ruang</span><span class="deflist__val">{{ $jadwal?->ruang ?? $kelas?->ruang ?? '—' }}</span></div>
                    <div class="deflist__row"><span class="deflist__key">Jumlah Siswa</span><span class="deflist__val">{{ $jumlahSiswa }} siswa</span></div>
                    <div class="deflist__row"><span class="deflist__key">Pertemuan Ke</span><span class="deflist__val">{{ $pertemuanKe }}</span></div>
                </div>
            </x-card>

            <x-card title="Kehadiran Mengajar Saya" :meta="now()->translatedFormat('F Y')">
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

            <x-card title="Ringkasan Presensi Siswa" :meta="$jumlahSiswa . ' siswa'">
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
            </x-card>

            <x-card title="Sebelum Menyimpan">
                <div class="checklist">
                    @foreach ([
                        'Kehadiran guru sudah dipilih' => true,
                        'Materi sudah diisi' => filled(old('materi', $jurnal?->materi)),
                        'Kehadiran siswa sudah lengkap' => $ditandai >= $jumlahSiswa && $jumlahSiswa > 0,
                        'Periksa kembali jam pelajaran' => false,
                    ] as $label => $selesai)
                        <div class="checklist__item {{ $selesai ? 'checklist__item--done' : 'checklist__item--todo' }}">
                            <span class="checklist__box"><i class="bi bi-check-lg"></i></span>{{ $label }}
                        </div>
                    @endforeach
                </div>
            </x-card>
        </div>
    </div>
@endsection
