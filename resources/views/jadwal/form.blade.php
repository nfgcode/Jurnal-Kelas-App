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

    <x-field label="Guru Pengajar" name="guru_nip" required>
        <select class="select-hifi" name="guru_nip" id="guru_nip" data-searchable required>
            @foreach ($gurus as $guru)
                <option value="{{ $guru->nip }}" @selected(old('guru_nip', $jadwal?->guru_nip) == $guru->nip)>{{ $guru->nama }}</option>
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

    <x-field label="Jam Ke (Mulai)" name="jam_ke_mulai" required hint="Nomor jam pelajaran. Waktunya dihitung otomatis.">
        <select class="select-hifi" name="jam_ke_mulai" id="jam_ke_mulai" data-searchable required>
            @foreach ($jpList as $jp)
                <option value="{{ $jp['jam_ke'] }}" @selected(old('jam_ke_mulai', $jadwal?->jam_ke_mulai ?? 1) == $jp['jam_ke'])>
                    JP {{ $jp['jam_ke'] }} — {{ $jp['mulai'] }}
                </option>
            @endforeach
        </select>
    </x-field>

    <x-field label="Jam Ke (Selesai)" name="jam_ke_selesai" required
             hint="1 JP = {{ $jpDurasi }} menit.">
        <select class="select-hifi" name="jam_ke_selesai" id="jam_ke_selesai" data-searchable required>
            @foreach ($jpList as $jp)
                <option value="{{ $jp['jam_ke'] }}" @selected(old('jam_ke_selesai', $jadwal?->jam_ke_selesai ?? 2) == $jp['jam_ke'])>
                    JP {{ $jp['jam_ke'] }} — {{ $jp['selesai'] }}
                </option>
            @endforeach
        </select>
    </x-field>

    <x-field label="Ruangan" name="ruangan_kode" hint="Kosongkan bila memakai ruang kelas sendiri.">
        <select class="select-hifi" name="ruangan_kode" id="ruangan_kode" data-searchable>
            <option value="">— Ruang kelas sendiri —</option>
            @foreach ($ruanganList as $ruangan)
                <option value="{{ $ruangan->kode }}" @selected(old('ruangan_kode', $jadwal?->ruangan_kode) === $ruangan->kode)>
                    {{ $ruangan->label() }}
                </option>
            @endforeach
        </select>
    </x-field>
</div>

<p class="field__hint mt-2">
    <x-ikon nama="info-circle" />
    Waktu mulai dan selesai tidak diisi manual — sistem menghitungnya dari nomor JP
    ({{ $jpDurasi }} menit per JP, bel pertama {{ $jpBelPertama }}@foreach ($jpIstirahat as $setelah => $lama), istirahat {{ $lama }} menit setelah JP {{ $setelah }}@endforeach).
</p>
