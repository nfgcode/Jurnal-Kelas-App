@php $jadwal ??= null; @endphp

<div class="form-grid form-grid--3">
    <x-field label="Kelas" name="kelas_id" required>
        <select class="select-hifi" name="kelas_id" id="kelas_id" data-searchable required>
            @foreach ($kelasList as $kelas)
                <option value="{{ $kelas->id }}" @selected(old('kelas_id', $jadwal?->kelas_id) == $kelas->id)>{{ $kelas->nama_kelas }}</option>
            @endforeach
        </select>
    </x-field>

    <x-field label="Mata Pelajaran" name="mata_pelajaran_id" required>
        <select class="select-hifi" name="mata_pelajaran_id" id="mata_pelajaran_id" data-searchable required>
            @foreach ($mataPelajaranList as $mapel)
                <option value="{{ $mapel->id }}" @selected(old('mata_pelajaran_id', $jadwal?->mata_pelajaran_id) == $mapel->id)>{{ $mapel->nama }}</option>
            @endforeach
        </select>
    </x-field>

    <x-field label="Guru Pengajar" name="guru_id" required>
        <select class="select-hifi" name="guru_id" id="guru_id" data-searchable required>
            @foreach ($gurus as $guru)
                <option value="{{ $guru->id }}" @selected(old('guru_id', $jadwal?->guru_id) == $guru->id)>{{ $guru->name }}</option>
            @endforeach
        </select>
    </x-field>

    <x-field label="Hari" name="hari" required>
        <select class="select-hifi" name="hari" id="hari" required>
            @foreach (['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'] as $hari)
                <option value="{{ $hari }}" @selected(old('hari', $jadwal?->hari) === $hari)>{{ $hari }}</option>
            @endforeach
        </select>
    </x-field>

    <x-field label="Jam Ke (Mulai)" name="jam_ke_mulai" required hint="Nomor jam pelajaran, bukan waktu.">
        <input class="input-hifi" type="number" name="jam_ke_mulai" id="jam_ke_mulai" min="1" max="12"
               value="{{ old('jam_ke_mulai', $jadwal?->jam_ke_mulai ?? 1) }}" required>
    </x-field>

    <x-field label="Jam Ke (Selesai)" name="jam_ke_selesai" required>
        <input class="input-hifi" type="number" name="jam_ke_selesai" id="jam_ke_selesai" min="1" max="12"
               value="{{ old('jam_ke_selesai', $jadwal?->jam_ke_selesai ?? 2) }}" required>
    </x-field>

    <x-field label="Waktu Mulai" name="jam_mulai" required>
        <input class="input-hifi" type="time" name="jam_mulai" id="jam_mulai"
               value="{{ old('jam_mulai', $jadwal ? substr($jadwal->jam_mulai, 0, 5) : '07:00') }}" required>
    </x-field>

    <x-field label="Waktu Selesai" name="jam_selesai" required>
        <input class="input-hifi" type="time" name="jam_selesai" id="jam_selesai"
               value="{{ old('jam_selesai', $jadwal ? substr($jadwal->jam_selesai, 0, 5) : '08:30') }}" required>
    </x-field>

    <x-field label="Ruang" name="ruang">
        <input class="input-hifi" type="text" name="ruang" id="ruang"
               value="{{ old('ruang', $jadwal?->ruang) }}" placeholder="mis. R-101 atau Lab Kimia">
    </x-field>
</div>
