@extends('layouts.app')

@section('title', 'Ruangan')

@section('content')
    <x-page-head :title="'Ubah ' . $ruangan->kode" :sub="$ruangan->nama">
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('ruangan.show', $ruangan) }}">Kembali</a>
    </x-page-head>

    <x-card title="Data Ruangan">
        <x-slot:actions><span class="card-hifi__meta">* wajib diisi</span></x-slot:actions>

        <form method="POST" action="{{ route('ruangan.update', $ruangan) }}" class="form-grid">
            @csrf
            @method('PUT')
            @include('ruangan.form', ['ruangan' => $ruangan])

            <div class="d-flex justify-content-end gap-2">
                <a class="btn-hifi btn-hifi--ghost" href="{{ route('ruangan.show', $ruangan) }}">Batal</a>
                <button class="btn-hifi" type="submit">Simpan Perubahan</button>
            </div>
        </form>
    </x-card>
@endsection
