@extends('layouts.app')

@section('title', 'Jurusan')

{{-- Expects: $bidangList (Collection<string>). Posts to admin.jurusan.store. --}}

@section('content')
    <x-page-head title="Tambah Jurusan" sub="Daftarkan jurusan baru yang dibuka sekolah.">
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.jurusan.index') }}">Kembali</a>
    </x-page-head>

    <x-card title="Data Jurusan">
        <x-slot:actions><span class="card-hifi__meta">* wajib diisi</span></x-slot:actions>

        <form method="POST" action="{{ route('admin.jurusan.store') }}" class="form-grid">
            @csrf
            @include('admin.jurusan.form', ['jurusan' => null])

            <div class="d-flex justify-content-end gap-2">
                <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.jurusan.index') }}">Batal</a>
                <button class="btn-hifi" type="submit">Simpan Jurusan</button>
            </div>
        </form>
    </x-card>
@endsection
