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

    <x-field label="Jurusan" name="jurusan">
        <input class="input-hifi" type="text" name="jurusan" id="jurusan"
               value="{{ old('jurusan', $kelas?->jurusan) }}" placeholder="mis. IPA, IPS, TKJ">
    </x-field>

    <x-field label="Ruang" name="ruang">
        <input class="input-hifi" type="text" name="ruang" id="ruang"
               value="{{ old('ruang', $kelas?->ruang) }}" placeholder="mis. R-101">
    </x-field>

    <x-field label="Kapasitas" name="kapasitas" required hint="Jumlah siswa ideal per rombel.">
        <input class="input-hifi" type="number" name="kapasitas" id="kapasitas" min="1" max="60"
               value="{{ old('kapasitas', $kelas?->kapasitas ?? 36) }}" required>
    </x-field>

    <x-field label="Tahun Ajaran" name="tahun_ajaran" required>
        <input class="input-hifi" type="text" name="tahun_ajaran" id="tahun_ajaran"
               value="{{ old('tahun_ajaran', $kelas?->tahun_ajaran ?? now()->year . '/' . (now()->year + 1)) }}" required>
    </x-field>

    <x-field label="Wali Kelas" name="wali_kelas_id">
        <select class="select-hifi" name="wali_kelas_id" id="wali_kelas_id">
            <option value="">Belum ditetapkan</option>
            @foreach ($gurus as $guru)
                <option value="{{ $guru->id }}" @selected(old('wali_kelas_id', $kelas?->wali_kelas_id) == $guru->id)>{{ $guru->name }}</option>
            @endforeach
        </select>
    </x-field>
</div>
