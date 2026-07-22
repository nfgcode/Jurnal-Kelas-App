@extends('layouts.app')

@section('title', 'Jadwal')

@section('content')
    <x-page-head
        :title="$jadwal->mataPelajaran?->nama ?? 'Jadwal'"
        :sub="collect([$jadwal->kelas?->nama_kelas, $jadwal->hari, 'JP ' . $jadwal->jpLabel(), $jadwal->ruang])->filter()->join(' · ')">
        @if (Auth::user()->isAdmin())
            <a class="btn-hifi btn-hifi--ghost" href="{{ route('jadwal.edit', $jadwal) }}">Ubah</a>
        @endif
        <a class="btn-hifi" href="{{ route('jurnal.create', ['jadwal_id' => $jadwal->id]) }}">Isi Jurnal</a>
    </x-page-head>

    <div class="grid-row" style="grid-template-columns: repeat(4, minmax(0, 1fr))">
        <x-stat label="Kelas" :value="$jadwal->kelas?->nama_kelas ?? '—'" :caption="$jadwal->kelas?->jurusan" />
        <x-stat label="Guru" :value="$jadwal->guru?->inisial() ?? '—'" :caption="$jadwal->guru?->name" />
        <x-stat label="Jam Pelajaran" :value="'JP ' . $jadwal->jpLabel()"
                :caption="substr($jadwal->jam_mulai, 0, 5) . '–' . substr($jadwal->jam_selesai, 0, 5)" />
        <x-stat label="Jurnal Tercatat" :value="$jadwal->jurnals->count()" caption="pertemuan" />
    </div>

    <x-card title="Riwayat Jurnal Slot Ini" flush>
        <x-slot:actions>
            <span class="card-hifi__meta">{{ $jadwal->jurnals->count() }} pertemuan</span>
        </x-slot:actions>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr><th>Tanggal</th><th>Materi</th><th>Tugas</th><th class="is-num">Status</th></tr>
                </thead>
                <tbody>
                    @forelse ($jadwal->jurnals->sortByDesc('tanggal') as $jurnal)
                        @php $status = $jurnal->statusPengisian(); @endphp
                        <tr>
                            <td class="is-muted is-nowrap">{{ $jurnal->tanggal->format('d/m/Y') }}</td>
                            <td>
                                <a class="text-reset text-decoration-none" href="{{ route('jurnal.show', $jurnal) }}">
                                    {{ Str::limit($jurnal->materi, 60) }}
                                </a>
                            </td>
                            <td class="is-muted">{{ $jurnal->tugas ? Str::limit($jurnal->tugas, 40) : '—' }}</td>
                            <td class="is-num"><x-chip :tone="$status['tone']" :label="$status['label']" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty-state">Belum ada jurnal untuk slot jadwal ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
