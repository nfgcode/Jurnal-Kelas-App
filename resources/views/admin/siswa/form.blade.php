@php $siswa ??= null; @endphp

<div class="form-grid form-grid--2">
    <x-field label="NIS" name="nis" :required="! $siswa"
             hint="Nomor Induk Siswa — kunci utama data ini, tidak bisa diubah setelah dibuat.">
        <input class="input-hifi" type="text" name="nis" id="nis" inputmode="numeric"
               value="{{ old('nis', $siswa?->nis) }}" placeholder="20260001"
               @if ($siswa) readonly @else required @endif>
    </x-field>

    <x-field label="NISN" name="nisn" hint="Nomor Induk Siswa Nasional. Boleh dikosongkan.">
        <input class="input-hifi" type="text" name="nisn" id="nisn" inputmode="numeric"
               value="{{ old('nisn', $siswa?->nisn) }}" placeholder="0071234567">
    </x-field>

    <x-field label="Nama Lengkap" name="nama" required>
        <input class="input-hifi" type="text" name="nama" id="nama"
               value="{{ old('nama', $siswa?->nama) }}" placeholder="mis. Ahmad Fauzi" required>
    </x-field>

    <x-field label="Jenis Kelamin" name="jenis_kelamin">
        <select class="select-hifi" name="jenis_kelamin" id="jenis_kelamin">
            <option value="">— Tidak dicatat —</option>
            @foreach (['L' => 'Laki-laki', 'P' => 'Perempuan'] as $value => $label)
                <option value="{{ $value }}" @selected(old('jenis_kelamin', $siswa?->jenis_kelamin) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </x-field>

    <x-field label="Kelas" name="kelas_id" hint="Siswa tanpa kelas tidak muncul di presensi manapun.">
        <select class="select-hifi" name="kelas_id" id="kelas_id" data-searchable>
            <option value="">— Belum dikelaskan —</option>
            @foreach ($kelasList as $kelas)
                <option value="{{ $kelas->id }}" @selected(old('kelas_id', $siswa?->kelas_id) == $kelas->id)>{{ $kelas->nama_kelas }}</option>
            @endforeach
        </select>
    </x-field>

    <x-field label="Status" name="status" required>
        <select class="select-hifi" name="status" id="status" required>
            @foreach (['aktif' => 'Aktif', 'lulus' => 'Lulus', 'nonaktif' => 'Nonaktif'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $siswa?->status ?? 'aktif') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </x-field>

    <x-field label="No. HP" name="no_hp">
        <input class="input-hifi" type="text" name="no_hp" id="no_hp"
               value="{{ old('no_hp', $siswa?->no_hp) }}" placeholder="08...">
    </x-field>

    <x-field label="Ketua Kelas" name="is_ketua_kelas"
             hint="Ketua kelas boleh mengisi jurnal atas nama guru. Satu kelas satu ketua — menunjuk yang baru otomatis melepas yang lama.">
        <label class="pref-toggle">
            <input type="hidden" name="is_ketua_kelas" value="0">
            <input type="checkbox" name="is_ketua_kelas" id="is_ketua_kelas" value="1"
                   @checked(old('is_ketua_kelas', $siswa?->is_ketua_kelas))>
            <span>Jadikan ketua kelas</span>
        </label>
    </x-field>
</div>

<x-field label="Alamat" name="alamat">
    <textarea class="input-hifi" name="alamat" id="alamat" style="min-height: 60px">{{ old('alamat', $siswa?->alamat) }}</textarea>
</x-field>

<div class="sidebar__section mt-3">Akun untuk Masuk</div>

<p class="field__hint mb-2">
    <x-ikon nama="shield-lock" />
    @if ($siswa)
        Mengubah kredensial di sini langsung memperbarui akun siswa ini. Kosongkan kata sandi bila tidak ingin menggantinya.
    @else
        Data siswa dan akunnya dibuat sekaligus dalam satu transaksi — bila salah satunya gagal, keduanya dibatalkan.
    @endif
</p>

<div class="form-grid form-grid--2">
    <x-field label="Username" name="username" required hint="Dipakai untuk masuk. Huruf, angka, titik, garis bawah, tanda hubung.">
        <input class="input-hifi" type="text" name="username" id="username"
               value="{{ old('username', $siswa?->akun?->username) }}" placeholder="mis. ahmad.fauzi" required>
    </x-field>

    <x-field label="Email" name="email" required>
        <input class="input-hifi" type="email" name="email" id="email"
               value="{{ old('email', $siswa?->akun?->email) }}" placeholder="nama@sekolah.sch.id" required>
    </x-field>

    <x-field label="Kata Sandi" name="password" :required="! $siswa"
             :hint="$siswa ? 'Kosongkan bila tidak diganti.' : 'Minimal 8 karakter.'">
        <input class="input-hifi" type="password" name="password" id="password"
               autocomplete="new-password" @if (! $siswa) required @endif>
    </x-field>

    @unless ($siswa)
        <x-field label="Nama Kembar" name="izinkan_nama_sama"
                 hint="Sistem menolak nama yang sudah ada di kelas yang sama. Centang hanya bila memang dua orang berbeda.">
            <label class="pref-toggle">
                <input type="checkbox" name="izinkan_nama_sama" value="1" @checked(old('izinkan_nama_sama'))>
                <span>Izinkan nama sama</span>
            </label>
        </x-field>
    @endunless
</div>
