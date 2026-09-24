@extends('layouts.app')

@section('title', 'Dashboard Guru')

@section('content')
    @php
        // "Hari Ini" only when it is; another day is already named by the
        // date picker and the table's date chip.
        $sufiks = $tanggal->isToday() ? ' Hari Ini' : '';
        $kapan = $tanggal->isToday() ? 'hari ini' : $tanggal->translatedFormat('l, j F');
        // A roster can only be marked for a lesson that has taken place.
        $akanDatang = $tanggal->isAfter(today());
    @endphp

    {{-- Figma "Dashboard Guru" (MoSCoW): greeting + date, ONE presensi card
         (the "isi" and "lihat" cards were merged in review), the day's lessons
         with one action each, and the week beside them. --}}
    <div class="dash dash--lega">
        <div class="card-hifi dash-head">
            <div>
                <h2 class="page-head__title">Selamat Datang, {{ Auth::user()->nama }}!</h2>
                <p class="page-head__sub">
                    Ringkasan mengajar {{ $kapan }} · Semester Gasal {{ now()->year }}/{{ now()->year + 1 }}
                </p>
            </div>
            <x-pilih-tanggal :tanggal="$tanggal" />
        </div>

        <div class="aksi-row aksi-row--1">
            <x-aksi-card :href="route('presensi.index')"
                         :judul="'Presensi Siswa' . $sufiks" ikon="people" warna="hijau" cta="Isi Presensi"
                         :deskripsi="$belumDitandai
                             ? $belumDitandai . ' kelas belum ditandai presensinya. Tandai per mata pelajaran, lalu pantau statusnya.'
                             : 'Tandai kehadiran siswa per mata pelajaran, lalu pantau status presensinya.'" />
        </div>

        <div class="dash-jadwal">
            <x-card :title="'Jadwal Mengajar' . $sufiks" flush class="dash-jadwal__tabel">
                <x-slot:actions>
                    <span class="chip chip--solid-lembut">{{ $tanggal->translatedFormat('l, j F Y') }}</span>
                    <a class="auth__link" href="{{ route('jadwal.index') }}">Lihat semua →</a>
                </x-slot:actions>

                <div class="tbl-wrap">
                    <table class="tbl tbl--lega tbl--kartu">
                        <thead>
                            <tr>
                                <th>Jam</th>
                                <th>Kelas</th>
                                <th>Mata Pelajaran</th>
                                <th>Ruang</th>
                                <th class="is-num">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($jadwal as $j)
                                @php
                                    $ada = $jurnal[$j->id] ?? null;
                                    $sudah = $ada && ($ditandai[$ada->id] ?? 0) > 0;
                                @endphp
                                <tr>
                                    <td class="is-muted sel-jam"><span class="sel-jam__awal">Jam </span>{{ $j->jpLabel() }}</td>
                                    <td class="is-strong sel-judul">{{ $j->kelas?->nama_kelas }}</td>
                                    <td class="sel-sub">{{ $j->mataPelajaran?->nama }}</td>
                                    <td class="is-muted sel-ruang">{{ $j->ruang ?? $j->kelas?->ruang ?? '—' }}</td>
                                    <td class="is-num sel-aksi">
                                        @if ($akanDatang)
                                            <span class="btn-hifi btn-hifi--baris is-nonaktif" aria-disabled="true">Belum dimulai</span>
                                        @else
                                            {{-- POST, not a link: the roster may have to open the meeting's
                                                 record when the class has not written its journal yet. --}}
                                            <form method="POST" action="{{ route('presensi-jurnal.mulai') }}" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="jadwal_id" value="{{ $j->id }}">
                                                <input type="hidden" name="tanggal" value="{{ $tanggal->toDateString() }}">
                                                <button class="btn-hifi btn-hifi--baris {{ $sudah ? 'btn-hifi--ghost' : '' }}" type="submit">
                                                    {{ $sudah ? 'Lihat Presensi' : 'Tandai Presensi' }}
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="empty-state">Tidak ada jadwal mengajar pada hari ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-slot:foot>
                    <span>{{ $jadwal->count() }} jadwal mengajar pada {{ $tanggal->translatedFormat('l, j F Y') }}</span>
                </x-slot:foot>
            </x-card>

            <x-kalender-mini :minggu="$minggu" :tanggal="$tanggal" satuan="jadwal mengajar"
                             belum-label="%d perlu ditandai" />
        </div>
    </div>
@endsection
