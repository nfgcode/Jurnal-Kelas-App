@extends('layouts.app')

@section('title', 'Isi Presensi Harian')

@section('content')
    @php
        $totalSiswa = $siswaList->count();
    @endphp

    <x-page-head
        :title="$sudahDiisi ? 'Perbarui Presensi Hari Ini' : 'Isi Presensi Hari Ini'"
        :sub="collect([$kelas->nama_kelas, $tanggal->translatedFormat('l, j F Y'), 'satu kali untuk seluruh hari'])->filter()->join(' · ')">
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('presensi.index') }}">← Rekap Presensi</a>
        <button class="btn-hifi" type="submit" form="formPresensiHarian">Simpan Presensi</button>
    </x-page-head>

    @if ($sudahDiisi)
        <p class="field__hint mb-2">
            <x-ikon nama="info-circle" /> Presensi kelas ini untuk <strong>{{ $tanggal->translatedFormat('j F Y') }}</strong>
            sudah tercatat. Menyimpan lagi akan <strong>menggantikan</strong> catatan hari ini, bukan menambah catatan baru.
        </p>
    @else
        <p class="field__hint mb-2">
            <x-ikon nama="info-circle" /> Presensi diisi <strong>satu kali sehari</strong> untuk seluruh kelas.
            Setelah hari ini berganti, koreksi hanya dapat dilakukan oleh admin.
        </p>
    @endif

    <div class="filter-bar">
        <label class="filter-bar__search">
            <x-ikon nama="search" />
            <input class="input-hifi" type="search" id="cariSiswa" placeholder="Cari nama atau NIS...">
        </label>

        <button class="btn-hifi btn-hifi--ghost" type="button" id="tandaiSemua">Tandai semua: Hadir</button>

        <span class="filter-bar__note">{{ $totalSiswa }} siswa terdaftar</span>
    </div>

    <form method="POST" action="{{ route('presensi-harian.store', $kelas) }}" id="formPresensiHarian">
        @csrf
        <input type="hidden" name="tanggal" value="{{ $tanggal->toDateString() }}">

        <x-card :title="'Daftar Siswa — ' . $kelas->nama_kelas" flush>
            <x-slot:actions>
                <span class="card-hifi__meta">{{ $tanggal->translatedFormat('j F Y') }}</span>
            </x-slot:actions>

            <div class="tbl-wrap">
                <table class="tbl" id="tabelSiswa">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th>Kehadiran</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($siswaList as $index => $siswa)
                            @php
                                $baris = $tersimpan[$siswa->id] ?? null;
                                $default = $baris->status ?? 'hadir';
                            @endphp
                            <tr data-nama="{{ Str::lower($siswa->name) }}" data-nis="{{ $siswa->nis }}">
                                <td class="is-muted">{{ $index + 1 }}</td>
                                <td class="is-muted">{{ $siswa->nis }}</td>
                                <td>
                                    <span class="name-cell">
                                        <span class="avatar avatar--xs">{{ $siswa->inisial() }}</span>
                                        {{ $siswa->name }}
                                    </span>
                                    <input type="hidden" name="presensi[{{ $index }}][siswa_id]" value="{{ $siswa->id }}">
                                </td>
                                <td>
                                    <span class="seg">
                                        @foreach (['hadir' => 'H', 'sakit' => 'S', 'izin' => 'I', 'alpa' => 'A'] as $nilai => $huruf)
                                            <label class="seg__opt seg__opt--{{ substr($nilai, 0, 1) }}">
                                                <input type="radio" name="presensi[{{ $index }}][status]" value="{{ $nilai }}"
                                                       @checked($default === $nilai) required>
                                                {{ $huruf }}
                                            </label>
                                        @endforeach
                                    </span>
                                </td>
                                <td>
                                    <input class="input-hifi" type="text" style="height: 28px; font-size: 11px"
                                           name="presensi[{{ $index }}][keterangan]"
                                           value="{{ $baris->keterangan ?? '' }}" placeholder="—">
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty-state">Belum ada siswa terdaftar di kelas ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-slot:foot>
                <span>Menampilkan {{ $totalSiswa }} siswa</span>
                @if ($totalSiswa)
                    <button class="btn-hifi" type="submit">Simpan Presensi</button>
                @endif
            </x-slot:foot>
        </x-card>
    </form>

    @push('scripts')
        <script>
            const baris = [...document.querySelectorAll('#tabelSiswa tbody tr')];

            document.getElementById('cariSiswa')?.addEventListener('input', (event) => {
                const kata = event.target.value.toLowerCase().trim();
                baris.forEach((tr) => {
                    const cocok = (tr.dataset.nama ?? '').includes(kata) || (tr.dataset.nis ?? '').includes(kata);
                    tr.style.display = cocok ? '' : 'none';
                });
            });

            document.getElementById('tandaiSemua')?.addEventListener('click', () => {
                baris.forEach((tr) => {
                    const hadir = tr.querySelector('input[value="hadir"]');
                    if (hadir) hadir.checked = true;
                });
            });
        </script>
    @endpush
@endsection
