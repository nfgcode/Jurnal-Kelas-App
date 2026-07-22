@extends('layouts.app')

@section('title', 'Jadwal')

@section('content')
    <x-page-head title="Tambah Jadwal" sub="Tempatkan satu mata pelajaran pada slot jam pelajaran.">
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('jadwal.index') }}">Kembali</a>
    </x-page-head>

    <x-card title="Data Jadwal">
        <x-slot:actions><span class="card-hifi__meta">* wajib diisi</span></x-slot:actions>

        <form method="POST" action="{{ route('jadwal.store') }}" class="form-grid">
            @csrf
            @include('jadwal.form', ['jadwal' => null])

            <div class="d-flex justify-content-end gap-2">
                <a class="btn-hifi btn-hifi--ghost" href="{{ route('jadwal.index') }}">Batal</a>
                <button class="btn-hifi" type="submit">Simpan Jadwal</button>
            </div>
        </form>
    </x-card>
@endsection
