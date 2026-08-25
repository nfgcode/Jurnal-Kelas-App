@extends('layouts.app')

@section('title', 'Ruangan')

@section('content')
    <x-page-head title="Tambah Ruangan" sub="Daftarkan ruangan baru agar bisa dipilih di kelas dan jadwal.">
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('ruangan.index') }}">Kembali</a>
    </x-page-head>

    <x-card title="Data Ruangan">
        <x-slot:actions><span class="card-hifi__meta">* wajib diisi</span></x-slot:actions>

        <form method="POST" action="{{ route('ruangan.store') }}" class="form-grid">
            @csrf
            @include('ruangan.form', ['ruangan' => null])

            <div class="d-flex justify-content-end gap-2">
                <a class="btn-hifi btn-hifi--ghost" href="{{ route('ruangan.index') }}">Batal</a>
                <button class="btn-hifi" type="submit">Simpan Ruangan</button>
            </div>
        </form>
    </x-card>
@endsection
