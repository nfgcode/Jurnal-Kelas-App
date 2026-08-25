@extends('layouts.app')

@section('title', 'Data Guru')

@section('content')
    <x-page-head title="Tambah Guru" sub="Data guru dan akunnya dibuat sekaligus dalam satu transaksi.">
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.guru.index') }}">Kembali</a>
    </x-page-head>

    <x-card title="Data Guru">
        <x-slot:actions><span class="card-hifi__meta">* wajib diisi</span></x-slot:actions>

        <form method="POST" action="{{ route('admin.guru.store') }}" class="form-grid">
            @csrf
            @include('admin.guru.form', ['guru' => null])

            <div class="d-flex justify-content-end gap-2">
                <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.guru.index') }}">Batal</a>
                <button class="btn-hifi" type="submit">Simpan Guru</button>
            </div>
        </form>
    </x-card>
@endsection
