@extends('layouts.app')

@section('title', 'Tambah Jurusan')
@section('processing', 'Memuat halaman jurusan...')

@section('content')
    <x-page-head title="Tambah Jurusan" sub="Daftarkan jurusan untuk dipilih pada kelas.">
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.jurusan.index') }}">Kembali</a>
    </x-page-head>
    <x-card title="Data Jurusan">
        <form class="form-grid" method="POST" action="{{ route('admin.jurusan.store') }}" data-processing="Menyimpan jurusan...">
            @csrf
            @include('admin.jurusan.form', ['jurusan' => null])
            <div class="d-flex justify-content-end gap-2">
                <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.jurusan.index') }}">Batal</a>
                <button class="btn-hifi" type="submit">Simpan Jurusan</button>
            </div>
        </form>
    </x-card>
@endsection
