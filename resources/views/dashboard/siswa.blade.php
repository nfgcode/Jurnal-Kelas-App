@extends('layouts.app')

@section('title', 'Dashboard Siswa')

@section('content')
    @php
        $sapaan = $isKetua
            ? 'Selamat Datang, Ketua Kelas ' . ($kelas?->nama_kelas ?? '') . '!'
            : 'Selamat Datang, ' . Auth::user()->nama . '!';
        $hariIni = $tanggal->isToday();
        // "Hari Ini" only when it is; another day is already named by the
        // date picker and the table's date chip.
        $sufiks = $hariIni ? ' Hari Ini' : '';
        $kapan = $hariIni ? 'hari ini' : 'pada ' . $tanggal->translatedFormat('l, j F');
        // A day that has not come yet has nothing to write or read.
        $akanDatang = $tanggal->isAfter(today());
    @endphp

    {{-- Figma "Dashboard Siswa" (MoSCoW): greeting + date, two action cards,
         the day's lessons with one action each, and the week beside them. --}}
    <div class="dash dash--lega">
        <div class="card-hifi dash-head">
            <div>
                <h2 class="page-head__title">{{ $sapaan }}</h2>
                <p class="page-head__sub">
                    Ringkasan harian kelas {{ $kelas?->nama_kelas ?? '—' }}
                    · Semester Gasal {{ now()->year }}/{{ now()->year + 1 }}
                </p>
            </div>
            {{-- Oct 2026: a ketua kelas works with today only — no day picker. --}}
            @unless ($isKetua)
                <x-pilih-tanggal :tanggal="$tanggal" />
            @endunless
        </div>

        <div class="aksi-row aksi-row--2">
            @if ($isKetua)
                <x-aksi-card :href="route('jurnal.create', ['tanggal' => $tanggal->toDateString()])"
                             :judul="'Isi Jurnal Kelas' . $sufiks" ikon="book" warna="hijau" cta="Isi Jurnal"
                             :deskripsi="$belumDitulis
                                 ? $belumDitulis . ' jurnal kelas belum diisi. Catat sekarang.'
                                 : 'Catat dan pantau jurnal kelas ' . $kapan . '.'" />
            @else
                <x-aksi-card :href="route('jurnal.index')"
                             :judul="'Jurnal Kelas' . $sufiks" ikon="book" warna="hijau" cta="Lihat Jurnal"
                             deskripsi="Baca jurnal pelajaran kelasmu." />
            @endif

            @if ($isKetua)
                {{-- The ketua reads today's journals; attendance screens are not
                     theirs any more. --}}
                <x-aksi-card :href="route('jurnal.index')"
                             judul="Jurnal Kelas Hari Ini" ikon="calendar3" warna="oranye" cta="Lihat Jurnal"
                             deskripsi="Lihat jurnal kelas yang sudah diisi hari ini." />
            @else
                <x-aksi-card :href="route('presensi.index')"
                             judul="Kehadiran Saya" ikon="calendar3" warna="oranye" cta="Lihat Presensi"
                             deskripsi="Lihat riwayat kehadiranmu di kelas." />
            @endif
        </div>

        <div @class(['dash-jadwal', 'dash-jadwal--tunggal' => $isKetua])>
            <x-card :title="'Jadwal Kelas' . $sufiks" flush class="dash-jadwal__tabel">
                <x-slot:actions>
                    <span class="chip chip--solid-lembut">{{ $tanggal->translatedFormat('l, j F Y') }}</span>
                    <a class="auth__link" href="{{ $isKetua ? route('jurnal.index') : route('jadwal.index') }}">Lihat semua →</a>
                </x-slot:actions>

                <div class="tbl-wrap">
                    <table class="tbl tbl--lega tbl--kartu">
                        <thead>
                            <tr>
                                <th>Jam</th>
                                <th>Mata Pelajaran</th>
                                <th>Guru</th>
                                <th>Ruang</th>
                                <th class="is-num">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($jadwal as $j)
                                @php $ada = $jurnal[$j->id] ?? null; @endphp
                                <tr>
                                    <td class="is-muted sel-jam"><span class="sel-jam__awal">Jam </span>{{ $j->jpLabel() }}</td>
                                    <td class="is-strong sel-judul">{{ $j->mataPelajaran?->nama }}</td>
                                    <td class="sel-sub">{{ $j->guru?->nama }}</td>
                                    <td class="is-muted sel-ruang">{{ $j->ruang ?? $kelas?->ruang ?? '—' }}</td>
                                    <td class="is-num sel-aksi">
                                        {{-- One button per lesson; its label carries the state. --}}
                                        @if ($ada)
                                            <a class="btn-hifi btn-hifi--ghost btn-hifi--baris" href="{{ route('jurnal.show', $ada) }}">Lihat Jurnal</a>
                                        @elseif ($akanDatang)
                                            <span class="btn-hifi btn-hifi--baris is-nonaktif" aria-disabled="true">Belum dimulai</span>
                                        @elseif ($isKetua)
                                            <a class="btn-hifi btn-hifi--baris"
                                               href="{{ route('jurnal.create', ['jadwal_id' => $j->id, 'tanggal' => $tanggal->toDateString()]) }}">Isi Jurnal</a>
                                        @else
                                            <span class="btn-hifi btn-hifi--baris is-nonaktif" aria-disabled="true">Belum diisi</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="empty-state">Tidak ada jadwal pada hari ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-slot:foot>
                    <span>{{ $jadwal->count() }} jadwal pada {{ $tanggal->translatedFormat('l, j F Y') }}</span>
                </x-slot:foot>
            </x-card>

            {{-- The week is for moving between days, which a ketua no longer does. --}}
            @unless ($isKetua)
                <x-kalender-mini :minggu="$minggu" :tanggal="$tanggal" satuan="jadwal"
                                 belum-label="%d belum ada jurnal" />
            @endunless
        </div>
    </div>
@endsection
