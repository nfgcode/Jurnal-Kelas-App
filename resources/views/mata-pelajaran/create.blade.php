@extends('layouts.app')

@section('title', 'Mata Pelajaran')

@section('content')
    <x-page-head title="Tambah Mata Pelajaran" sub="Daftarkan mata pelajaran baru ke kurikulum berjalan.">
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('mata-pelajaran.index') }}">Kembali</a>
    </x-page-head>

    <x-card title="Data Mata Pelajaran">
        <x-slot:actions><span class="card-hifi__meta">* wajib diisi</span></x-slot:actions>

        <form method="POST" action="{{ route('mata-pelajaran.store') }}" class="form-grid">
            @csrf
            @include('mata-pelajaran.form', ['mataPelajaran' => null])

            <div class="d-flex justify-content-end gap-2">
                <a class="btn-hifi btn-hifi--ghost" href="{{ route('mata-pelajaran.index') }}">Batal</a>
                <button class="btn-hifi" type="submit">Simpan Mata Pelajaran</button>
            </div>
        </form>
    </x-card>
@endsection
