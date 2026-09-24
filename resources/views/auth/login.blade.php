<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk · Jurnal Kelas</title>


    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
</head>
<body>

<div class="auth">
    <section class="auth__brand">
        {{-- Plain brand, not a link: there is no landing page to go back to. --}}
        <div class="sidebar__brand p-0">
            <span class="sidebar__mark"><x-ikon nama="journal-text" /></span>
            <span class="sidebar__wordmark">Jurnal Kelas</span>
        </div>

        <div class="my-auto">
            <h1 class="auth__headline">Catat jurnal mengajar<br>tanpa ribet.</h1>
            <p class="auth__lede">Satu tempat untuk jurnal mengajar, presensi siswa, dan rekap laporan sekolah.</p>

            <div class="auth__feature">
                <x-ikon nama="check-lg" />
                <div>
                    <p class="auth__feature-title">Jurnal terisi otomatis dari jadwal</p>
                    <p class="auth__feature-sub">Tidak perlu ketik ulang kelas, mapel, atau jam.</p>
                </div>
            </div>
            <div class="auth__feature">
                <x-ikon nama="check-lg" />
                <div>
                    <p class="auth__feature-title">Presensi dalam satu ketukan</p>
                    <p class="auth__feature-sub">Tandai hadir, sakit, izin, alpa langsung dari daftar siswa.</p>
                </div>
            </div>
            <div class="auth__feature">
                <x-ikon nama="check-lg" />
                <div>
                    <p class="auth__feature-title">Rekap siap diekspor</p>
                    <p class="auth__feature-sub">Laporan bulanan per kelas dan per guru.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="auth__panel">
        <div class="auth__top">
            <span>Belum punya akun?</span>
            <span class="auth__link">Hubungi admin sekolah</span>
        </div>

        <form class="auth__form" method="POST" action="{{ route('login') }}">
            @csrf

            <h2 class="auth__title">Masuk ke akun Anda</h2>
            <p class="auth__sub">Masuk dengan username, email, NIP (guru), atau NIS (siswa).</p>

            {{-- One identifier field, no role picker (Figma login): the server
                 works out whose NIP, NIS, username or email this is. --}}
            <div class="auth__field">
                <label class="field__label d-block mb-1" for="user">NIP / NIS / Username</label>
                <input class="input-hifi" type="text" id="user" name="user" value="{{ old('user') }}"
                       placeholder="Masukkan NIP, NIS, username, atau email" autocomplete="username"
                       autofocus required>
                @error('user')<span class="field__error">{{ $message }}</span>@enderror
            </div>

            <div class="auth__field">
                <div class="auth__label-row">
                    <label class="field__label" for="password">Kata Sandi</label>
                    <span class="auth__link">Lupa kata sandi?</span>
                </div>
                <div class="password-wrap">
                    <input class="input-hifi" type="password" id="password" name="password"
                           autocomplete="current-password" required>
                    <button class="password-toggle" type="button" id="togglePassword"
                            aria-label="Tampilkan kata sandi" aria-pressed="false">
                        <span data-ikon-tampil><x-ikon nama="eye" /></span>
                        <span data-ikon-sembunyi hidden><x-ikon nama="eye-slash" /></span>
                    </button>
                </div>
                @error('password')<span class="field__error">{{ $message }}</span>@enderror
            </div>

            <label class="checkbox-row">
                <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                Biarkan saya tetap masuk di perangkat ini
            </label>

            <button class="auth__submit" type="submit">Masuk</button>
        </form>

        <p class="auth__foot mb-0">© {{ date('Y') }} Jurnal Kelas · Seluruh hak cipta dilindungi.</p>
    </section>
</div>

<script>
    // Show/hide the password. The icons are inline SVGs now (no more <i> from
    // the old icon font), so both ship in the markup and one is hidden.
    const passwordInput = document.getElementById('password');
    const toggle = document.getElementById('togglePassword');
    toggle?.addEventListener('click', () => {
        const tampil = passwordInput.type === 'password';
        passwordInput.type = tampil ? 'text' : 'password';
        toggle.setAttribute('aria-pressed', String(tampil));
        toggle.setAttribute('aria-label', tampil ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
        toggle.querySelector('[data-ikon-tampil]').hidden = tampil;
        toggle.querySelector('[data-ikon-sembunyi]').hidden = !tampil;
    });
</script>

</body>
</html>
