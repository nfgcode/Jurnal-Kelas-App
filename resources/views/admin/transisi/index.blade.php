@extends('layouts.app')

@section('title', 'Tahun Ajaran & Transisi')

@section('content')
    <x-page-head
        title="Tahun Ajaran & Transisi Akademik"
        sub="Kelola kalender akademik, penyiapan rombel kelas, kenaikan kelas paralel, dan kelulusan siswa">
        <span class="badge bg-primary fs-6 px-3 py-2">
            Tahun Aktif: {{ $tahunAktif?->kode ?? 'Belum Ditentukan' }}
        </span>
    </x-page-head>

    <div class="grid-row grid-row--4 mb-4">
        <x-stat label="Siswa Aktif" :value="number_format($statistik['total_siswa'], 0, ',', '.')" caption="total seluruh rombel" />
        <x-stat label="Tingkat X" :value="number_format($statistik['tingkat_x'], 0, ',', '.')" caption="calon naik ke XI" />
        <x-stat label="Tingkat XI" :value="number_format($statistik['tingkat_xi'], 0, ',', '.')" caption="calon naik ke XII" />
        <x-stat label="Tingkat XII" :value="number_format($statistik['tingkat_xii'], 0, ',', '.')" caption="calon lulusan" />
    </div>

    {{-- 1. Daftar Tahun Ajaran & Tambah Tahun Ajaran --}}
    <div class="grid-row grid-row--2 mb-4">
        <x-card title="Daftar Tahun Ajaran" flush>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Periode</th>
                            <th class="is-num">Rombel</th>
                            <th class="is-num">Status</th>
                            <th class="is-num">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tahunList as $th)
                            <tr>
                                <td class="is-strong">{{ $th->kode }}</td>
                                <td class="is-muted">
                                    {{ $th->mulai ? $th->mulai->format('d/m/Y') : '—' }} s/d {{ $th->selesai ? $th->selesai->format('d/m/Y') : '—' }}
                                </td>
                                <td class="is-num">{{ $th->kelas_count }}</td>
                                <td class="is-num">
                                    <x-chip :tone="$th->aktif ? 'green' : 'neutral'" :label="$th->aktif ? 'Aktif' : 'Nonaktif'" />
                                </td>
                                <td class="is-num tbl__aksi">
                                    @if (! $th->aktif)
                                        <form method="POST" action="{{ route('admin.transisi.tahun-ajaran.aktifkan', $th) }}" class="d-inline"
                                              onsubmit="return confirm('Aktifkan tahun ajaran {{ $th->kode }}? Tahun berjalan sebelumnya akan dinonaktifkan.');">
                                            @csrf
                                            <button type="submit" class="btn-hifi btn-hifi--ghost btn-hifi--sm">Aktifkan</button>
                                        </form>
                                    @else
                                        <span class="text-success small fw-semibold">Berjalan</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="empty-state">Belum ada data tahun ajaran.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <x-card title="Tambah Tahun Ajaran Baru">
            <p class="field__hint mb-3">
                Buat tahun ajaran mendatang (misal <code>2027/2028</code>) untuk penyiapan rombel kelas tujuan tanpa mengubah tahun berjalan saat ini.
            </p>

            <form method="POST" action="{{ route('admin.transisi.tahun-ajaran.store') }}" class="form-grid">
                @csrf
                <x-field label="Kode Tahun Ajaran" name="kode" required hint="Format: YYYY/YYYY (contoh: 2027/2028)">
                    <input class="input-hifi" type="text" name="kode" placeholder="2027/2028" required pattern="\d{4}/\d{4}">
                </x-field>

                <div class="form-grid form-grid--2">
                    <x-field label="Tanggal Mulai" name="mulai" hint="Opsional">
                        <input class="input-hifi" type="date" name="mulai">
                    </x-field>
                    <x-field label="Tanggal Selesai" name="selesai" hint="Opsional">
                        <input class="input-hifi" type="date" name="selesai">
                    </x-field>
                </div>

                <label class="checkbox-row mt-2">
                    <input type="checkbox" name="aktif" value="1">
                    Langsung aktifkan tahun ajaran ini sekarang
                </label>

                <div class="d-flex justify-content-end mt-3">
                    <button class="btn-hifi" type="submit">+ Simpan Tahun Ajaran</button>
                </div>
            </form>
        </x-card>
    </div>

    {{-- 2. Alur Transisi Tahunan (Penyiapan Kelas & Pratinjau Kenaikan) --}}
    <div class="grid-row grid-row--2 mb-4">
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
