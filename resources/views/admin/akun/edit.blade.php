@extends('layouts.app')

@section('title', 'Akun Pengguna')

@section('content')
    <x-page-head :title="'Ubah Akun ' . $akun->username" :sub="$akun->email . ' · ' . ucfirst($akun->role)">
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.akun.show', $akun) }}">Lihat Detail</a>
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.akun.index') }}">Kembali</a>
    </x-page-head>

    <x-card title="Kredensial">
        <x-slot:actions><span class="card-hifi__meta">* wajib diisi</span></x-slot:actions>

        <form method="POST" action="{{ route('admin.akun.update', $akun) }}" class="form-grid">
            @csrf
            @method('PUT')
            @include('admin.akun.form')

            <div class="d-flex justify-content-between gap-2">
                <button class="btn-hifi btn-hifi--ghost" type="submit" form="hapusAkun"
                        onclick="return confirm('Hapus akun {{ $akun->username }}? Data orangnya tetap tersimpan.')">Hapus Akun</button>
                <span class="d-flex gap-2">
                    <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.akun.index') }}">Batal</a>
                    <button class="btn-hifi" type="submit">Simpan Perubahan</button>
                </span>
            </div>
        </form>

        <form method="POST" action="{{ route('admin.akun.destroy', $akun) }}" id="hapusAkun" class="d-none">
            @csrf
            @method('DELETE')
        </form>
    </x-card>
@endsection
