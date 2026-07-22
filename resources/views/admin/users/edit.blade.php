@extends('layouts.app')

@section('title', 'Pengguna')

@section('content')
    <x-page-head :title="'Ubah ' . $user->name" :sub="ucfirst($user->role) . ' · ' . ($user->nip ?? $user->nis ?? $user->email)">
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.users.show', $user) }}">Lihat Detail</a>
    </x-page-head>

    <x-card title="Data Pengguna">
        <x-slot:actions><span class="card-hifi__meta">* wajib diisi</span></x-slot:actions>

        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="form-grid">
            @csrf
            @method('PUT')
            @include('admin.users.form')

            <div class="d-flex justify-content-between gap-2">
                @unless ($user->is(Auth::user()))
                    <button class="btn-hifi btn-hifi--danger" type="submit" form="hapusUser"
                            onclick="return confirm('Hapus akun {{ $user->name }}?')">Hapus Akun</button>
                @else
                    <span></span>
                @endunless

                <div class="d-flex gap-2">
                    <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.users.index') }}">Batal</a>
                    <button class="btn-hifi" type="submit">Simpan Perubahan</button>
                </div>
            </div>
        </form>
    </x-card>

    @unless ($user->is(Auth::user()))
        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" id="hapusUser" class="d-none">
            @csrf
            @method('DELETE')
        </form>
    @endunless
@endsection
