@php $mataPelajaran ??= null; @endphp

<div class="form-grid form-grid--2">
    <x-field label="Nama Mata Pelajaran" name="nama" required>
        <input class="input-hifi" type="text" name="nama" id="nama"
               value="{{ old('nama', $mataPelajaran?->nama) }}" placeholder="mis. Matematika" required>
    </x-field>

    <x-field label="Kode" name="kode" required hint="Singkatan unik, mis. MTK.">
        <input class="input-hifi" type="text" name="kode" id="kode"
               value="{{ old('kode', $mataPelajaran?->kode) }}" placeholder="MTK" required>
    </x-field>

    <x-field label="Kelompok" name="kelompok" required>
        <select class="select-hifi" name="kelompok" id="kelompok" required>
            @foreach (['wajib' => 'Wajib', 'peminatan' => 'Peminatan', 'muatan_lokal' => 'Muatan Lokal', 'kejuruan' => 'Kejuruan'] as $value => $label)
                <option value="{{ $value }}" @selected(old('kelompok', $mataPelajaran?->kelompok ?? 'wajib') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </x-field>

    <x-field label="JP per Minggu" name="jp_per_minggu" required hint="Jam pelajaran per rombel per minggu.">
        <input class="input-hifi" type="number" name="jp_per_minggu" id="jp_per_minggu" min="1" max="12"
               value="{{ old('jp_per_minggu', $mataPelajaran?->jp_per_minggu ?? 2) }}" required>
    </x-field>
</div>

<x-field label="Deskripsi" name="deskripsi">
    <textarea class="input-hifi" name="deskripsi" id="deskripsi" style="min-height: 70px"
              placeholder="Keterangan singkat tentang mata pelajaran ini.">{{ old('deskripsi', $mataPelajaran?->deskripsi) }}</textarea>
</x-field>
