<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Apply saved appearance prefs before first paint (no flash of light theme).
         Defaults follow the OS via CSS media queries when no override is stored. --}}
    <script>
        (function () {
            try {
                var d = document.documentElement, ls = window.localStorage;
                var t = ls.getItem('pref-theme');
                if (t === 'dark' || t === 'light') d.setAttribute('data-theme', t);
                var f = ls.getItem('pref-font');
                if (f && f !== 'normal') d.setAttribute('data-font', f);
                if (ls.getItem('pref-contrast') === '1') d.setAttribute('data-contrast', 'more');
                if (ls.getItem('pref-motion') === '1') d.setAttribute('data-motion', 'reduced');
            } catch (e) {}
        })();
    </script>

    <title>@yield('title', 'Dashboard') · Jurnal Kelas</title>


    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    @stack('styles')
</head>
{{-- An akun pantau only looks: mode-pantau hides the controls that only write. --}}
<body @class(['mode-pantau' => Auth::user()?->isHanyaBaca()])>

@php
    $user = Auth::user();
    $isAdmin = $user?->isAdmin() ?? false;
    $isSiswa = $user?->isSiswa() ?? false;
    $pantau = $user?->isHanyaBaca() ?? false;

    // In "Mode Wali Kelas" the whole app narrows to one class, so the nav is
    // replaced rather than extended. The active class rides along on every
    // link, otherwise switching screens would reset it to the first class.
    $waliMode = request()->routeIs('wali-kelas.*');
    $waliQuery = request()->integer('kelas_id') ? ['kelas_id' => request()->integer('kelas_id')] : [];
    $beranda = $waliMode ? route('wali-kelas.dashboard', $waliQuery) : ($isAdmin ? route('admin.dashboard') : route('dashboard'));
@endphp

<div class="sidebar-scrim" id="sidebarScrim"></div>

