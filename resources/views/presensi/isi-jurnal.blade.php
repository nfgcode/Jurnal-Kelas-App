@extends('layouts.app')

@section('title', 'Isi Presensi')

@section('content')
    @php
        $totalSiswa = $siswaList->count();
        $mapel = $jurnal->jadwal?->mataPelajaran?->nama;
        $ditandai = $tersimpan->count();
    @endphp

    <x-page-head
        :title="($sudahDiisi ? 'Perbarui Presensi ' : 'Isi Presensi ') . $mapel"
        :sub="collect([
            $kelas->nama_kelas,
            'JP ' . $jurnal->jadwal?->jpLabel(),
            $jurnal->tanggal->translatedFormat('l, j F Y'),
        ])->filter()->join(' · ')">
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('jurnal.show', $jurnal) }}">← Jurnal Pertemuan</a>
        <button class="btn-hifi" type="submit" form="formPresensiJurnal">Simpan Presensi</button>
    </x-page-head>

    <p class="field__hint mb-2">
        <x-ikon nama="info-circle" />
        @if ($sudahDiisi)
            Presensi <strong>{{ $mapel }}</strong> pertemuan ini sudah tercatat
            ({{ $ditandai }} siswa). Menyimpan lagi akan <strong>menggantikan</strong> catatan
            pertemuan ini — mata pelajaran lain di hari yang sama tidak ikut berubah.
        @else
            Presensi dicatat <strong>per mata pelajaran</strong>. Yang Anda simpan di sini berlaku
            untuk <strong>{{ $mapel }}</strong> saja; jam pelajaran lain punya presensinya sendiri.
        @endif
    </p>

    <div class="filter-bar">
        <label class="filter-bar__search">
            <x-ikon nama="search" />
            <input class="input-hifi" type="search" id="cariSiswa" placeholder="Cari nama atau NIS...">
        </label>

        <button class="btn-hifi btn-hifi--ghost" type="button" data-tandai="hadir">Tandai semua: Hadir</button>
        <button class="btn-hifi btn-hifi--ghost" type="button" data-tandai="alpa">Tandai semua: Alpa</button>

        <span class="filter-bar__note">{{ $totalSiswa }} siswa terdaftar</span>
    </div>

    <div class="grid-row grid-row--editor">
        <form method="POST" action="{{ route('presensi-jurnal.store', $jurnal) }}" id="formPresensiJurnal">
            @csrf

            <x-card :title="'Daftar Siswa — ' . $kelas->nama_kelas" flush>
                <x-slot:actions>
                    <span class="card-hifi__meta">{{ $mapel }} · {{ $jurnal->tanggal->translatedFormat('j F Y') }}</span>
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
                                    $baris = $tersimpan[$siswa->nis] ?? null;
                                    $default = $baris->status ?? 'hadir';
                                @endphp
                                <tr data-nama="{{ Str::lower($siswa->nama) }}" data-nis="{{ $siswa->nis }}">
                                    <td class="is-muted">{{ $index + 1 }}</td>
                                    <td class="is-muted">{{ $siswa->nis }}</td>
                                    <td>
                                        <span class="name-cell">
                                            <span class="avatar avatar--xs">{{ $siswa->inisial() }}</span>
                                            {{ $siswa->nama }}
                                        </span>
                                        <input type="hidden" name="presensi[{{ $index }}][siswa_nis]" value="{{ $siswa->nis }}">
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

        <div class="d-flex flex-column gap-3">
            <x-card title="Konteks Pertemuan">
                <div class="deflist">
                    <div class="deflist__row"><span class="deflist__key">Kelas</span><span class="deflist__val">{{ $kelas->nama_kelas }}</span></div>
                    <div class="deflist__row"><span class="deflist__key">Mata Pelajaran</span><span class="deflist__val">{{ $mapel ?? '—' }}</span></div>
                    <div class="deflist__row"><span class="deflist__key">Jam Ke</span><span class="deflist__val">{{ $jurnal->jadwal?->jpLabel() ?? '—' }}</span></div>
                    <div class="deflist__row"><span class="deflist__key">Ruang</span><span class="deflist__val">{{ $jurnal->jadwal?->ruang ?? $kelas->ruang ?? '—' }}</span></div>
                    <div class="deflist__row">
                        <span class="deflist__key">Ditandai Oleh</span>
                        <span class="deflist__val">{{ Auth::user()->nama }}</span>
                    </div>
                </div>
            </x-card>

            {{-- The whole point of per-subject attendance, made visible: the other
                 lessons this class had today and whether they are marked yet. --}}
            <x-card title="Mata Pelajaran Lain Hari Ini" :meta="$jurnal->tanggal->translatedFormat('j F Y')" flush>
                <div class="tbl-wrap">
                    <table class="tbl">
                        <tbody>
                            @forelse ($pertemuanLain as $lain)
                                <tr>
                                    <td class="is-muted is-nowrap" style="padding-left: 0">
                                        JP {{ $lain->jadwal?->jpLabel() }}
                                    </td>
                                    <td class="{{ $lain->is($jurnal) ? 'is-strong' : '' }}">
                                        {{ $lain->jadwal?->mataPelajaran?->nama ?? '—' }}
                                        @if ($lain->is($jurnal))
                                            <span class="is-muted">(ini)</span>
                                        @endif
                                    </td>
                                    <td class="is-num" style="padding-right: 0">
                                        @if ($lain->jumlah_presensi > 0)
                                            <x-chip tone="green" :label="$lain->jumlah_presensi . ' ditandai'" />
                                        @else
                                            <x-chip tone="neutral" label="Belum" />
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td class="empty-state">Belum ada pertemuan lain tercatat hari ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>

            <x-card title="Cara Kerjanya">
                <p class="field__hint mb-0">
                    Presensi yang Anda simpan berlaku untuk pertemuan ini saja. Rekap harian kelas
                    disusun otomatis dari seluruh mata pelajaran hari itu — bila seorang siswa alpa di
                    salah satu jam, rekap harian mencatatnya sebagai alpa.
                </p>
            </x-card>
        </div>
    </div>

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

            // "Tandai semua" only touches the rows currently visible, so a teacher
            // can search for a group and mark just those without wiping the rest.
            document.querySelectorAll('[data-tandai]').forEach((tombol) => {
                tombol.addEventListener('click', () => {
                    baris.forEach((tr) => {
                        if (tr.style.display === 'none') return;
                        const pilihan = tr.querySelector('input[value="' + tombol.dataset.tandai + '"]');
                        if (pilihan) pilihan.checked = true;
                    });
                });
            });
        </script>
    @endpush
@endsection
