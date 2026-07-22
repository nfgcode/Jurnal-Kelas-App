@extends('layouts.app')

@section('title', 'Pengguna')

@section('content')
    <x-page-head title="Tambah Pengguna" sub="Buat akun baru untuk administrator, guru, atau siswa.">
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.users.index') }}">Kembali</a>
    </x-page-head>

    <x-card title="Data Pengguna">
        <x-slot:actions><span class="card-hifi__meta">* wajib diisi</span></x-slot:actions>

        <form method="POST" action="{{ route('admin.users.store') }}" class="form-grid">
            @csrf
            @include('admin.users.form', ['user' => null])

            <div class="d-flex justify-content-end gap-2">
                <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.users.index') }}">Batal</a>
                <button class="btn-hifi" type="submit">Simpan Pengguna</button>
            </div>
        </form>
    </x-card>
@endsection
