@php $ruangan ??= null; @endphp

<div class="form-grid form-grid--2">
    <x-field label="Kode Ruangan" name="kode" required hint="Kode yang tertempel di pintu, mis. R-101 atau LAB-RPL-1.">
        <input class="input-hifi" type="text" name="kode" id="kode"
               value="{{ old('kode', $ruangan?->kode) }}" placeholder="R-101" required>
    </x-field>

    <x-field label="Nama Ruangan" name="nama" required>
        <input class="input-hifi" type="text" name="nama" id="nama"
               value="{{ old('nama', $ruangan?->nama) }}" placeholder="mis. Lab Komputer 1" required>
    </x-field>

    <x-field label="Jenis" name="jenis" required>
        <select class="select-hifi" name="jenis" id="jenis" data-searchable required>
            @foreach (\App\Models\Ruangan::JENIS as $value => $label)
                <option value="{{ $value }}" @selected(old('jenis', $ruangan?->jenis ?? 'kelas') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </x-field>

    <x-field label="Kapasitas" name="kapasitas" required hint="Jumlah kursi.">
        <input class="input-hifi" type="number" name="kapasitas" id="kapasitas" min="1" max="200"
               value="{{ old('kapasitas', $ruangan?->kapasitas ?? 36) }}" required>
    </x-field>

    <x-field label="Gedung" name="gedung">
        <input class="input-hifi" type="text" name="gedung" id="gedung"
               value="{{ old('gedung', $ruangan?->gedung) }}" placeholder="mis. Gedung A">
    </x-field>

    <x-field label="Lantai" name="lantai">
        <input class="input-hifi" type="number" name="lantai" id="lantai" min="1" max="10"
               value="{{ old('lantai', $ruangan?->lantai) }}" placeholder="1">
    </x-field>

    <x-field label="Status" name="status" required
             hint="Ruangan yang sedang diperbaiki tetap tercatat, tapi ditandai agar tidak dijadwalkan.">
        <select class="select-hifi" name="status" id="status" required>
            @foreach (['aktif' => 'Aktif', 'perbaikan' => 'Perbaikan', 'nonaktif' => 'Nonaktif'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $ruangan?->status ?? 'aktif') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </x-field>
</div>

<x-field label="Keterangan" name="keterangan">
    <textarea class="input-hifi" name="keterangan" id="keterangan" style="min-height: 70px"
              placeholder="Fasilitas atau catatan, mis. 20 PC + proyektor.">{{ old('keterangan', $ruangan?->keterangan) }}</textarea>
</x-field>
