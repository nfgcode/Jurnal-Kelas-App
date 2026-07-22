@extends('layouts.app')

@section('title', 'Jadwal')

@section('content')
    <x-page-head
        title="Ubah Jadwal"
        :sub="collect([$jadwal->kelas?->nama_kelas, $jadwal->mataPelajaran?->nama, $jadwal->hari . ' JP ' . $jadwal->jpLabel()])->filter()->join(' · ')">
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('jadwal.show', $jadwal) }}">Lihat Detail</a>
    </x-page-head>

    <x-card title="Data Jadwal">
        <x-slot:actions><span class="card-hifi__meta">* wajib diisi</span></x-slot:actions>

        <form method="POST" action="{{ route('jadwal.update', $jadwal) }}" class="form-grid">
            @csrf
            @method('PUT')
            @include('jadwal.form')

            <div class="d-flex justify-content-between gap-2">
                <button class="btn-hifi btn-hifi--danger" type="submit" form="hapusJadwal"
                        onclick="return confirm('Hapus jadwal ini?')">Hapus Jadwal</button>

                <div class="d-flex gap-2">
                    <a class="btn-hifi btn-hifi--ghost" href="{{ route('jadwal.index') }}">Batal</a>
                    <button class="btn-hifi" type="submit">Simpan Perubahan</button>
                </div>
            </div>
        </form>
    </x-card>

    <form method="POST" action="{{ route('jadwal.destroy', $jadwal) }}" id="hapusJadwal" class="d-none">
        @csrf
        @method('DELETE')
    </form>
@endsection
