@extends('layouts.app')

@section('title', 'Mata Pelajaran')

@section('content')
    @php
        $pengampu = $mataPelajaran->guru;
        $terjadwal = $mataPelajaran->jadwals->pluck('guru_nip')->filter()->unique();
        $kelasDiajar = $mataPelajaran->jadwals->pluck('kelas.nama_kelas')->filter()->unique();
        $kelompokTone = ['wajib' => 'green', 'peminatan' => 'khaki', 'muatan_lokal' => 'neutral', 'kejuruan' => 'solid'];
    @endphp

    <x-page-head :title="$mataPelajaran->nama" :sub="$mataPelajaran->kode . ' · ' . $mataPelajaran->kelompokLabel()">
        @if (Auth::user()->isAdmin())
            <a class="btn-hifi btn-hifi--ghost" href="{{ route('mata-pelajaran.edit', $mataPelajaran) }}">Ubah</a>
        @endif
    </x-page-head>

    <div class="grid-row grid-row--4">
        <x-stat label="JP per Minggu" :value="$mataPelajaran->jp_per_minggu" caption="per rombel" />
        <x-stat label="Diajarkan Di" :value="$kelasDiajar->count()" caption="kelas" />
        <x-stat label="Guru Pengampu" :value="$pengampu->count()" :caption="$pengampu->first()?->nama ?? 'belum ditetapkan'" />
        <x-stat label="Total Jadwal" :value="$mataPelajaran->jadwals->count()" caption="slot per minggu" />
    </div>

    @if ($mataPelajaran->deskripsi)
        <x-card title="Deskripsi">
            <p class="mb-0" style="font-size: 12px; color: var(--n-800)">{{ $mataPelajaran->deskripsi }}</p>
        </x-card>
    @endif

    <x-card title="Guru Pengampu">
        <x-slot:actions>
            <span class="card-hifi__meta">{{ $pengampu->count() }} guru tercatat mengampu mapel ini</span>
        </x-slot:actions>

        @if (Auth::user()->isAdmin())
            <form method="POST" action="{{ route('mata-pelajaran.guru', $mataPelajaran) }}">
                @csrf
                @method('PUT')

                <p class="field__hint mb-2">
                    <x-ikon nama="info-circle" />
                    Centang guru yang berhak mengampu {{ $mataPelajaran->nama }}. Hanya guru tercentang
                    yang bisa dipilih saat menyusun jadwal mapel ini.
                </p>

                <div class="form-grid form-grid--3">
                    @foreach ($guruList as $guru)
                        @php
                            $dipilih = $pengampu->contains('nip', $guru->nip);
                            $dikunci = $terjadwal->contains($guru->nip);
                        @endphp
                        <label class="pref-toggle">
                            <input type="checkbox" name="guru_nip[]" value="{{ $guru->nip }}"
                                   @checked(old('guru_nip') ? in_array($guru->nip, (array) old('guru_nip'), true) : $dipilih)>
                            <span>
                                {{ $guru->nama }}
                                <span class="is-muted">· {{ $guru->nip }}</span>
                                @if ($dikunci)
                                    <x-chip tone="yellow" label="Terjadwal" />
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>

                <x-field label="Guru Utama" name="utama"
                         hint="Penanggung jawab mapel ini bila diampu lebih dari satu guru. Opsional.">
                    <select class="select-hifi" name="utama" id="utama" data-searchable>
                        <option value="">— Tidak ditentukan —</option>
                        @foreach ($guruList as $guru)
                            <option value="{{ $guru->nip }}"
                                @selected(old('utama', $pengampu->firstWhere('pivot.utama', true)?->nip) === $guru->nip)>
                                {{ $guru->nama }}
                            </option>
                        @endforeach
                    </select>
                </x-field>

                <div class="d-flex justify-content-end gap-2">
                    <button class="btn-hifi" type="submit">Simpan Guru Pengampu</button>
                </div>
            </form>
        @else
            @forelse ($pengampu as $guru)
                <span class="name-cell">
                    <span class="avatar avatar--xs">{{ $guru->inisial() }}</span>
                    {{ $guru->nama }}
                    @if ($guru->pivot->utama)
                        <x-chip tone="green" label="Utama" />
                    @endif
                </span>
            @empty
                <p class="empty-state mb-0">Belum ada guru yang tercatat mengampu mata pelajaran ini.</p>
            @endforelse
        @endif
    </x-card>

    <x-card title="Jadwal Mata Pelajaran" flush>
        <x-slot:actions>
            <x-chip :tone="$kelompokTone[$mataPelajaran->kelompok] ?? 'neutral'" :label="$mataPelajaran->kelompokLabel()" />
        </x-slot:actions>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr><th>Hari</th><th>JP</th><th>Kelas</th><th>Guru</th><th class="is-num">Ruang</th></tr>
                </thead>
                <tbody>
                    @forelse ($mataPelajaran->jadwals->sortBy(['hari', 'jam_ke_mulai']) as $jadwal)
                        <tr>
                            <td class="is-muted is-nowrap">{{ $jadwal->hari }}</td>
                            <td class="is-muted">{{ $jadwal->jpLabel() }}</td>
                            <td class="is-strong">{{ $jadwal->kelas?->nama_kelas }}</td>
                            <td class="is-muted">{{ $jadwal->guru?->nama }}</td>
                            <td class="is-num is-muted">{{ $jadwal->ruang ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-state">Mata pelajaran ini belum terhubung ke jadwal manapun.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
