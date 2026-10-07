{{--
    Shared fields of "Tambah Jurusan" and "Ubah Jurusan".
      $jurusan     ?Jurusan — null on create
      $bidangList  Collection<string> — suggestions for the bidang field
--}}
@php $jurusan ??= null; @endphp

<div class="form-grid form-grid--2">
    <x-field label="Kode" name="kode" required
             :hint="$jurusan
                 ? 'Mengubah kode ikut mengganti kode di kelas dan siswa yang memakainya.'
                 : 'Singkatan yang dipakai sekolah, mis. TKJ. Maks. 20 karakter, tanpa spasi.'">
        <input class="input-hifi" type="text" name="kode" id="kode" maxlength="20" required
               style="text-transform: uppercase" autocomplete="off"
               value="{{ old('kode', $jurusan?->kode) }}" placeholder="TKJ">
    </x-field>

    <x-field label="Nama Jurusan" name="nama" required>
        <input class="input-hifi" type="text" name="nama" id="nama" maxlength="255" required
               value="{{ old('nama', $jurusan?->nama) }}" placeholder="mis. Teknik Komputer dan Jaringan">
    </x-field>

    <x-field label="Bidang Keahlian" name="bidang"
             hint="Kelompok besar jurusan, mis. Teknologi Informasi. Pilih yang sudah ada atau ketik baru; boleh dikosongkan.">
        <input class="input-hifi" type="text" name="bidang" id="bidang" maxlength="255" list="daftarBidang"
               value="{{ old('bidang', $jurusan?->bidang) }}" placeholder="mis. Teknologi Informasi">
        <datalist id="daftarBidang">
            @foreach ($bidangList as $bidang)
                <option value="{{ $bidang }}"></option>
            @endforeach
        </datalist>
    </x-field>

    <x-field label="Status" name="aktif"
             hint="Jurusan nonaktif tidak muncul di pilihan jurusan saat menambah atau mengubah kelas.">
        {{-- Unchecked boxes are not posted, so the hidden 0 is what says "nonaktif". --}}
        <input type="hidden" name="aktif" value="0">
        <label class="checkbox-row">
            <input type="checkbox" name="aktif" id="aktif" value="1"
                   @checked((bool) old('aktif', $jurusan?->aktif ?? true))>
            Aktif — dibuka untuk kelas dan siswa
        </label>
    </x-field>
</div>

<x-field label="Deskripsi" name="deskripsi">
    <textarea class="input-hifi" name="deskripsi" id="deskripsi" style="min-height: 70px"
              placeholder="Keterangan singkat tentang jurusan ini (opsional).">{{ old('deskripsi', $jurusan?->deskripsi) }}</textarea>
</x-field>
