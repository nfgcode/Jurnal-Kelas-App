@extends('layouts.app')

@section('title', 'Jurusan')

{{--
    Expects: $jurusan (Jurusan, loadCount(['kelas', 'siswa'])) and $bidangList.
    Puts to admin.jurusan.update; deletes through admin.jurusan.destroy, which is
    only offered while no kelas or siswa uses the jurusan.
--}}

@section('content')
    @php
        $dipakaiKelas = (int) ($jurusan->kelas_count ?? 0);
        $dipakaiSiswa = (int) ($jurusan->siswa_count ?? 0);
        $dipakai = $dipakaiKelas + $dipakaiSiswa > 0;
    @endphp

    <x-page-head :title="'Ubah Jurusan ' . $jurusan->kode" :sub="$jurusan->nama">
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.jurusan.index') }}">Kembali</a>
    </x-page-head>

    <x-card title="Data Jurusan">
        <x-slot:actions>
            <span class="card-hifi__meta">
                Dipakai {{ $dipakaiKelas }} kelas · {{ number_format($dipakaiSiswa, 0, ',', '.') }} siswa
            </span>
        </x-slot:actions>

        <form method="POST" action="{{ route('admin.jurusan.update', $jurusan) }}" class="form-grid">
            @csrf
            @method('PUT')
            @include('admin.jurusan.form')

            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    @if ($dipakai)
                        <button class="btn-hifi btn-hifi--danger" type="button" disabled
                                aria-describedby="alasanTidakBisaHapus">Hapus</button>
                        <span class="field__hint" id="alasanTidakBisaHapus">
                            Masih dipakai {{ $dipakaiKelas }} kelas dan {{ $dipakaiSiswa }} siswa.
                            Nonaktifkan saja bila jurusan ini tidak dibuka lagi.
                        </span>
                    @else
                        <button class="btn-hifi btn-hifi--danger" type="submit" form="hapusJurusan"
                                data-konfirmasi="Hapus jurusan {{ $jurusan->kode }} — {{ $jurusan->nama }}?">Hapus</button>
                    @endif
                </div>

                <div class="d-flex gap-2">
                    <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.jurusan.index') }}">Batal</a>
                    <button class="btn-hifi" type="submit">Simpan Perubahan</button>
                </div>
            </div>
        </form>
    </x-card>

    @unless ($dipakai)
        <form method="POST" action="{{ route('admin.jurusan.destroy', $jurusan) }}" id="hapusJurusan" class="d-none">
            @csrf
            @method('DELETE')
        </form>
    @endunless
@endsection
