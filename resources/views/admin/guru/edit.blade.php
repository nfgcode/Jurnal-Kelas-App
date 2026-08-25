@extends('layouts.app')

@section('title', 'Data Guru')

@section('content')
    <x-page-head :title="'Ubah ' . $guru->nama" :sub="'NIP ' . $guru->nip">
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.guru.show', $guru) }}">Lihat Detail</a>
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.guru.index') }}">Kembali</a>
    </x-page-head>

    <x-card title="Data Guru">
        <x-slot:actions><span class="card-hifi__meta">* wajib diisi</span></x-slot:actions>

        <form method="POST" action="{{ route('admin.guru.update', $guru) }}" class="form-grid">
            @csrf
            @method('PUT')
            @include('admin.guru.form')

            <div class="d-flex justify-content-between gap-2">
                <button class="btn-hifi btn-hifi--ghost" type="submit" form="hapusGuru"
                        onclick="return confirm('Hapus {{ $guru->nama }} beserta akunnya?')">Hapus Guru</button>
                <span class="d-flex gap-2">
                    <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.guru.index') }}">Batal</a>
                    <button class="btn-hifi" type="submit">Simpan Perubahan</button>
                </span>
            </div>
        </form>

        <form method="POST" action="{{ route('admin.guru.destroy', $guru) }}" id="hapusGuru" class="d-none">
            @csrf
            @method('DELETE')
        </form>
    </x-card>
@endsection
