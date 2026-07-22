@extends('layouts.app')

@section('title', 'Kelas')

@section('content')
    <x-page-head :title="'Ubah Kelas ' . $kelas->nama_kelas" sub="Perbarui data rombongan belajar.">
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('kelas.show', $kelas) }}">Lihat Detail</a>
    </x-page-head>

    <x-card title="Data Kelas">
        <x-slot:actions><span class="card-hifi__meta">* wajib diisi</span></x-slot:actions>

        <form method="POST" action="{{ route('kelas.update', $kelas) }}" class="form-grid">
            @csrf
            @method('PUT')
            @include('kelas.form')

            <div class="d-flex justify-content-between gap-2">
                <button class="btn-hifi btn-hifi--danger" type="submit" form="hapusKelas"
                        onclick="return confirm('Hapus kelas {{ $kelas->nama_kelas }}?')">Hapus Kelas</button>

                <div class="d-flex gap-2">
                    <a class="btn-hifi btn-hifi--ghost" href="{{ route('kelas.index') }}">Batal</a>
                    <button class="btn-hifi" type="submit">Simpan Perubahan</button>
                </div>
            </div>
        </form>
    </x-card>

    <form method="POST" action="{{ route('kelas.destroy', $kelas) }}" id="hapusKelas" class="d-none">
        @csrf
        @method('DELETE')
    </form>
@endsection
