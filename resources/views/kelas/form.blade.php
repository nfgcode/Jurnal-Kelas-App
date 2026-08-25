@php $kelas ??= null; @endphp

<div class="form-grid form-grid--2">
    <x-field label="Nama Kelas" name="nama_kelas" required>
        <input class="input-hifi" type="text" name="nama_kelas" id="nama_kelas"
               value="{{ old('nama_kelas', $kelas?->nama_kelas) }}" placeholder="mis. X IPA 1" required>
    </x-field>

    <x-field label="Tingkat" name="tingkat" required>
        <select class="select-hifi" name="tingkat" id="tingkat" required>
            @foreach (['X', 'XI', 'XII'] as $tingkat)
                <option value="{{ $tingkat }}" @selected(old('tingkat', $kelas?->tingkat) === $tingkat)>{{ $tingkat }}</option>
            @endforeach
        </select>
    </x-field>

    <x-field label="Jurusan" name="jurusan_kode" hint="Dipilih dari daftar jurusan sekolah, bukan diketik bebas.">
        <select class="select-hifi" name="jurusan_kode" id="jurusan_kode" data-searchable>
            <option value="">— Tanpa jurusan —</option>
            @foreach ($jurusanList as $jurusan)
                <option value="{{ $jurusan->kode }}" @selected(old('jurusan_kode', $kelas?->jurusan_kode) === $jurusan->kode)>
                    {{ $jurusan->label() }}
                </option>
            @endforeach
        </select>
    </x-field>

    <x-field label="Kelas Paralel Ke-" name="paralel" required
             hint="1 untuk rombel tunggal; 2 untuk &quot;AKL 2&quot;, dan seterusnya.">
        <input class="input-hifi" type="number" name="paralel" id="paralel" min="1" max="20"
               value="{{ old('paralel', $kelas?->paralel ?? 1) }}" required>
    </x-field>

    <x-field label="Ruang Kelas" name="ruangan_kode" hint="Ruang tempat rombel ini menetap.">
        <select class="select-hifi" name="ruangan_kode" id="ruangan_kode" data-searchable>
            <option value="">— Belum ditentukan —</option>
            @foreach ($ruanganList as $ruangan)
                <option value="{{ $ruangan->kode }}" @selected(old('ruangan_kode', $kelas?->ruangan_kode) === $ruangan->kode)>
                    {{ $ruangan->label() }} · {{ $ruangan->kapasitas }} kursi
                </option>
            @endforeach
        </select>
    </x-field>

    <x-field label="Kapasitas" name="kapasitas" required hint="Jumlah siswa ideal per rombel.">
        <input class="input-hifi" type="number" name="kapasitas" id="kapasitas" min="1" max="60"
               value="{{ old('kapasitas', $kelas?->kapasitas ?? 36) }}" required>
    </x-field>

    <x-field label="Tahun Ajaran" name="tahun_ajaran_kode" required>
        <select class="select-hifi" name="tahun_ajaran_kode" id="tahun_ajaran_kode" data-searchable required>
            @foreach ($tahunAjaranList as $tahun)
                <option value="{{ $tahun->kode }}"
                    @selected(old('tahun_ajaran_kode', $kelas?->tahun_ajaran_kode ?? $tahunBerjalan) === $tahun->kode)>
                    {{ $tahun->kode }}@if ($tahun->aktif) · berjalan @endif
                </option>
            @endforeach
        </select>
    </x-field>

    <x-field label="Ketua Kelas" name="ketua_nis"
             hint="Siswa yang boleh mengisi jurnal atas nama guru. Satu kelas satu ketua.">
        <select class="select-hifi" name="ketua_nis" id="ketua_nis" data-searchable>
            <option value="">Belum ditetapkan</option>
            @foreach ($siswaKelas ?? [] as $s)
                <option value="{{ $s->nis }}" @selected(old('ketua_nis', $kelas?->ketua_nis) === $s->nis)>
                    {{ $s->nama }} · {{ $s->nis }}
                </option>
            @endforeach
        </select>
    </x-field>

    <x-field label="Wali Kelas" name="wali_kelas_nip">
        <select class="select-hifi" name="wali_kelas_nip" id="wali_kelas_nip" data-searchable>
            <option value="">Belum ditetapkan</option>
            @foreach ($gurus as $guru)
                <option value="{{ $guru->nip }}" @selected(old('wali_kelas_nip', $kelas?->wali_kelas_nip) == $guru->nip)>{{ $guru->nama }}</option>
            @endforeach
        </select>
    </x-field>
</div>
