@extends('layouts.app')

@section('title', 'Kenaikan & Kelulusan')

@section('content')
    <x-page-head
        title="Kenaikan & Kelulusan"
        sub="Siapkan rombel, simulasikan kenaikan kelas, dan proses kelulusan siswa antar tahun ajaran.">
        @if ($tahunAktif)
            <div class="d-inline-flex align-items-center gap-2">
                <span class="field__label mb-0">Tahun Berjalan:</span>
                <x-chip tone="green" :label="$tahunAktif->kode" />
            </div>
        @else
            <x-chip tone="neutral" label="Belum Ada Tahun Aktif" />
        @endif
    </x-page-head>

    {{-- Ringkasan Statistik Siswa Tahun Berjalan --}}
    <div class="mb-2">
        <span class="field__hint fw-semibold text-secondary">
            Statistik Siswa Aktif Berdasarkan Tahun Ajaran Berjalan ({{ $statistik['tahun_kode'] }}):
        </span>
    </div>
    <div class="grid-row grid-row--4 mb-4">
        <x-stat label="Siswa Aktif" :value="number_format($statistik['total_siswa'], 0, ',', '.')" caption="seluruh kelas asal" />
        <x-stat label="Tingkat X" :value="number_format($statistik['tingkat_x'], 0, ',', '.')" caption="calon naik ke XI" />
        <x-stat label="Tingkat XI" :value="number_format($statistik['tingkat_xi'], 0, ',', '.')" caption="calon naik ke XII" />
        <x-stat label="Tingkat XII" :value="number_format($statistik['tingkat_xii'], 0, ',', '.')" caption="calon lulusan" />
    </div>

    {{-- Alur Transisi Tahunan (1. Penyiapan Kelas & 2. Pratinjau Kenaikan) --}}
    <div class="grid-row grid-row--2 mb-4 align-items-start">
        <x-card title="1. Siapkan Rombel Kelas Tujuan">
            <p class="field__hint mb-3">
                Sistem akan membuat rombel kelas di tahun ajaran tujuan secara otomatis dengan mempertahankan <strong>jurusan dan paralel</strong> kelas (contoh: X TKJ 1 &rarr; XI TKJ 1, XI TKJ 1 &rarr; XII TKJ 1, dan template rombel X baru).
            </p>

            <form method="POST" action="{{ route('admin.transisi.siapkan-kelas') }}" class="form-grid">
                @csrf
                <div class="form-grid form-grid--2">
                    <x-field label="Dari Tahun Ajaran" name="tahun_asal" required>
                        <select class="select-hifi" name="tahun_asal" required>
                            @foreach ($tahunList as $t)
                                <option value="{{ $t->kode }}" @selected($t->aktif)>{{ $t->kode }} {{ $t->aktif ? '(Aktif)' : '' }}</option>
                            @endforeach
                        </select>
                    </x-field>

                    <x-field label="Ke Tahun Ajaran Tujuan" name="tahun_tujuan" required>
                        <select class="select-hifi" name="tahun_tujuan" required>
                            <option value="">Pilih Tahun Tujuan...</option>
                            @foreach ($tahunList as $t)
                                @if (! $t->aktif)
                                    <option value="{{ $t->kode }}">{{ $t->kode }}</option>
                                @endif
                            @endforeach
                        </select>
                    </x-field>
                </div>

                <div class="d-flex justify-content-end mt-2">
                    <button class="btn-hifi" type="submit">Siapkan Rombel Kelas</button>
                </div>
            </form>
        </x-card>

        <x-card title="2. Pratinjau & Eksekusi Transisi">
            <p class="field__hint mb-3">
                Buka simulasi pratinjau kenaikan kelas (X &rarr; XI, XI &rarr; XII) dan pemrosesan kelulusan tingkat XII sebelum dieksekusi secara nyata.
            </p>

            <form method="GET" action="{{ route('admin.transisi.pratinjau') }}" class="form-grid">
                <div class="form-grid form-grid--2">
                    <x-field label="Tahun Ajaran Asal" name="tahun_asal" required>
                        <select class="select-hifi" name="tahun_asal" required>
                            @foreach ($tahunList as $t)
                                <option value="{{ $t->kode }}" @selected($t->aktif)>{{ $t->kode }} {{ $t->aktif ? '(Aktif)' : '' }}</option>
                            @endforeach
                        </select>
                    </x-field>

                    <x-field label="Tahun Ajaran Tujuan" name="tahun_tujuan" required>
                        <select class="select-hifi" name="tahun_tujuan" required>
                            <option value="">Pilih Tahun Tujuan...</option>
                            @foreach ($tahunList as $t)
                                @if (! $t->aktif)
                                    <option value="{{ $t->kode }}">{{ $t->kode }}</option>
                                @endif
                            @endforeach
                        </select>
                    </x-field>
                </div>

                <div class="d-flex justify-content-end mt-2">
                    <button class="btn-hifi" type="submit">Buka Pratinjau Transisi &rarr;</button>
                </div>
            </form>
        </x-card>
    </div>

    {{-- 3. Riwayat Transisi Tahunan --}}
    <x-card title="Riwayat Transisi Tahun Ajaran" flush>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Waktu Eksekusi</th>
                        <th>Tahun Asal &rarr; Tujuan</th>
                        <th class="is-num">Promosi</th>
                        <th class="is-num">Lulus</th>
                        <th class="is-num">Tinggal</th>
                        <th class="is-num">Status</th>
                        <th>Keterangan</th>
                        <th>Diproses Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transisiList as $tr)
                        @php
                            $tone = match ($tr->status) {
                                'selesai' => 'green',
                                'diproses' => 'yellow',
                                'gagal' => 'red',
                                default => 'neutral',
                            };
                        @endphp
                        <tr>
                            <td class="is-muted is-nowrap">{{ $tr->diproses_pada ? $tr->diproses_pada->format('d/m/Y H:i') : $tr->created_at->format('d/m/Y H:i') }}</td>
                            <td class="is-strong">{{ $tr->tahun_asal }} &rarr; {{ $tr->tahun_tujuan }}</td>
                            <td class="is-num">{{ number_format($tr->jumlah_siswa_dipromosikan, 0, ',', '.') }}</td>
                            <td class="is-num">{{ number_format($tr->jumlah_siswa_lulus, 0, ',', '.') }}</td>
                            <td class="is-num">{{ number_format($tr->jumlah_siswa_tinggal, 0, ',', '.') }}</td>
                            <td class="is-num"><x-chip :tone="$tone" :label="ucfirst($tr->status)" /></td>
                            <td class="is-muted small">{{ Str::limit($tr->catatan, 60) }}</td>
                            <td class="is-muted">{{ $tr->diprosesOleh?->nama ?? $tr->diprosesOleh?->username ?? 'Sistem' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-state">Belum ada riwayat transisi tahun ajaran.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
