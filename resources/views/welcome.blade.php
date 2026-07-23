<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jurnal Kelas · Catat jurnal mengajar tanpa ribet</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    @vite(['resources/sass/app.scss', 'resources/js/app.js'])

    <style>
        .landing { min-height: 100vh; background: var(--p-500); color: #fff; display: flex; flex-direction: column; }
        .landing__bar { display: flex; align-items: center; justify-content: space-between; padding: 24px 64px; }
        .landing__main { flex: 1; display: flex; align-items: center; padding: 0 64px; }
        .landing__grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 64px; align-items: center; width: 100%; }
        .landing__card { background: var(--n-100); border-radius: 16px; padding: 22px; color: var(--n-1000); }
        .landing__foot { padding: 24px 64px; font-size: 11.5px; color: rgba(255, 255, 255, 0.5); }

        @media (max-width: 991.98px) {
            .landing__bar, .landing__main, .landing__foot { padding-left: 24px; padding-right: 24px; }
            .landing__grid { grid-template-columns: minmax(0, 1fr); gap: 32px; }
            .landing__preview { display: none; }
        }
    </style>
</head>
<body>

<div class="landing">
    <header class="landing__bar">
        <div class="sidebar__brand p-0">
            <span class="sidebar__mark"><i class="bi bi-journal-text"></i></span>
            <span class="sidebar__wordmark">Jurnal Kelas</span>
        </div>

        <a class="btn-hifi" href="{{ route('login') }}">Masuk</a>
    </header>

    <main class="landing__main">
        <div class="landing__grid">
            <div>
                <h1 class="auth__headline">Catat jurnal mengajar<br>tanpa ribet.</h1>
                <p class="auth__lede">
                    Satu tempat untuk jurnal mengajar, presensi siswa, dan rekap laporan sekolah —
                    terisi otomatis dari jadwal, siap diekspor kapan saja.
                </p>

                <div class="auth__feature">
                    <i class="bi bi-check-lg"></i>
                    <div>
                        <p class="auth__feature-title">Jurnal terisi otomatis dari jadwal</p>
                        <p class="auth__feature-sub">Tidak perlu ketik ulang kelas, mapel, atau jam.</p>
                    </div>
                </div>
                <div class="auth__feature">
                    <i class="bi bi-check-lg"></i>
                    <div>
                        <p class="auth__feature-title">Presensi dalam satu ketukan</p>
                        <p class="auth__feature-sub">Tandai hadir, sakit, izin, alpa langsung dari daftar siswa.</p>
                    </div>
                </div>
                <div class="auth__feature">
                    <i class="bi bi-check-lg"></i>
                    <div>
                        <p class="auth__feature-title">Kehadiran guru ikut tercatat</p>
                        <p class="auth__feature-sub">Guru dan ketua kelas sama-sama bisa melaporkannya.</p>
                    </div>
                </div>

                <a class="btn-hifi mt-3" href="{{ route('login') }}">Masuk ke akun Anda →</a>
            </div>

            <div class="landing__preview d-flex flex-column gap-3">
                <div class="landing__card">
                    <span class="kpi__label">Kelengkapan Jurnal</span>
                    <div class="kpi__value mt-1">{{ $kelengkapan }}%</div>
                    <span class="meter mt-2" style="width: 100%">
                        <span class="meter__fill" style="width: {{ $kelengkapan }}%"></span>
                    </span>
                    <p class="kpi__caption mt-2 mb-0">rata-rata seluruh kelas, semester berjalan</p>
                </div>

                <div class="landing__card">
                    <span class="kpi__label">Kehadiran Guru</span>
                    <div class="mt-2 d-flex flex-column gap-2">
                        @foreach ([['Hadir', 'green', 'hadir'], ['Ada Tugas', 'yellow', 'ada_tugas'], ['Tanpa Tugas', 'red', 'tanpa_tugas']] as [$label, $tone, $key])
                            <div class="d-flex align-items-center justify-content-between" style="font-size: 11.5px">
                                <span>{{ $label }}</span>
                                <span class="d-flex align-items-center gap-2">
                                    <strong>{{ number_format($kehadiranGuru[$key]) }}</strong>
                                    <x-chip :tone="$tone" :label="$label" />
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="landing__foot">
        © {{ date('Y') }} Jurnal Kelas · Seluruh hak cipta dilindungi.
    </footer>
</div>

</body>
</html>
