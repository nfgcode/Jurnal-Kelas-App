@extends('layouts.app')

@section('title', 'Pratinjau Impor')

@section('content')
    @php
        $ringkas = $hasil['ringkas'];
        $adaYangBisa = $ringkas['baru'] + $ringkas['perbarui'] > 0;
    @endphp

    <x-page-head
        :title="'Pratinjau Impor ' . ucfirst($jenis)"
        :sub="$namaAsli . ' · ' . $ringkas['total'] . ' baris terbaca'">
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.impor.index') }}">← Ulangi Unggah</a>
    </x-page-head>

    <div class="grid-row grid-row--4">
        <x-stat label="Total Baris" :value="$ringkas['total']" caption="baris berisi data" />
        <x-stat label="Akan Dibuat" :value="$ringkas['baru']" caption="akun baru" />
        <x-stat label="Akan Diperbarui" :value="$ringkas['perbarui']" caption="akun yang sudah ada" />
        <x-stat label="Bermasalah" :value="$ringkas['gagal']" caption="dilewati saat disimpan" />
    </div>

    @if ($ringkas['gagal'])
        <p class="banner banner--bahaya mb-2">
            <strong>{{ $ringkas['gagal'] }} baris</strong> masih bermasalah dan
            <strong>tidak akan disimpan</strong>. Perbaiki di berkas Excel lalu unggah ulang,
            atau lanjutkan untuk menyimpan baris yang sudah benar saja.
        </p>
    @endif

    <x-card title="Hasil Pemeriksaan per Baris" flush>
        <x-slot:actions>
            <span class="card-hifi__meta">Nomor baris mengikuti nomor baris di Excel</span>
        </x-slot:actions>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Baris</th>
                        {{-- "Hasil", not "Status": the template has a Status
                             column of its own, and two identical headers in one
                             table is a guessing game. --}}
                        <th>Hasil</th>
                        @foreach ($kolom as $kunci => $def)
                            @continue($kunci === 'password')
                            <th>{{ $def['judul'] }}</th>
                        @endforeach
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($hasil['baris'] as $baris)
                        @php
                            [$label, $tone] = match ($baris['aksi']) {
                                'baru' => ['Baru', 'green'],
                                'perbarui' => ['Perbarui', 'yellow'],
                                default => ['Gagal', 'red'],
                            };
                        @endphp
                        <tr>
                            <td class="is-muted">{{ $baris['nomor'] }}</td>
                            <td><x-chip :tone="$tone" :label="$label" /></td>
                            @foreach ($kolom as $kunci => $def)
                                @continue($kunci === 'password')
                                <td class="{{ $kunci === 'nama' ? 'is-strong' : 'is-muted' }}">
                                    {{ $baris['data'][$kunci] ?: '—' }}
                                </td>
                            @endforeach
                            <td class="is-muted">
                                @if ($baris['error'])
                                    <ul class="mb-0" style="padding-left: 16px">
                                        @foreach ($baris['error'] as $pesan)
                                            <li>{{ $pesan }}</li>
                                        @endforeach
                                    </ul>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($kolom) + 2 }}" class="empty-state">
                                Tidak ada baris data di berkas ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:foot>
            <span>{{ $ringkas['total'] }} baris diperiksa</span>

            <form method="POST" action="{{ route('admin.impor.simpan') }}">
                @csrf
                <input type="hidden" name="jenis" value="{{ $jenis }}">
                <input type="hidden" name="berkas" value="{{ $berkas }}">
                <input type="hidden" name="password_bawaan" value="{{ $passwordBawaan }}">
                <input type="hidden" name="perbarui" value="{{ $perbarui ? 1 : 0 }}">

                <button class="btn-hifi" type="submit" @disabled(! $adaYangBisa)>
                    Simpan {{ $ringkas['baru'] + $ringkas['perbarui'] }} Baris
                </button>
            </form>
        </x-slot:foot>
    </x-card>
@endsection
