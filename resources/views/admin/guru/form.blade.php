@php $guru ??= null; @endphp

<div class="form-grid form-grid--2">
    <x-field label="NIP" name="nip" :required="! $guru"
             hint="Nomor Induk Pegawai — kunci utama data ini, tidak bisa diubah setelah dibuat.">
        <input class="input-hifi" type="text" name="nip" id="nip" inputmode="numeric"
               value="{{ old('nip', $guru?->nip) }}" placeholder="198501012010011001"
               @if ($guru) readonly @else required @endif>
    </x-field>

    <x-field label="Nama Lengkap" name="nama" required hint="Termasuk gelar bila dipakai, mis. Budi Santoso, S.Pd.">
        <input class="input-hifi" type="text" name="nama" id="nama"
               value="{{ old('nama', $guru?->nama) }}" placeholder="mis. Budi Santoso, S.Pd." required>
    </x-field>

    <x-field label="Jenis Kelamin" name="jenis_kelamin">
        <select class="select-hifi" name="jenis_kelamin" id="jenis_kelamin">
            <option value="">— Tidak dicatat —</option>
            @foreach (['L' => 'Laki-laki', 'P' => 'Perempuan'] as $value => $label)
                <option value="{{ $value }}" @selected(old('jenis_kelamin', $guru?->jenis_kelamin) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </x-field>

    <x-field label="Status" name="status" required
             hint="Guru nonaktif tidak bisa masuk dan tidak ditawarkan saat menyusun jadwal.">
        <select class="select-hifi" name="status" id="status" required>
            @foreach (['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $guru?->status ?? 'aktif') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </x-field>

    <x-field label="No. HP" name="no_hp">
        <input class="input-hifi" type="text" name="no_hp" id="no_hp"
               value="{{ old('no_hp', $guru?->no_hp) }}" placeholder="08...">
    </x-field>

    <x-field label="Alamat" name="alamat">
        <input class="input-hifi" type="text" name="alamat" id="alamat" value="{{ old('alamat', $guru?->alamat) }}">
    </x-field>
</div>

<div class="sidebar__section mt-3">Mata Pelajaran yang Diampu</div>

<p class="field__hint mb-2">
    <x-ikon nama="info-circle" />
    Seorang guru boleh mengampu satu sampai beberapa mapel, dan satu mapel boleh
    dipegang beberapa guru. Jadwal hanya bisa memasangkan guru dengan mapel yang tercentang di sini.
</p>

@php $mapelTerpilih = old('mata_pelajaran_id', $guru?->mataPelajaran->pluck('id')->all() ?? []); @endphp

<div class="form-grid form-grid--3">
    @foreach ($mapelList as $mapel)
        <label class="pref-toggle">
            <input type="checkbox" name="mata_pelajaran_id[]" value="{{ $mapel->id }}"
                   @checked(in_array($mapel->id, (array) $mapelTerpilih))>
            <span>{{ $mapel->nama }} <span class="is-muted">· {{ $mapel->kode }}</span></span>
        </label>
    @endforeach
</div>

<x-field label="Mapel Utama" name="mapel_utama" hint="Mapel yang jadi tanggung jawab utamanya. Opsional.">
    <select class="select-hifi" name="mapel_utama" id="mapel_utama" data-searchable>
        <option value="">— Tidak ditentukan —</option>
        @foreach ($mapelList as $mapel)
            <option value="{{ $mapel->id }}"
                @selected(old('mapel_utama', $guru?->mataPelajaran->firstWhere('pivot.utama', true)?->id) == $mapel->id)>
                {{ $mapel->nama }}
            </option>
        @endforeach
    </select>
</x-field>

<div class="sidebar__section mt-3">Perwalian Kelas</div>

@php $waliTerpilih = old('kelas_wali', $guru ? $kelasList->where('wali_kelas_nip', $guru->nip)->pluck('id')->all() : []); @endphp

<div class="form-grid form-grid--3">
    @foreach ($kelasList as $kelas)
        @php $waliLain = $kelas->wali_kelas_nip && $kelas->wali_kelas_nip !== $guru?->nip ? $kelas->waliKelas?->nama : null; @endphp
        <label class="pref-toggle">
            <input type="checkbox" name="kelas_wali[]" value="{{ $kelas->id }}"
                   @checked(in_array($kelas->id, (array) $waliTerpilih))>
            <span>
                {{ $kelas->nama_kelas }}
                @if ($waliLain)
                    <span class="is-muted">· kini {{ $waliLain }}</span>
                @endif
            </span>
        </label>
    @endforeach
</div>

<div class="sidebar__section mt-3">Akun untuk Masuk</div>

<p class="field__hint mb-2">
    <x-ikon nama="shield-lock" />
    @if ($guru)
        Mengubah kredensial di sini langsung memperbarui akun guru ini. Kosongkan kata sandi bila tidak ingin menggantinya.
    @else
        Data guru dan akunnya dibuat sekaligus dalam satu transaksi — bila salah satunya gagal, keduanya dibatalkan.
    @endif
</p>

<div class="form-grid form-grid--2">
    <x-field label="Username" name="username" required hint="Dipakai untuk masuk. Huruf, angka, titik, garis bawah, tanda hubung.">
        <input class="input-hifi" type="text" name="username" id="username"
               value="{{ old('username', $guru?->akun?->username) }}" placeholder="mis. budi.santoso" required>
    </x-field>

    <x-field label="Email" name="email" required>
        <input class="input-hifi" type="email" name="email" id="email"
               value="{{ old('email', $guru?->akun?->email) }}" placeholder="nama@sekolah.sch.id" required>
    </x-field>

    <x-field label="Kata Sandi" name="password" :required="! $guru"
             :hint="$guru ? 'Kosongkan bila tidak diganti.' : 'Minimal 8 karakter.'">
        <input class="input-hifi" type="password" name="password" id="password"
               autocomplete="new-password" @if (! $guru) required @endif>
    </x-field>

    @unless ($guru)
        <x-field label="Nama Kembar" name="izinkan_nama_sama"
                 hint="Sistem menolak nama guru yang sudah ada. Centang hanya bila memang dua orang berbeda.">
            <label class="pref-toggle">
                <input type="checkbox" name="izinkan_nama_sama" value="1" @checked(old('izinkan_nama_sama'))>
                <span>Izinkan nama sama</span>
            </label>
        </x-field>
    @endunless
</div>
