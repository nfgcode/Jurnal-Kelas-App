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

            <div class="sidebar__section">Guru Pengampu</div>

            <p class="field__hint mb-2">
                <x-ikon nama="info-circle" />
                Mata pelajaran dan guru pengampunya disimpan sekaligus dalam satu transaksi.
                Boleh dikosongkan sekarang dan diisi nanti dari halaman mata pelajaran ini.
            </p>

            <div class="form-grid form-grid--3">
                @foreach ($guruList as $guru)
                    <label class="pref-toggle">
                        <input type="checkbox" name="guru_nip[]" value="{{ $guru->nip }}"
                               @checked(in_array($guru->nip, (array) old('guru_nip', []), true))>
                        <span>{{ $guru->nama }} <span class="is-muted">· {{ $guru->nip }}</span></span>
                    </label>
                @endforeach
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a class="btn-hifi btn-hifi--ghost" href="{{ route('mata-pelajaran.index') }}">Batal</a>
                <button class="btn-hifi" type="submit">Simpan Mata Pelajaran</button>
            </div>
        </form>
    </x-card>
@endsection
