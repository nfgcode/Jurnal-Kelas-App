{{--
    Shared form fields for creating and editing a user.
    Expects: $user (nullable), $kelasList
--}}
@php $user ??= null; @endphp

<div class="form-grid form-grid--2">
    <x-field label="Nama Lengkap" name="name" required>
        <input class="input-hifi" type="text" name="name" id="name"
               value="{{ old('name', $user?->name) }}" placeholder="mis. Budi Santoso" required>
    </x-field>

    <x-field label="Email" name="email" required>
        <input class="input-hifi" type="email" name="email" id="email"
               value="{{ old('email', $user?->email) }}" placeholder="nama@jurnalkelas.app" required>
    </x-field>

    <x-field label="Peran" name="role" required>
        <select class="select-hifi" name="role" id="role" required>
            <option value="" disabled @selected(! old('role', $user?->role))>Pilih Peran</option>
            @foreach (['admin' => 'Administrator', 'guru' => 'Guru', 'siswa' => 'Siswa'] as $value => $label)
                <option value="{{ $value }}" @selected(old('role', $user?->role) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </x-field>

    <x-field label="Status Akun" name="status" required hint="Akun nonaktif tidak dapat masuk.">
        <select class="select-hifi" name="status" id="status" required>
            @foreach (['aktif' => 'Aktif', 'pending' => 'Pending', 'nonaktif' => 'Nonaktif'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $user?->status ?? 'aktif') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </x-field>

    <x-field :label="$user ? 'Password Baru' : 'Password'" name="password" :required="! $user"
             :hint="$user ? 'Biarkan kosong bila tidak ingin diubah.' : 'Minimal 8 karakter.'">
        <input class="input-hifi" type="password" name="password" id="password"
               placeholder="{{ $user ? 'Kosongkan jika tidak diganti' : 'Minimal 8 karakter' }}"
               @unless ($user) required @endunless>
    </x-field>

    <div data-role-field="guru">
        <x-field label="NIP" name="nip" required hint="Dipakai guru untuk login. Harus unik.">
            <input class="input-hifi" type="text" name="nip" id="nip"
                   value="{{ old('nip', $user?->nip) }}" placeholder="mis. 198501012010011001">
        </x-field>
    </div>

    <div data-role-field="siswa">
        <x-field label="NIS" name="nis" required hint="Dipakai siswa untuk login. Harus unik.">
            <input class="input-hifi" type="text" name="nis" id="nis"
                   value="{{ old('nis', $user?->nis) }}" placeholder="mis. 20261001">
        </x-field>
    </div>

    <div data-role-field="siswa">
        <x-field label="Kelas" name="kelas_id" required>
            <select class="select-hifi" name="kelas_id" id="kelas_id">
                <option value="" disabled @selected(! old('kelas_id', $user?->kelas_id))>Pilih Kelas</option>
                @foreach ($kelasList as $kelas)
                    <option value="{{ $kelas->id }}" @selected((int) old('kelas_id', $user?->kelas_id) === $kelas->id)>
                        {{ $kelas->nama_kelas }} — {{ $kelas->tahun_ajaran }}
                    </option>
                @endforeach
            </select>
        </x-field>
    </div>

    <div data-role-field="siswa">
        <x-field label="Ketua Kelas" hint="Ketua kelas boleh mengisi jurnal kelas atas nama guru.">
            <label class="checkbox-row">
                <input type="checkbox" name="is_ketua_kelas" value="1" @checked(old('is_ketua_kelas', $user?->is_ketua_kelas))>
                Jadikan ketua kelas
            </label>
        </x-field>
    </div>
</div>

@push('scripts')
    <script>
        // Show only the fields relevant to the selected role.
        (function () {
            const roleSelect = document.getElementById('role');
            const roleFields = document.querySelectorAll('[data-role-field]');

            function syncRoleFields() {
                roleFields.forEach((field) => {
                    field.hidden = field.dataset.roleField !== roleSelect.value;
                });
            }

            roleSelect.addEventListener('change', syncRoleFields);
            syncRoleFields();
        })();
    </script>
@endpush
