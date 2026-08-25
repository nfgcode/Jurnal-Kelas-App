@extends('layouts.app')

@section('title', 'Data Siswa')

@section('content')
    <x-page-head :title="'Ubah ' . $siswa->nama" :sub="'NIS ' . $siswa->nis . ' · ' . ($siswa->kelas?->nama_kelas ?? 'belum dikelaskan')">
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.siswa.show', $siswa) }}">Lihat Detail</a>
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.siswa.index') }}">Kembali</a>
    </x-page-head>

    <x-card title="Data Siswa">
        <x-slot:actions><span class="card-hifi__meta">* wajib diisi</span></x-slot:actions>

        <form method="POST" action="{{ route('admin.siswa.update', $siswa) }}" class="form-grid">
            @csrf
            @method('PUT')
            @include('admin.siswa.form')

            <div class="d-flex justify-content-between gap-2">
                <button class="btn-hifi btn-hifi--ghost" type="submit" form="hapusSiswa"
                        onclick="return confirm('Hapus {{ $siswa->nama }} beserta akunnya?')">Hapus Siswa</button>
                <span class="d-flex gap-2">
                    <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.siswa.index') }}">Batal</a>
                    <button class="btn-hifi" type="submit">Simpan Perubahan</button>
                </span>
            </div>
        </form>

        <form method="POST" action="{{ route('admin.siswa.destroy', $siswa) }}" id="hapusSiswa" class="d-none">
            @csrf
            @method('DELETE')
        </form>
    </x-card>
@endsection
