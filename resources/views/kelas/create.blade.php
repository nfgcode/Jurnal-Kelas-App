@extends('layouts.app')

@section('title', 'Kelas')

@section('content')
    <x-page-head title="Tambah Kelas" sub="Buat rombongan belajar baru untuk tahun ajaran berjalan.">
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('kelas.index') }}">Kembali</a>
    </x-page-head>

    <x-card title="Data Kelas">
        <x-slot:actions><span class="card-hifi__meta">* wajib diisi</span></x-slot:actions>

        <form method="POST" action="{{ route('kelas.store') }}" class="form-grid">
            @csrf
            @include('kelas.form', ['kelas' => null])

            <div class="d-flex justify-content-end gap-2">
                <a class="btn-hifi btn-hifi--ghost" href="{{ route('kelas.index') }}">Batal</a>
                <button class="btn-hifi" type="submit">Simpan Kelas</button>
            </div>
        </form>
    </x-card>
@endsection
