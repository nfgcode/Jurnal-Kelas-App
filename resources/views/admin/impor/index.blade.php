@extends('layouts.app')

@section('title', 'Impor Data')

@section('content')
    {{-- A plain & here, not &amp;: the component echoes the attribute through
         {{ }}, which escapes it once on the way out. --}}
    <x-page-head
        title="Impor Data Siswa & Guru"
        sub="Unduh template Excel, isi, lalu unggah untuk membuat banyak akun sekaligus">
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.users.index') }}">← Daftar Pengguna</a>
    </x-page-head>

    @if ($jumlahKelas === 0)
        <p class="banner banner--bahaya mb-2">
            Belum ada kelas terdaftar. Impor siswa membutuhkan nama kelas yang sudah ada —
            <a class="auth__link" href="{{ route('kelas.create') }}">tambahkan kelas dulu</a>.
        </p>
    @endif

    <div class="grid-row grid-row--2">
        @foreach (['siswa' => $kolomSiswa, 'guru' => $kolomGuru] as $jenis => $kolom)
            <x-card :title="'Impor ' . ucfirst($jenis)" :meta="count($kolom) . ' kolom'">
                <p class="field__hint">
                    Template berisi baris judul, dua baris contoh, dan lembar <strong>Petunjuk</strong>
                    berisi penjelasan tiap kolom serta daftar kelas yang terdaftar.
                    Hapus baris contoh sebelum mengunggah.
                </p>

                <a class="btn-hifi btn-hifi--ghost mt-2"
                   href="{{ route('admin.impor.template', ['jenis' => $jenis]) }}">
                    <x-ikon nama="download" /> Unduh Template {{ ucfirst($jenis) }}
                </a>

                <div class="tbl-wrap mt-3">
                    <table class="tbl">
                        <thead>
                            <tr><th>Kolom</th><th>Wajib</th><th>Penjelasan</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($kolom as $def)
                                <tr>
                                    <td class="is-strong is-nowrap">{{ $def['judul'] }}</td>
                                    <td>
                                        <x-chip :tone="$def['wajib'] ? 'red' : 'neutral'"
                                                :label="$def['wajib'] ? 'Wajib' : 'Opsional'" />
                                    </td>
                                    <td class="is-muted">{{ $def['petunjuk'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        @endforeach
    </div>

    <x-card title="Unggah Berkas Terisi" meta="Ditinjau dulu sebelum disimpan">
        <form method="POST" action="{{ route('admin.impor.pratinjau') }}" enctype="multipart/form-data">
            @csrf

            <div class="grid-row grid-row--2">
                <x-field label="Jenis Data" name="jenis" required>
                    <select class="select-hifi" name="jenis" id="jenis" required>
                        <option value="siswa" @selected(old('jenis') === 'siswa')>Siswa</option>
                        <option value="guru" @selected(old('jenis') === 'guru')>Guru</option>
                    </select>
                </x-field>

                <x-field label="Berkas Excel (.xlsx atau .csv)" name="berkas" required>
                    <input class="input-hifi" type="file" name="berkas" id="berkas"
                           accept=".xlsx,.csv" required>
                </x-field>
            </div>

            <div class="grid-row grid-row--2">
                <x-field label="Password Bawaan" name="password_bawaan"
                         hint="Dipakai untuk baris yang kolom Password-nya kosong. Minimal 8 karakter. Mintalah pengguna menggantinya setelah login pertama.">
                    <input class="input-hifi" type="text" name="password_bawaan" id="password_bawaan"
                           value="{{ old('password_bawaan') }}" minlength="8" autocomplete="off">
                </x-field>

                <x-field label="Data yang Sudah Ada" name="perbarui">
                    <label class="d-flex align-items-center gap-2" style="font-size: 12.5px">
                        <input type="checkbox" name="perbarui" value="1" @checked(old('perbarui'))>
                        Perbarui data yang sudah ada (dicocokkan berdasarkan email)
                    </label>
                    <span class="field__hint">
                        Tanpa ini, baris dengan email yang sudah terdaftar akan ditolak, bukan ditimpa.
                    </span>
                </x-field>
            </div>

            <button class="btn-hifi mt-2" type="submit">
                <x-ikon nama="file-earmark-arrow-up" /> Periksa Berkas
            </button>
        </form>
    </x-card>
@endsection
