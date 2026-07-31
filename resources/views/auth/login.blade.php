<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk · Jurnal Kelas</title>


    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
</head>
<body>

@php
    // The tab drives the identifier label, so it must survive a failed attempt.
    $peran = old('role', 'guru');

    $identitas = [
        'admin' => ['label' => 'Email', 'placeholder' => 'admin@sekolah.sch.id'],
        'guru' => ['label' => 'NIP', 'placeholder' => '19850412 200604 1 012'],
        'siswa' => ['label' => 'NIS', 'placeholder' => '20261079'],
    ];
@endphp

<div class="auth">
    <section class="auth__brand">
        <a class="sidebar__brand p-0" href="{{ route('landing') }}">
            <span class="sidebar__mark"><x-ikon nama="journal-text" /></span>
            <span class="sidebar__wordmark">Jurnal Kelas</span>
        </a>

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

        <div class="auth__stats">
            @foreach ($ringkasan as $label => $nilai)
                <div>
                    <div class="auth__stat-value">{{ number_format($nilai, 0, ',', '.') }}</div>
                    <div class="auth__stat-label">{{ $label }}</div>
                </div>
            @endforeach
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
            <p class="auth__sub">Gunakan NIP untuk guru dan admin, atau NIS untuk siswa.</p>

            <div class="role-tabs">
                @foreach (['admin' => 'Administrator', 'guru' => 'Guru', 'siswa' => 'Siswa'] as $value => $label)
                    <label class="role-tabs__opt">
                        <input type="radio" name="role" value="{{ $value }}" @checked($peran === $value) data-role-tab>
                        {{ $label }}
                    </label>
                @endforeach
            </div>

            <div class="auth__field">
                <label class="field__label d-block mb-1" for="user" id="userLabel">
                    {{ $identitas[$peran]['label'] }}
                </label>
                <input class="input-hifi" type="text" id="user" name="user" value="{{ old('user') }}"
                       placeholder="{{ $identitas[$peran]['placeholder'] }}" autofocus required>
                @error('user')<span class="field__error">{{ $message }}</span>@enderror
            </div>

            <div class="auth__field">
                <div class="auth__label-row">
                    <label class="field__label" for="password">Kata Sandi</label>
                    <span class="auth__link">Lupa kata sandi?</span>
                </div>
                <div class="password-wrap">
                    <input class="input-hifi" type="password" id="password" name="password" required>
                    <button class="password-toggle" type="button" id="togglePassword" aria-label="Tampilkan kata sandi">
                        <x-ikon nama="eye" />
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
    // Keep the identifier field labelled for whichever role tab is selected.
    const labels = @json(collect($identitas)->map(fn ($i) => [$i['label'], $i['placeholder']]));
    const userLabel = document.getElementById('userLabel');
    const userInput = document.getElementById('user');
    const passwordInput = document.getElementById('password');

    document.querySelectorAll('[data-role-tab]').forEach((tab) => {
        tab.addEventListener('change', () => {
            const [label, placeholder] = labels[tab.value];
            userLabel.textContent = label;
            userInput.placeholder = placeholder;
        });
    });

    const toggle = document.getElementById('togglePassword');
    toggle?.addEventListener('click', () => {
        const hidden = passwordInput.type === 'password';
        passwordInput.type = hidden ? 'text' : 'password';
        toggle.querySelector('i').className = hidden ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
</script>

</body>
</html>
