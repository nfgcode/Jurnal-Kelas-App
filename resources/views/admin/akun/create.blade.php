@extends('layouts.app')

@section('title', 'Akun Pengguna')

@section('content')
    <x-page-head title="Tambah Akun Admin"
                 sub="Hanya akun admin yang dibuat di sini — akun guru dan siswa dibuat bersama data orangnya.">
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.akun.index') }}">Kembali</a>
    </x-page-head>

    <x-card title="Kredensial">
        <x-slot:actions><span class="card-hifi__meta">* wajib diisi</span></x-slot:actions>

        <form method="POST" action="{{ route('admin.akun.store') }}" class="form-grid">
            @csrf
            @include('admin.akun.form', ['akun' => null])

            <div class="d-flex justify-content-end gap-2">
                <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.akun.index') }}">Batal</a>
                <button class="btn-hifi" type="submit">Simpan Akun</button>
            </div>
        </form>
    </x-card>
@endsection
