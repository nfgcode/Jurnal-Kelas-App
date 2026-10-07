<div class="form-grid form-grid--2">
    <x-field label="Kode Jurusan *" name="kode" hint="Maksimal 20 karakter tanpa whitespace. Disimpan kapital.">
        <input class="input-hifi" id="kode" name="kode" value="{{ old('kode', $jurusan?->kode) }}" maxlength="20" required>
    </x-field>
    <x-field label="Nama Jurusan *" name="nama">
        <input class="input-hifi" id="nama" name="nama" value="{{ old('nama', $jurusan?->nama) }}" maxlength="255" required>
    </x-field>
    <x-field label="Bidang" name="bidang">
        <input class="input-hifi" id="bidang" name="bidang" value="{{ old('bidang', $jurusan?->bidang) }}" maxlength="255">
    </x-field>
    <x-field label="Status *" name="aktif">
        <select class="select-hifi" id="aktif" name="aktif" required>
            <option value="1" @selected((string) old('aktif', $jurusan?->aktif ?? true) === '1')>Aktif</option>
            <option value="0" @selected((string) old('aktif', $jurusan ? (int) $jurusan->aktif : 1) === '0')>Nonaktif</option>
        </select>
    </x-field>
</div>
<x-field label="Deskripsi" name="deskripsi">
    <textarea class="input-hifi" id="deskripsi" name="deskripsi" rows="4">{{ old('deskripsi', $jurusan?->deskripsi) }}</textarea>
</x-field>
