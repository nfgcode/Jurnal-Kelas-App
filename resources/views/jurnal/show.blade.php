@extends('layouts.app')

@section('title', 'Jurnal')

@section('content')
    @php
        $chip = $jurnal->kehadiranGuruChip();
        $status = $jurnal->statusPengisian();
        $rekap = $jurnal->presensis->countBy('status');
        $total = $jurnal->presensis->count() ?: 1;
    @endphp

    <x-page-head
        :title="$jurnal->jadwal?->mataPelajaran?->nama ?? 'Jurnal'"
        :sub="collect([$jurnal->jadwal?->kelas?->nama_kelas, 'JP ' . $jurnal->jadwal?->jpLabel(), $jurnal->tanggal->translatedFormat('l, j F Y')])->filter()->join(' · ')">
        <x-chip :tone="$status['tone']" :label="$status['label']" />
        <x-jurnal-edit-badge :jurnal="$jurnal" />
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('jurnal.index') }}">← Daftar Jurnal</a>
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('presensi.create', $jurnal) }}">Ubah Presensi</a>
        <a class="btn-hifi" href="{{ route('jurnal.edit', $jurnal) }}">Ubah Jurnal</a>
        @can('delete', $jurnal)
            <button type="button" class="btn-hifi btn-hifi--danger"
                    data-bs-toggle="modal" data-bs-target="#hapusJurnal">Hapus</button>
        @endcan
    </x-page-head>

    @can('delete', $jurnal)
        {{-- A confirm() dialog cannot say what actually goes: presensi and
             presensi_log both cascade on this row, so deleting one journal also
             erases the whole roster for that lesson and the record of who marked
             it. The count is named here so nobody discovers that afterwards. --}}
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

                        @if ($jurnal->presensis->isNotEmpty())
                            <p class="banner banner--bahaya mb-2">
                                Presensi <strong>{{ $jurnal->presensis->count() }} siswa</strong> pada pertemuan ini
                                ikut terhapus, beserta catatan siapa yang menandainya. Angka kehadiran kelas
                                akan berubah.
                            </p>
                        @endif

                        <p class="field__hint mb-0">Tindakan ini tidak dapat dibatalkan.</p>
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

            <x-card title="Daftar Kehadiran Siswa" flush>
                <x-slot:actions>
                    <span class="card-hifi__meta">{{ $jurnal->presensis->count() }} siswa ditandai</span>
                </x-slot:actions>

                <div class="tbl-wrap">
                    <table class="tbl">
                        <thead>
                            <tr><th>No</th><th>NIS</th><th>Nama Siswa</th><th>Status</th><th class="is-num">Keterangan</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($jurnal->presensis->sortBy('siswa.name') as $index => $presensi)
                                @php
                                    $tone = match ($presensi->status) {
                                        'hadir' => 'green',
                                        'sakit' => 'khaki',
                                        'izin' => 'yellow',
                                        default => 'red',
                                    };
                                @endphp
                                <tr>
                                    <td class="is-muted">{{ $loop->iteration }}</td>
                                    <td class="is-muted">{{ $presensi->siswa?->nis }}</td>
                                    <td>
                                        <span class="name-cell">
                                            <span class="avatar avatar--xs">{{ $presensi->siswa?->inisial() }}</span>
                                            {{ $presensi->siswa?->name }}
                                        </span>
                                    </td>
                                    <td><x-chip :tone="$tone" :label="ucfirst($presensi->status)" /></td>
                                    <td class="is-num is-muted">{{ $presensi->keterangan ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="empty-state">Presensi belum ditandai untuk pertemuan ini.</td></tr>
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
                    <div class="deflist__row"><span class="deflist__key">Guru</span><span class="deflist__val">{{ $jurnal->guru?->name ?? '—' }}</span></div>
                    <div class="deflist__row"><span class="deflist__key">Jam Ke</span><span class="deflist__val">{{ $jurnal->jadwal?->jpLabel() ?? '—' }}</span></div>
                    <div class="deflist__row"><span class="deflist__key">Ruang</span><span class="deflist__val">{{ $jurnal->jadwal?->ruang ?? '—' }}</span></div>
                    <div class="deflist__row">
                        <span class="deflist__key">Diisi Oleh</span>
                        <span class="deflist__val">{{ $jurnal->diisiOleh?->name ?? $jurnal->guru?->name ?? '—' }}</span>
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

            <x-card title="Ringkasan Presensi" :meta="$jurnal->presensis->count() . ' siswa'">
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
