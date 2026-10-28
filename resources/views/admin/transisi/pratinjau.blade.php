@extends('layouts.app')

@section('title', 'Pratinjau Transisi: ' . $pratinjau['tahun_asal'] . ' ke ' . $pratinjau['tahun_tujuan'])

@section('content')
    <x-page-head
        title="Pratinjau Transisi: {{ $pratinjau['tahun_asal'] }} &rarr; {{ $pratinjau['tahun_tujuan'] }}"
        sub="Tinjau pemetaan kenaikan kelas, kelulusan tingkat XII, dan atur pengecualian sebelum eksekusi permanen">
        <a href="{{ route('admin.transisi.index') }}" class="btn-hifi btn-hifi--ghost">&larr; Kembali</a>
    </x-page-head>

    {{-- Masalah Validasi / Peringatan --}}
    @if (! empty($pratinjau['masalah_validasi']))
        <div class="alert alert-danger mb-4">
            <h6 class="fw-bold mb-2">Perhatian: Ada prasyarat yang belum terpenuhi sebelum transisi dapat dieksekusi:</h6>
            <ul class="mb-0 ps-3">
                @foreach ($pratinjau['masalah_validasi'] as $masalah)
                    <li>{{ $masalah }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (! empty($pratinjau['peringatan']))
        <div class="alert alert-warning mb-4">
            <ul class="mb-0 ps-3">
                @foreach ($pratinjau['peringatan'] as $p)
                    <li>{{ $p }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Statistik Ringkasan --}}
    <div class="grid-row grid-row--4 mb-4">
        <x-stat label="Calon Lulusan (XII)" :value="number_format($pratinjau['statistik']['total_lulusan'], 0, ',', '.')" caption="masuk ke data alumni" />
        <x-stat label="Promosi X &rarr; XI" :value="number_format($pratinjau['statistik']['total_promosi_x_ke_xi'], 0, ',', '.')" caption="naik tingkat XI" />
        <x-stat label="Promosi XI &rarr; XII" :value="number_format($pratinjau['statistik']['total_promosi_xi_ke_xii'], 0, ',', '.')" caption="naik tingkat XII" />
        <x-stat label="Status Kesiapan" :value="$pratinjau['siap_eksekusi'] ? 'SIAP' : 'BELUM SIAP'"
                :caption="$pratinjau['siap_eksekusi'] ? 'semua kelas tujuan tersedia' : 'ada kelas tujuan yang kurang'" />
    </div>

    <form method="POST" action="{{ route('admin.transisi.eksekusi') }}" id="formTransisi">
        @csrf
        <input type="hidden" name="tahun_asal" value="{{ $pratinjau['tahun_asal'] }}">
        <input type="hidden" name="tahun_tujuan" value="{{ $pratinjau['tahun_tujuan'] }}">

        {{-- Tab Pemetaan --}}
        <div class="card-hifi mb-4">
            <div class="card-hifi__header border-bottom-0 pb-0">
                <ul class="nav nav-tabs card-header-tabs" id="tabTransisi" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-semibold" id="promosi-tab" data-bs-toggle="tab" data-bs-target="#promosi-pane" type="button" role="tab">
                            Kenaikan Kelas X & XI ({{ number_format($pratinjau['statistik']['total_promosi']) }})
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold" id="kelulusan-tab" data-bs-toggle="tab" data-bs-target="#kelulusan-pane" type="button" role="tab">
                            Kelulusan Kelas XII ({{ number_format($pratinjau['statistik']['total_lulusan']) }})
                        </button>
                    </li>
                </ul>
            </div>

            <div class="tab-content" id="tabTransisiContent">
                {{-- Pane 1: Kenaikan Kelas X & XI --}}
                <div class="tab-pane fade show active" id="promosi-pane" role="tabpanel">
                    <div class="tbl-wrap">
                        <table class="tbl">
                            <thead>
                                <tr>
                                    <th>NIS</th>
                                    <th>Nama Siswa</th>
                                    <th>Kelas Asal</th>
                                    <th>Tingkat</th>
                                    <th>Kelas Tujuan (Default)</th>
                                    <th>Aksi / Pengecualian</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($pratinjau['calon_promosi'] as $cp)
                                    <tr>
                                        <td class="is-strong">{{ $cp['nis'] }}</td>
                                        <td>{{ $cp['nama'] }}</td>
                                        <td class="is-muted">{{ $cp['nama_kelas_asal'] }}</td>
                                        <td>
                                            <span class="badge bg-secondary">{{ $cp['tingkat_asal'] }} &rarr; {{ $cp['tingkat_tujuan'] }}</span>
                                        </td>
                                        <td>
                                            @if ($cp['siap'])
                                                <span class="text-success fw-semibold">{{ $cp['nama_kelas_tujuan'] }}</span>
                                            @else
                                                <span class="text-danger fw-semibold">{{ $cp['nama_kelas_tujuan'] }} (Belum Ada)</span>
                                            @endif
                                        </td>
                                        <td>
                                            <select class="select-hifi select-hifi--sm" name="pengecualian[{{ $cp['nis'] }}][aksi]" style="width: 170px;">
                                                <option value="promosi">Naik Kelas</option>
                                                <option value="tinggal_kelas">Tinggal Kelas</option>
                                                <option value="keluar">Pindah/Keluar</option>
                                            </select>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="empty-state">Tidak ada calon siswa promosi.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Pane 2: Kelulusan Kelas XII --}}
                <div class="tab-pane fade" id="kelulusan-pane" role="tabpanel">
                    <div class="tbl-wrap">
                        <table class="tbl">
                            <thead>
                                <tr>
                                    <th>NIS</th>
                                    <th>Nama Siswa</th>
                                    <th>Kelas Terakhir</th>
                                    <th>Status Kelulusan</th>
                                    <th>Aksi / Pengecualian</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($pratinjau['calon_lulus'] as $cl)
                                    <tr>
                                        <td class="is-strong">{{ $cl['nis'] }}</td>
                                        <td>{{ $cl['nama'] }}</td>
                                        <td class="is-muted">{{ $cl['nama_kelas'] }}</td>
                                        <td>
                                            <x-chip tone="khaki" label="Lulus" />
                                        </td>
                                        <td>
                                            <select class="select-hifi select-hifi--sm" name="pengecualian[{{ $cl['nis'] }}][aksi]" style="width: 170px;">
                                                <option value="lulus">Lulus</option>
                                                <option value="tunda_lulus">Belum Lulus</option>
                                                <option value="keluar">Pindah/Keluar</option>
                                            </select>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="empty-state">Tidak ada calon siswa lulusan.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Konfirmasi & Eksekusi Box --}}
        <x-card title="Konfirmasi & Eksekusi Transisi">
            <div class="form-grid form-grid--3 mb-3">
                <x-field label="Tanggal Kelulusan" name="tanggal_lulus" hint="Diterapkan pada catatan alumni">
                    <input class="input-hifi" type="date" name="tanggal_lulus" value="{{ now()->toDateString() }}">
                </x-field>

                <div class="pt-3">
                    <label class="checkbox-row">
                        <input type="checkbox" name="nonaktifkan_akun" value="1">
                        Nonaktifkan akun login siswa yang lulus
                    </label>
                    <span class="field__hint d-block mt-1">Kosongkan jika siswa lulus tetap diizinkan login membaca riwayat kehadiran.</span>
                </div>

                <div class="pt-3">
                    <label class="checkbox-row">
                        <input type="checkbox" name="aktifkan_tahun_tujuan" value="1">
                        Jadikan tahun ajaran {{ $pratinjau['tahun_tujuan'] }} aktif
                    </label>
                    <span class="field__hint d-block mt-1">Otomatis menonaktifkan tahun ajaran berjalan lama.</span>
                </div>
            </div>

            <div class="alert alert-warning mb-3">
                <label class="checkbox-row fw-bold text-dark">
                    <input type="checkbox" name="konfirmasi" value="1" required>
                    Saya telah memverifikasi seluruh data di atas dan memahami perubahan ini akan memperbarui status siswa, rombel kelas, serta catatan alumni secara permanen.
                </label>
            </div>

            <div class="d-flex justify-content-between align-items-center">
                <a href="{{ route('admin.transisi.index') }}" class="btn-hifi btn-hifi--ghost">&larr; Batal</a>
                <button class="btn-hifi" type="submit" @disabled(! $pratinjau['siap_eksekusi'])>
                    <x-ikon nama="check-circle-fill" /> Eksekusi Transisi Tahun Ajaran Sekarang
                </button>
            </div>
        </x-card>
    </form>
@endsection
