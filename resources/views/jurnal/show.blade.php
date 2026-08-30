@extends('layouts.app')

@section('title', 'Jurnal')

@section('content')
    @php
        $chip = $jurnal->kehadiranGuruChip();
        $status = $jurnal->statusPengisian();
        // This meeting's own roster, marked by the teacher who taught it — the
        // figures here describe this subject, not the whole school day.
        $jumlahTercatat = $presensi->count();
        $total = $jumlahTercatat ?: 1;
    @endphp

    <x-page-head
        :title="$jurnal->jadwal?->mataPelajaran?->nama ?? 'Jurnal'"
        :sub="collect([$jurnal->jadwal?->kelas?->nama_kelas, 'JP ' . $jurnal->jadwal?->jpLabel(), $jurnal->tanggal->translatedFormat('l, j F Y')])->filter()->join(' · ')">
        <x-chip :tone="$status['tone']" :label="$status['label']" />
        <x-jurnal-edit-badge :jurnal="$jurnal" />
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('jurnal.index') }}">← Daftar Jurnal</a>
        @if ($jurnal->jadwal?->kelas)
            <a class="btn-hifi btn-hifi--ghost"
               href="{{ route('presensi-harian.show', [$jurnal->jadwal->kelas_id, 'tanggal' => $jurnal->tanggal->toDateString()]) }}">
                Rekap Sehari
            </a>
        @endif
        {{-- The two halves of a meeting, each offered to the person who owns it:
             the class edits the journal, the teacher marks the roster. --}}
        @can('isiPresensi', $jurnal)
            <a class="btn-hifi" href="{{ route('presensi-jurnal.edit', $jurnal) }}">
                {{ $jumlahTercatat ? 'Perbarui Presensi' : 'Isi Presensi' }}
            </a>
        @endcan
        @can('update', $jurnal)
            <a class="btn-hifi btn-hifi--ghost" href="{{ route('jurnal.edit', $jurnal) }}">Ubah Jurnal</a>
        @endcan
        @can('delete', $jurnal)
            <button type="button" class="btn-hifi btn-hifi--danger"
                    data-bs-toggle="modal" data-bs-target="#hapusJurnal">Hapus</button>
        @endcan
    </x-page-head>

    @can('delete', $jurnal)
        {{-- Deleting a journal takes this meeting's roster with it (the foreign
             key cascades), and the class's daily rollup is recomputed without
             it. Other subjects that day keep their own attendance. --}}
        <div class="modal fade" id="hapusJurnal" tabindex="-1" aria-labelledby="hapusJurnalJudul" aria-hidden="true">
            <div class="modal-dialog modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="mb-0" id="hapusJurnalJudul">Hapus jurnal ini?</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>

                    <div class="modal-body">
                        <p class="mb-2">
                            <strong>{{ $jurnal->jadwal?->kelas?->nama_kelas }} ·
                            {{ $jurnal->jadwal?->mataPelajaran?->nama }}</strong><br>
                            {{ $jurnal->tanggal->translatedFormat('l, j F Y') }} · JP {{ $jurnal->jadwal?->jpLabel() }}
                        </p>

                        <p class="field__hint mb-0">
                            Presensi <strong>mata pelajaran ini</strong> ikut terhapus; presensi
                            mata pelajaran lain di hari yang sama tidak terpengaruh.
                            Tindakan ini tidak dapat dibatalkan.
                        </p>
                    </div>

                    <div class="modal-footer d-flex gap-2 justify-content-end">
                        <button type="button" class="btn-hifi btn-hifi--ghost" data-bs-dismiss="modal">Batal</button>
                        <form method="POST" action="{{ route('jurnal.destroy', $jurnal) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-hifi btn-hifi--danger">Ya, Hapus</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endcan

    <div class="grid-row grid-row--4">
        <x-stat label="Hadir" :value="$rekap['hadir'] ?? 0" :caption="round(($rekap['hadir'] ?? 0) / $total * 100) . '% dari kelas'" />
        <x-stat label="Sakit" :value="$rekap['sakit'] ?? 0" caption="dengan keterangan" />
        <x-stat label="Izin" :value="$rekap['izin'] ?? 0" caption="dengan keterangan" />
        <x-stat label="Alpa" :value="$rekap['alpa'] ?? 0" caption="tanpa keterangan" />
    </div>

    <div class="grid-row grid-row--editor">
        <div class="d-flex flex-column gap-3">
            <x-card title="Materi yang Diajarkan">
                <p class="mb-0" style="font-size: 12.5px; line-height: 1.6; color: var(--n-900)">{{ $jurnal->materi }}</p>
            </x-card>

            <x-card title="Tugas / Pekerjaan Rumah">
                <p class="mb-0" style="font-size: 12.5px; line-height: 1.6; color: var(--n-900)">
                    {{ $jurnal->tugas ?: 'Tidak ada tugas pada pertemuan ini.' }}
                </p>
            </x-card>

            <x-card :title="'Kehadiran Siswa — ' . ($jurnal->jadwal?->mataPelajaran?->nama ?? 'Pertemuan Ini')" flush>
                <x-slot:actions>
                    <span class="card-hifi__meta">
                        {{ $jumlahTercatat }} dari {{ $jumlahSiswa }} siswa ditandai
                    </span>
                </x-slot:actions>

                <div class="tbl-wrap">
                    <table class="tbl">
                        <thead>
                            <tr><th>No</th><th>NIS</th><th>Nama Siswa</th><th>Status</th><th class="is-num">Keterangan</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($presensi as $baris)
                                @php
                                    $tone = match ($baris->status) {
                                        'hadir' => 'green',
                                        'sakit' => 'khaki',
                                        'izin' => 'yellow',
                                        default => 'red',
                                    };
                                @endphp
                                <tr>
                                    <td class="is-muted">{{ $loop->iteration }}</td>
                                    <td class="is-muted">{{ $baris->siswa?->nis }}</td>
                                    <td>
                                        <span class="name-cell">
                                            <span class="avatar avatar--xs">{{ $baris->siswa?->inisial() }}</span>
                                            {{ $baris->siswa?->nama }}
                                        </span>
                                    </td>
                                    <td><x-chip :tone="$tone" :label="ucfirst($baris->status)" /></td>
                                    <td class="is-num is-muted">{{ $baris->keterangan ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="empty-state">
                                        {{ $jurnal->guru?->nama ?? 'Guru pengajar' }} belum menandai presensi
                                        {{ $jurnal->jadwal?->mataPelajaran?->nama }} pada
                                        {{ $jurnal->tanggal->translatedFormat('j F Y') }}.
                                        @can('isiPresensi', $jurnal)
                                            <a class="auth__link" href="{{ route('presensi-jurnal.edit', $jurnal) }}">Isi sekarang →</a>
                                        @endcan
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>

        <div class="d-flex flex-column gap-3">
            <x-card title="Konteks Pertemuan">
                <div class="deflist">
                    <div class="deflist__row"><span class="deflist__key">Kelas</span><span class="deflist__val">{{ $jurnal->jadwal?->kelas?->nama_kelas ?? '—' }}</span></div>
                    <div class="deflist__row"><span class="deflist__key">Mata Pelajaran</span><span class="deflist__val">{{ $jurnal->jadwal?->mataPelajaran?->nama ?? '—' }}</span></div>
                    <div class="deflist__row"><span class="deflist__key">Guru</span><span class="deflist__val">{{ $jurnal->guru?->nama ?? '—' }}</span></div>
                    <div class="deflist__row"><span class="deflist__key">Jam Ke</span><span class="deflist__val">{{ $jurnal->jadwal?->jpLabel() ?? '—' }}</span></div>
                    <div class="deflist__row"><span class="deflist__key">Ruang</span><span class="deflist__val">{{ $jurnal->jadwal?->ruang ?? '—' }}</span></div>
                    <div class="deflist__row">
                        <span class="deflist__key">Diisi Oleh</span>
                        <span class="deflist__val">{{ $jurnal->diisiOleh?->nama ?? $jurnal->guru?->nama ?? '—' }}</span>
                    </div>
                    <div class="deflist__row">
                        <span class="deflist__key">Diisi Pada</span>
                        <span class="deflist__val">{{ $jurnal->created_at?->translatedFormat('d/m/Y H:i') ?? '—' }}</span>
                    </div>
                    @if ($jurnal->updated_at && $jurnal->created_at && $jurnal->updated_at->gt($jurnal->created_at))
                        <div class="deflist__row">
                            <span class="deflist__key">Diubah</span>
                            <span class="deflist__val">{{ $jurnal->updated_at->translatedFormat('d/m/Y H:i') }}</span>
                        </div>
                    @endif
                </div>
            </x-card>

            <x-card title="Kehadiran Guru">
                <div class="d-flex align-items-center justify-content-between">
                    <span style="font-size: 12px; color: var(--n-700)">Status</span>
                    <x-chip :tone="$chip['tone']" :label="$chip['label']" />
                </div>

                @if ($jurnal->kehadiran_guru_keterangan)
                    <p class="field__hint mt-2 mb-0">{{ $jurnal->kehadiran_guru_keterangan }}</p>
                @endif
            </x-card>

            <x-card title="Ringkasan Presensi" :meta="$jumlahTercatat . ' dari ' . $jumlahSiswa . ' siswa'">
                <x-stack-bar :hadir="$rekap['hadir'] ?? 0" :sakit="$rekap['sakit'] ?? 0"
                             :izin="$rekap['izin'] ?? 0" :alpa="$rekap['alpa'] ?? 0" />

                <div class="d-flex justify-content-between mt-3">
                    @foreach ([
                        'Hadir' => ['hadir', 'var(--green-200)'],
                        'Sakit' => ['sakit', 'var(--s-300)'],
                        'Izin' => ['izin', 'var(--yellow-200)'],
                        'Alpa' => ['alpa', 'var(--red-100)'],
                    ] as $label => [$kunci, $warna])
                        <div>
                            <div class="counter__label"><span class="legend__dot" style="background: {{ $warna }}"></span>{{ $label }}</div>
                            <div class="counter__value">{{ $rekap[$kunci] ?? 0 }}</div>
                        </div>
                    @endforeach
                </div>
            </x-card>
        </div>
    </div>
@endsection
