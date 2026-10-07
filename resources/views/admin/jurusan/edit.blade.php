@extends('layouts.app')

@section('title', 'Ubah Jurusan')
@section('processing', 'Memuat halaman jurusan...')

@section('content')
    <x-page-head :title="'Ubah Jurusan ' . $jurusan->kode" sub="Perubahan kode memperbarui referensi kelas dan siswa.">
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.jurusan.index') }}">Kembali</a>
    </x-page-head>
    <x-card title="Data Jurusan">
        <form class="form-grid" method="POST" action="{{ route('admin.jurusan.update', $jurusan) }}" data-processing="Menyimpan perubahan jurusan...">
            @csrf
            @method('PUT')
            @include('admin.jurusan.form')
            <div class="d-flex justify-content-end gap-2">
                <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.jurusan.index') }}">Batal</a>
                <button class="btn-hifi" type="submit">Simpan Perubahan</button>
            </div>
        </form>
    </x-card>
    <x-card title="Hapus Jurusan">
        <p>Jurusan hanya dapat dihapus jika tidak dipakai kelas atau siswa. Jika masih dipakai, ubah status menjadi Nonaktif.</p>
        <form method="POST" action="{{ route('admin.jurusan.destroy', $jurusan) }}" data-processing="Menghapus jurusan...">
            @csrf
            @method('DELETE')
            <label class="field-confirm mb-3">
                <input type="checkbox" required>
                <span>Saya yakin ingin menghapus jurusan {{ $jurusan->kode }}.</span>
            </label>
            <button class="btn-hifi btn-hifi--danger" type="submit">Hapus Jurusan</button>
        </form>
    </x-card>
@endsection
