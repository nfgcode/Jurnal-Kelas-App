@extends('layouts.app')

@section('title', 'Mata Pelajaran')

@section('content')
    <x-page-head :title="'Ubah ' . $mataPelajaran->nama" sub="Perbarui data mata pelajaran.">
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('mata-pelajaran.show', $mataPelajaran) }}">Lihat Detail</a>
    </x-page-head>

    <x-card title="Data Mata Pelajaran">
        <x-slot:actions><span class="card-hifi__meta">* wajib diisi</span></x-slot:actions>

        <form method="POST" action="{{ route('mata-pelajaran.update', $mataPelajaran) }}" class="form-grid">
            @csrf
            @method('PUT')
            @include('mata-pelajaran.form')

            <div class="d-flex justify-content-between gap-2">
                <button class="btn-hifi btn-hifi--danger" type="submit" form="hapusMapel"
                        onclick="return confirm('Hapus {{ $mataPelajaran->nama }}?')">Hapus</button>

                <div class="d-flex gap-2">
                    <a class="btn-hifi btn-hifi--ghost" href="{{ route('mata-pelajaran.index') }}">Batal</a>
                    <button class="btn-hifi" type="submit">Simpan Perubahan</button>
                </div>
            </div>
        </form>
    </x-card>

    <form method="POST" action="{{ route('mata-pelajaran.destroy', $mataPelajaran) }}" id="hapusMapel" class="d-none">
        @csrf
        @method('DELETE')
    </form>
@endsection
