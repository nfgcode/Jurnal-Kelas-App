@extends('layouts.app')

@section('title', 'Isi via QR')

@section('content')
    <x-page-head
        title="Kelas {{ $kelas->nama_kelas }}"
        :sub="collect([$kelas->ruang, \Illuminate\Support\Carbon::today()->translatedFormat('l, j F Y')])->filter()->join(' · ')" />

    <div class="grid-row grid-row--2">
        <x-card title="Konfirmasi Pengisian">
            @if ($jadwals->isEmpty())
                <p class="empty-state">
                    Anda tidak mengajar di kelas {{ $kelas->nama_kelas }}, jadi tidak ada jurnal
                    yang bisa diisi dari QR ini. Pastikan Anda memindai QR ruang kelas yang benar.
                </p>
                <a class="btn-hifi btn-hifi--ghost" href="{{ route('dashboard') }}">Ke Dashboard</a>
            @else
                <p class="field__hint mb-3">
                    Anda memindai QR kelas <strong>{{ $kelas->nama_kelas }}</strong>. Pilih mata
                    pelajaran yang Anda ajar di kelas ini, lalu lanjut mengisi jurnal dan presensi.
                </p>

                <form method="GET" action="{{ route('jurnal.create') }}" class="form-grid">
                    <x-field label="Mata Pelajaran (jadwal Anda di kelas ini)" name="jadwal_id" required>
                        <select class="select-hifi" name="jadwal_id" data-searchable required>
                            @foreach ($jadwals as $jadwal)
                                @php $terisi = in_array($jadwal->id, $sudahDiisi, true); @endphp
                                <option value="{{ $jadwal->id }}" @selected($rekomendasi?->id === $jadwal->id)>
                                    {{ $jadwal->mataPelajaran?->nama }} · {{ $jadwal->hari }} JP {{ $jadwal->jpLabel() }}{{ $terisi ? ' · sudah diisi hari ini' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </x-field>

                    <div class="d-flex justify-content-end gap-2">
                        <a class="btn-hifi btn-hifi--ghost" href="{{ route('dashboard') }}">Batal</a>
                        <button class="btn-hifi" type="submit">Isi Jurnal &amp; Presensi →</button>
                    </div>
                </form>
            @endif
        </x-card>

        <x-card title="Jadwal Anda di Kelas Ini" flush>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Hari</th>
                            <th>JP</th>
                            <th>Mata Pelajaran</th>
                            <th class="is-num">Hari Ini</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($jadwals as $jadwal)
                            @php $terisi = in_array($jadwal->id, $sudahDiisi, true); @endphp
                            <tr>
                                <td class="is-muted">{{ $jadwal->hari }}</td>
                                <td class="is-muted">{{ $jadwal->jpLabel() }}</td>
                                <td class="is-strong">{{ $jadwal->mataPelajaran?->nama }}</td>
                                <td class="is-num">
                                    @if ($jadwal->hari === $hariIni)
                                        <x-chip :tone="$terisi ? 'green' : 'yellow'" :label="$terisi ? 'Terisi' : 'Belum'" />
                                    @else
                                        <span class="is-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty-state">Tidak ada jadwal Anda di kelas ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
@endsection
