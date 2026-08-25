@php
    $akun ??= null;
    $milikOrang = $akun && ($akun->nip || $akun->nis);
@endphp

@if ($milikOrang)
    <p class="field__hint mb-2">
        <x-ikon nama="info-circle" />
        Akun ini milik <strong>{{ $akun->nama }}</strong> ({{ $akun->nip ?? $akun->nis }}).
        Namanya dan data sekolahnya diubah di
        <a href="{{ $akun->guru ? route('admin.guru.edit', $akun->guru) : route('admin.siswa.edit', $akun->siswa) }}">halaman datanya</a>,
        bukan di sini — satu nama, satu tempat.
    </p>
@endif

<div class="form-grid form-grid--2">
    <x-field label="Username" name="username" required hint="Huruf, angka, titik, garis bawah, tanda hubung.">
        <input class="input-hifi" type="text" name="username" id="username"
               value="{{ old('username', $akun?->username) }}" placeholder="mis. budi.santoso" required>
    </x-field>

    <x-field label="Email" name="email" required>
        <input class="input-hifi" type="email" name="email" id="email"
               value="{{ old('email', $akun?->email) }}" placeholder="nama@sekolah.sch.id" required>
    </x-field>

    <x-field label="Peran" name="role" required
             :hint="$milikOrang ? 'Mengikuti data orangnya; tidak bisa diubah dari sini.' : 'Hanya akun admin yang dibuat di halaman ini.'">
        <select class="select-hifi" name="role" id="role" required @if ($milikOrang) disabled @endif>
            @foreach (['admin' => 'Administrator', 'guru' => 'Guru', 'siswa' => 'Siswa'] as $value => $label)
                <option value="{{ $value }}" @selected(old('role', $akun?->role ?? 'admin') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @if ($milikOrang)
            <input type="hidden" name="role" value="{{ $akun->role }}">
        @endif
    </x-field>

    <x-field label="Status" name="status" required hint="Nonaktif berarti tidak bisa masuk sama sekali.">
        <select class="select-hifi" name="status" id="status" required>
            @foreach (['aktif' => 'Aktif', 'pending' => 'Pending', 'nonaktif' => 'Nonaktif'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $akun?->status ?? 'aktif') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </x-field>

    @unless ($milikOrang)
        <x-field label="Nama" name="nama" required hint="Nama admin ini. Guru dan siswa mengambil nama dari data orangnya.">
            <input class="input-hifi" type="text" name="nama" id="nama"
                   value="{{ old('nama', $akun?->nama) }}" placeholder="mis. Administrator Sekolah" required>
        </x-field>
    @endunless

    <x-field label="Kata Sandi" name="password" :required="! $akun"
             :hint="$akun ? 'Kosongkan bila tidak diganti.' : 'Minimal 8 karakter.'">
        <input class="input-hifi" type="password" name="password" id="password"
               autocomplete="new-password" @if (! $akun) required @endif>
    </x-field>
</div>
