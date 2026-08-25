@extends('layouts.app')

@section('title', 'Data Siswa')

@section('content')
    <x-page-head title="Tambah Siswa" sub="Data siswa dan akunnya dibuat sekaligus dalam satu transaksi.">
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.siswa.index') }}">Kembali</a>
    </x-page-head>

    <x-card title="Data Siswa">
        <x-slot:actions><span class="card-hifi__meta">* wajib diisi</span></x-slot:actions>

        <form method="POST" action="{{ route('admin.siswa.store') }}" class="form-grid">
            @csrf
            @include('admin.siswa.form', ['siswa' => null])

            <div class="d-flex justify-content-end gap-2">
                <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.siswa.index') }}">Batal</a>
                <button class="btn-hifi" type="submit">Simpan Siswa</button>
            </div>
        </form>
    </x-card>
@endsection
