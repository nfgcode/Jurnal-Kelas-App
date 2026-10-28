@extends('layouts.app')

@section('title', 'Tahun Ajaran')

@section('content')
    <x-page-head
        title="Tahun Ajaran"
        sub="Kelola periode tahun ajaran dan status tahun ajaran aktif.">
        @if ($tahunAktif)
            <div class="d-inline-flex align-items-center gap-2">
                <span class="field__label mb-0">Tahun Berjalan:</span>
                <x-chip tone="green" :label="$tahunAktif->kode . ' (Aktif)'" />
            </div>
        @else
            <x-chip tone="neutral" label="Belum Ada Tahun Aktif" />
        @endif
    </x-page-head>

    <div class="grid-row grid-row--2 mb-4 align-items-start">
        {{-- 1. Daftar Tahun Ajaran --}}
        <x-card title="Daftar Tahun Ajaran" flush>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Periode</th>
                            <th class="is-num">Rombel</th>
                            <th class="is-num">Status</th>
                            <th class="is-num">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tahunList as $th)
                            <tr>
                                <td class="is-strong">{{ $th->kode }}</td>
                                <td class="is-muted">
                                    {{ $th->mulai ? $th->mulai->format('d/m/Y') : '—' }} s/d {{ $th->selesai ? $th->selesai->format('d/m/Y') : '—' }}
                                </td>
                                <td class="is-num">{{ $th->kelas_count }}</td>
                                <td class="is-num">
                                    <x-chip :tone="$th->aktif ? 'green' : 'neutral'" :label="$th->aktif ? 'Aktif' : 'Nonaktif'" />
                                </td>
                                <td class="is-num tbl__aksi">
                                    @if (! $th->aktif)
                                        <form method="POST" action="{{ route('admin.tahun-ajaran.aktifkan', $th) }}" class="d-inline"
                                              onsubmit="return confirm('Apakah Anda yakin ingin mengaktifkan tahun ajaran {{ $th->kode }}? Tahun ajaran aktif saat ini akan dinonaktifkan.');">
                                            @csrf
                                            <button type="submit" class="btn-hifi btn-hifi--ghost btn-hifi--sm">Aktifkan</button>
                                        </form>
                                    @else
                                        <span class="text-success small fw-semibold">Aktif</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="empty-state">Belum ada data tahun ajaran.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        {{-- 2. Formulir Tambah Tahun Ajaran Baru --}}
        <x-card title="Tambah Tahun Ajaran Baru">
            <p class="field__hint mb-3">
                Buat tahun ajaran baru (contoh: <code>2027/2028</code>). Tahun ajaran baru berstatus <strong>nonaktif secara bawaan</strong> sehingga tidak mengganggu kegiatan tahun ajaran yang sedang berjalan.
            </p>

            <form method="POST" action="{{ route('admin.tahun-ajaran.store') }}" class="form-grid">
                @csrf
                <x-field label="Kode Tahun Ajaran" name="kode" required hint="Format: YYYY/YYYY (contoh: 2027/2028)">
                    <input class="input-hifi" type="text" name="kode" placeholder="2027/2028" required pattern="\d{4}/\d{4}" value="{{ old('kode') }}">
                </x-field>

                <div class="form-grid form-grid--2">
                    <x-field label="Tanggal Mulai" name="mulai" hint="Format: dd/mm/yyyy (opsional)">
                        <input class="input-hifi" type="date" name="mulai" value="{{ old('mulai') }}">
                    </x-field>
                    <x-field label="Tanggal Selesai" name="selesai" hint="Format: dd/mm/yyyy (opsional)">
                        <input class="input-hifi" type="date" name="selesai" value="{{ old('selesai') }}">
                    </x-field>
                </div>

                <div class="d-flex justify-content-end mt-3">
                    <button class="btn-hifi" type="submit">+ Simpan Tahun Ajaran</button>
                </div>
            </form>
        </x-card>
    </div>
@endsection