<aside class="sidebar" id="sidebar">
    <a class="sidebar__brand" href="{{ $beranda }}">
        <span class="sidebar__mark"><x-ikon nama="journal-text" /></span>
        <span class="sidebar__wordmark">Jurnal Kelas</span>
    </a>

    <nav class="sidebar__nav">
        @if ($waliMode)
            <a href="{{ route('wali-kelas.dashboard', $waliQuery) }}"
               class="sidebar__link {{ request()->routeIs('wali-kelas.dashboard') ? 'is-active' : '' }}">
                <x-ikon nama="speedometer2" /><span>Dashboard</span>
            </a>

            <div class="sidebar__section">Kelas Perwalian</div>

            <a href="{{ route('wali-kelas.siswa', $waliQuery) }}"
               class="sidebar__link {{ request()->routeIs('wali-kelas.siswa') ? 'is-active' : '' }}">
                <x-ikon nama="people" /><span>Data Kelas</span>
            </a>
            <a href="{{ route('wali-kelas.jadwal', $waliQuery) }}"
               class="sidebar__link {{ request()->routeIs('wali-kelas.jadwal') ? 'is-active' : '' }}">
                <x-ikon nama="calendar3" /><span>Jadwal Kelas</span>
            </a>
            <a href="{{ route('wali-kelas.jurnal', $waliQuery) }}"
               class="sidebar__link {{ request()->routeIs('wali-kelas.jurnal') ? 'is-active' : '' }}">
                <x-ikon nama="journal-text" /><span>Jurnal Kelas</span>
            </a>
            <a href="{{ route('wali-kelas.presensi', $waliQuery) }}"
               class="sidebar__link {{ request()->routeIs('wali-kelas.presensi') ? 'is-active' : '' }}">
                <x-ikon nama="person-check" /><span>Presensi Kelas</span>
            </a>
        @elseif ($isSiswa)
            {{-- A student only ever works with their own class: its timetable,
                 its journal and their attendance. Master data is not theirs. --}}
            <a href="{{ route('dashboard') }}"
               class="sidebar__link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}">
                <x-ikon nama="speedometer2" /><span>Dashboard</span>
            </a>

            <div class="sidebar__section">Kegiatan</div>

            @if (Auth::user()->isKetuaKelas())
                {{-- Oct 2026: a ketua kelas only files today's journal and reads
                     today's — no timetable, no attendance screens, no history. --}}
                <a href="{{ route('jurnal.create') }}"
                   class="sidebar__link {{ request()->routeIs('jurnal.create') ? 'is-active' : '' }}">
                    <x-ikon nama="journal-text" /><span>Isi Jurnal Kelas</span>
                </a>
                <a href="{{ route('jurnal.index') }}" class="sidebar__link {{ request()->routeIs('jurnal.index', 'jurnal.show') ? 'is-active' : '' }}">
                    <x-ikon nama="calendar3" /><span>Jurnal Hari Ini</span>
                </a>
            @else
                <a href="{{ route('jadwal.index') }}" class="sidebar__link {{ request()->routeIs('jadwal.*') ? 'is-active' : '' }}">
                    <x-ikon nama="calendar3" /><span>Jadwal Kelas</span>
                </a>
                <a href="{{ route('jurnal.index') }}" class="sidebar__link {{ request()->routeIs('jurnal.index', 'jurnal.show') ? 'is-active' : '' }}">
                    <x-ikon nama="journal-text" /><span>Jurnal Kelas</span>
                </a>
                <a href="{{ route('presensi.index') }}"
                   class="sidebar__link {{ request()->routeIs('presensi.*', 'presensi-harian.*', 'presensi-jurnal.*') ? 'is-active' : '' }}">
                    <x-ikon nama="person-check" /><span>Presensi Saya</span>
                </a>
            @endif
        @else
            <a href="{{ $isAdmin ? route('admin.dashboard') : route('dashboard') }}"
               class="sidebar__link {{ request()->routeIs('dashboard', 'admin.dashboard') ? 'is-active' : '' }}">
                <x-ikon nama="speedometer2" /><span>Dashboard</span>
            </a>

            @if ($isAdmin)
                <div class="sidebar__section">Data Master</div>

                {{-- People and accounts are deliberately two entries: the top
                     two hold the school's records, the third only holds logins. --}}
                <a href="{{ route('admin.guru.index') }}"
                   class="sidebar__link {{ request()->routeIs('admin.guru.*') ? 'is-active' : '' }}">
                    <x-ikon nama="mortarboard" /><span>Data Guru</span>
                </a>
                <a href="{{ route('admin.siswa.index') }}"
                   class="sidebar__link {{ request()->routeIs('admin.siswa.*') ? 'is-active' : '' }}">
                    <x-ikon nama="people" /><span>Data Siswa</span>
                </a>
                @unless ($pantau)
                <a href="{{ route('admin.akun.index') }}"
                   class="sidebar__link {{ request()->routeIs('admin.akun.*') ? 'is-active' : '' }}">
                    <x-ikon nama="shield-lock" /><span>Akun Pengguna</span>
                </a>
                <a href="{{ route('admin.impor.index') }}"
                   class="sidebar__link {{ request()->routeIs('admin.impor.*') ? 'is-active' : '' }}">
                    <x-ikon nama="file-earmark-arrow-up" /><span>Impor Data</span>
                </a>
                <a href="{{ route('admin.tahun-ajaran.index') }}"
                   class="sidebar__link {{ request()->routeIs('admin.tahun-ajaran.*') ? 'is-active' : '' }}">
                    <x-ikon nama="calendar3" /><span>Tahun Ajaran</span>
                </a>
                @endunless
            @endif

            @if ($isAdmin)
                {{-- Oct 2026: master data is the admin's; a guru's screens are
                     the journal (CRUD) and the roster. --}}
                <a href="{{ route('kelas.index') }}" class="sidebar__link {{ request()->routeIs('kelas.*') ? 'is-active' : '' }}">
                    <x-ikon nama="building" /><span>Kelas</span>
                </a>
                <a href="{{ route('admin.jurusan.index') }}" class="sidebar__link {{ request()->routeIs('admin.jurusan.*') ? 'is-active' : '' }}">
                    <x-ikon nama="signpost-2" /><span>Jurusan</span>
                </a>
                <a href="{{ route('ruangan.index') }}" class="sidebar__link {{ request()->routeIs('ruangan.*') ? 'is-active' : '' }}">
                    <x-ikon nama="door" /><span>Ruangan</span>
                </a>
                <a href="{{ route('mata-pelajaran.index') }}" class="sidebar__link {{ request()->routeIs('mata-pelajaran.*') ? 'is-active' : '' }}">
                    <x-ikon nama="book" /><span>Mata Pelajaran</span>
                </a>
                <a href="{{ route('jadwal.index') }}" class="sidebar__link {{ request()->routeIs('jadwal.*') ? 'is-active' : '' }}">
                    <x-ikon nama="calendar3" /><span>Jadwal</span>
                </a>
                <a href="{{ route('admin.hari-libur.index') }}" class="sidebar__link {{ request()->routeIs('admin.hari-libur.*') ? 'is-active' : '' }}">
                    <x-ikon nama="calendar3" /><span>Hari Libur</span>
                </a>

                <div class="sidebar__section">Manajemen Akademik</div>

                <a href="{{ route('admin.transisi.index') }}"
                   class="sidebar__link {{ request()->routeIs('admin.transisi.*') ? 'is-active' : '' }}">
                    <x-ikon nama="mortarboard" /><span>Kenaikan &amp; Kelulusan</span>
                </a>
            @endif

            <div class="sidebar__section">Kegiatan</div>

            <a href="{{ route('jurnal.index') }}" class="sidebar__link {{ request()->routeIs('jurnal.*') ? 'is-active' : '' }}">
                <x-ikon nama="journal-text" /><span>Jurnal</span>
            </a>
            <a href="{{ route('presensi.index') }}"
               class="sidebar__link {{ request()->routeIs('presensi.*', 'presensi-harian.*', 'presensi-jurnal.*') ? 'is-active' : '' }}">
                <x-ikon nama="person-check" /><span>Presensi</span>
            </a>

            @if ($isAdmin)
                <div class="sidebar__section">Laporan</div>

                <a href="{{ route('admin.laporan.jurnal') }}" class="sidebar__link {{ request()->routeIs('admin.laporan.jurnal') ? 'is-active' : '' }}">
                    <x-ikon nama="clipboard-data" /><span>Rekap Jurnal</span>
                </a>
                <a href="{{ route('admin.laporan.presensi') }}" class="sidebar__link {{ request()->routeIs('admin.laporan.presensi') ? 'is-active' : '' }}">
                    <x-ikon nama="bar-chart" /><span>Rekap Presensi</span>
                </a>
                <a href="{{ route('admin.presensi.log') }}" class="sidebar__link {{ request()->routeIs('admin.presensi.log') ? 'is-active' : '' }}">
                    <x-ikon nama="clock-history" /><span>Log Presensi</span>
                </a>

                <div class="sidebar__section">Perangkat</div>

                <a href="{{ route('admin.kelas-qr.index') }}" class="sidebar__link {{ request()->routeIs('admin.kelas-qr.*') ? 'is-active' : '' }}">
                    <x-ikon nama="qr-code" /><span>Cetak QR Kelas</span>
                </a>
                @unless ($pantau)
                <a href="{{ route('admin.cadangan.index') }}" class="sidebar__link {{ request()->routeIs('admin.cadangan.*') ? 'is-active' : '' }}">
                    <x-ikon nama="shield-lock" /><span>Cadangan Data</span>
                </a>
                <a href="{{ route('admin.sistem.index') }}" class="sidebar__link {{ request()->routeIs('admin.sistem.*') ? 'is-active' : '' }}">
                    <x-ikon nama="activity" /><span>Sistem &amp; Log</span>
                </a>
                @endunless
            @endif
        @endif
    </nav>

    <div class="sidebar__foot">
        <form method="POST" action="{{ route('logout') }}" data-pantau>
            @csrf
            <button type="submit" class="sidebar__link"><x-ikon nama="box-arrow-left" /><span>Logout</span></button>
        </form>
    </div>
</aside>

<div class="main">
    <header class="topbar">
        <div class="d-flex align-items-center">
            <button class="sidebar-toggle" id="sidebarToggle" type="button" aria-label="Buka menu" aria-controls="sidebar" aria-expanded="false">
                <x-ikon nama="list" />
            </button>
            {{-- The top bar names the section ("Jurnal"); the page heading below
                 names the screen ("Isi Jurnal Mengajar"). --}}
            <h1 class="topbar__title">@yield('title', 'Dashboard')</h1>
        </div>

        <div class="topbar__right d-flex align-items-center gap-2">
            @if ($pantau)
                <span class="user-chip" title="Akun ini hanya bisa melihat data">
                    <x-ikon nama="eye" /><span class="d-none d-sm-inline">Akun Pantau</span>
                </span>
            @endif

            @if ($user?->isWaliKelas())
                <a class="user-chip" href="{{ $waliMode ? route('dashboard') : route('wali-kelas.dashboard') }}"
                   title="Beralih tampilan guru / wali kelas">
                    <x-ikon :nama="$waliMode ? 'mortarboard-fill' : 'mortarboard'" />
                    <span class="d-none d-sm-inline">{{ $waliMode ? 'Mode Guru' : 'Mode Wali Kelas' }}</span>
                </a>
            @endif

            <div class="dropdown">
                <button class="user-chip dropdown-toggle" type="button" data-bs-toggle="dropdown"
                        data-bs-auto-close="outside" aria-expanded="false" aria-label="Pengaturan tampilan">
                    <x-ikon nama="circle-half" />
                    <span class="d-none d-lg-inline">Tampilan</span>
                </button>
                <div class="dropdown-menu dropdown-menu-end pref-panel">
                    <div class="pref-group">
                        <span class="pref-group__label">Tema</span>
                        <div class="pref-seg" data-pref="theme">
                            <button type="button" data-value="system">Sistem</button>
                            <button type="button" data-value="light">Terang</button>
                            <button type="button" data-value="dark">Gelap</button>
                        </div>
                    </div>
                    <div class="pref-group">
                        <span class="pref-group__label">Ukuran Font</span>
                        <div class="pref-seg" data-pref="font">
                            <button type="button" data-value="kecil">Kecil</button>
                            <button type="button" data-value="normal">Normal</button>
                            <button type="button" data-value="besar">Besar</button>
                            <button type="button" data-value="ekstra">Ekstra</button>
                        </div>
                    </div>
                    <label class="pref-toggle">
                        <input type="checkbox" data-pref-toggle="contrast"> Kontras tinggi
                    </label>
                    <label class="pref-toggle">
                        <input type="checkbox" data-pref-toggle="motion"> Kurangi gerak (animasi)
                    </label>
                </div>
            </div>

            <div class="dropdown">
            <button class="user-chip dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="avatar">{{ $user?->inisial() }}</span>
                <span class="d-none d-md-inline">{{ $user?->nama }}</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <span class="dropdown-item-text small">
                        <span class="d-block text-muted">{{ $user?->email }}</span>
                        <span class="chip chip--neutral mt-1">{{ ucfirst($user?->role) }}</span>
                    </span>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}" data-pantau>
                        @csrf
                        <button type="submit" class="dropdown-item text-danger">
                            <x-ikon nama="box-arrow-left" kelas="me-2" />Logout
                        </button>
                    </form>
                </li>
            </ul>
            </div>
        </div>
    </header>

    <main class="content" @hasSection('processing') data-page-processing="@yield('processing')" @endif>
        {{-- Maintenance/disruption notices are for the people using the app, not
             for the admin who posts them and has the Sistem page instead. --}}
        <x-pengumuman-banner :untuk-peran="$user !== null && ! $isAdmin" />
        <x-flash />
        @hasSection('processing')
            <x-processing-status />
        @endif
        @yield('content')
    </main>
</div>

<script>
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const scrim = document.getElementById('sidebarScrim');
    const mobileMenu = window.matchMedia('(max-width: 991.98px)');

    const syncSidebar = () => {
        const open = sidebar.classList.contains('is-open');
        sidebar.inert = mobileMenu.matches && !open;
        toggle.setAttribute('aria-expanded', String(open));
        if (mobileMenu.matches) sidebar.setAttribute('aria-hidden', String(!open));
        else sidebar.removeAttribute('aria-hidden');
    };

    const closeSidebar = () => {
        const hadFocus = sidebar.contains(document.activeElement);
        sidebar.classList.remove('is-open');
        scrim.classList.remove('is-open');
        syncSidebar();
        if (hadFocus) toggle.focus();
    };

    toggle?.addEventListener('click', () => {
        sidebar.classList.toggle('is-open');
        scrim.classList.toggle('is-open');
        syncSidebar();
        if (sidebar.classList.contains('is-open')) sidebar.querySelector('a, button')?.focus();
    });

    scrim?.addEventListener('click', closeSidebar);
    mobileMenu.addEventListener('change', closeSidebar);
    syncSidebar();

    document.addEventListener('keydown', (event) => {
        if (!mobileMenu.matches || !sidebar.classList.contains('is-open')) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            closeSidebar();
        } else if (event.key === 'Tab') {
            const items = [...sidebar.querySelectorAll('a[href], button:not([disabled])')];
            const first = items[0], last = items[items.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });

    // Appearance preferences: apply changes live and remember them per device.
    (function () {
        const d = document.documentElement;
        const ls = window.localStorage;
        const get = (k, def) => { try { return ls.getItem(k) ?? def; } catch (e) { return def; } };
        const set = (k, v) => { try { v == null ? ls.removeItem(k) : ls.setItem(k, v); } catch (e) {} };

        const syncSeg = (pref, val) =>
            document.querySelectorAll(`.pref-seg[data-pref="${pref}"] button`).forEach(
                (b) => b.classList.toggle('is-active', b.dataset.value === val));

        // Reflect the stored state onto the controls.
        syncSeg('theme', get('pref-theme', 'system'));
        syncSeg('font', get('pref-font', 'normal'));
        const cEl = document.querySelector('[data-pref-toggle="contrast"]');
        const mEl = document.querySelector('[data-pref-toggle="motion"]');
        if (cEl) cEl.checked = get('pref-contrast', '') === '1';
        if (mEl) mEl.checked = get('pref-motion', '') === '1';

        document.querySelectorAll('.pref-seg[data-pref="theme"] button').forEach((b) =>
            b.addEventListener('click', () => {
                const v = b.dataset.value;
                v === 'system' ? d.removeAttribute('data-theme') : d.setAttribute('data-theme', v);
                set('pref-theme', v);
                syncSeg('theme', v);
            }));

        document.querySelectorAll('.pref-seg[data-pref="font"] button').forEach((b) =>
            b.addEventListener('click', () => {
                const v = b.dataset.value;
                v === 'normal' ? d.removeAttribute('data-font') : d.setAttribute('data-font', v);
                set('pref-font', v === 'normal' ? null : v);
                syncSeg('font', v);
            }));

        cEl?.addEventListener('change', () => {
            cEl.checked ? d.setAttribute('data-contrast', 'more') : d.removeAttribute('data-contrast');
            set('pref-contrast', cEl.checked ? '1' : null);
        });
        mEl?.addEventListener('change', () => {
            mEl.checked ? d.setAttribute('data-motion', 'reduced') : d.removeAttribute('data-motion');
            set('pref-motion', mEl.checked ? '1' : null);
        });
    })();
</script>

@stack('scripts')
</body>
</html>
