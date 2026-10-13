@extends('layouts.app')

@section('title', 'Hari Libur')
@section('processing', 'Memuat hari libur...')

@section('content')
    <x-page-head title="Hari Libur" sub="Tanggal tanpa kegiatan belajar: libur nasional, ujian, PKL. Jurnal otomatis tidak dibuat pada tanggal ini, dan tanggal ini tidak dihitung dalam kelengkapan jurnal." />

    <x-card title="Tambah Hari Libur">
        <form method="POST" action="{{ route('admin.hari-libur.store') }}" data-processing="Menyimpan hari libur...">
            @csrf
            <div class="form-grid form-grid--3">
                <x-field label="Tanggal mulai *" name="tanggal_mulai">
                    <input class="input-hifi" type="date" id="tanggal_mulai" name="tanggal_mulai"
                           value="{{ old('tanggal_mulai') }}" required>
                </x-field>
                <x-field label="Tanggal selesai" name="tanggal_selesai" hint="Kosongkan bila hanya satu hari.">
                    <input class="input-hifi" type="date" id="tanggal_selesai" name="tanggal_selesai"
                           value="{{ old('tanggal_selesai') }}">
                </x-field>
                <x-field label="Keterangan *" name="keterangan">
                    <input class="input-hifi" id="keterangan" name="keterangan" maxlength="150" required
                           value="{{ old('keterangan') }}" placeholder="mis. Maulid Nabi, Ujian Tengah Semester">
                </x-field>
            </div>
            <p class="field__hint">Jurnal otomatis yang sudah terlanjur dibuat pada tanggal tersebut ikut dihapus, kecuali yang sudah berisi presensi.</p>
            <div class="d-flex justify-content-end">
                <button class="btn-hifi" type="submit">Simpan Hari Libur</button>
            </div>
        </form>
    </x-card>

    <x-card title="Daftar Hari Libur" flush>
        <x-slot:actions>
            <div class="d-flex gap-2">
                <a class="btn-hifi {{ $tampil === 'mendatang' ? '' : 'btn-hifi--ghost' }}"
                   href="{{ route('admin.hari-libur.index') }}">Mendatang</a>
                <a class="btn-hifi {{ $tampil === 'semua' ? '' : 'btn-hifi--ghost' }}"
                   href="{{ route('admin.hari-libur.index', ['tampil' => 'semua']) }}">Semua</a>
            </div>
        </x-slot:actions>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Hari</th>
                        <th>Keterangan</th>
                        <th class="is-num">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($libur as $l)
                        <tr>
                            <td class="is-strong">{{ $l->tanggal->translatedFormat('j F Y') }}</td>
                            <td>{{ $l->tanggal->translatedFormat('l') }}</td>
                            <td>{{ $l->keterangan }}</td>
                            <td class="is-num">
                                <form method="POST" action="{{ route('admin.hari-libur.destroy', $l) }}" class="d-inline"
                                      data-processing="Menghapus hari libur...">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn-hifi btn-hifi--ghost" type="submit"
                                            aria-label="Hapus hari libur {{ $l->tanggal->translatedFormat('j F Y') }}">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty-state">
                            {{ $tampil === 'mendatang' ? 'Belum ada hari libur mendatang.' : 'Belum ada hari libur.' }}
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-slot:foot>
            <span>Menampilkan {{ $libur->count() }} dari {{ $libur->total() }} tanggal</span>
            <x-pager :paginator="$libur" :processing="true" />
        </x-slot:foot>
    </x-card>
@endsection
