<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard') · Jurnal Kelas</title>


    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    @stack('styles')
</head>
<body>

@php
    $user = Auth::user();
    $isAdmin = $user?->isAdmin() ?? false;
    $isSiswa = $user?->isSiswa() ?? false;

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

            <a href="{{ route('jadwal.index') }}" class="sidebar__link {{ request()->routeIs('jadwal.*') ? 'is-active' : '' }}">
                <x-ikon nama="calendar3" /><span>Jadwal Kelas</span>
            </a>
            <a href="{{ route('jurnal.index') }}" class="sidebar__link {{ request()->routeIs('jurnal.*') ? 'is-active' : '' }}">
                <x-ikon nama="journal-text" /><span>Jurnal Kelas</span>
            </a>
            <a href="{{ route('presensi.index') }}" class="sidebar__link {{ request()->routeIs('presensi.*') ? 'is-active' : '' }}">
                <x-ikon nama="person-check" /><span>Presensi Saya</span>
            </a>
        @else
            <a href="{{ $isAdmin ? route('admin.dashboard') : route('dashboard') }}"
               class="sidebar__link {{ request()->routeIs('dashboard', 'admin.dashboard') ? 'is-active' : '' }}">
                <x-ikon nama="speedometer2" /><span>Dashboard</span>
            </a>

            <div class="sidebar__section">Data Master</div>

            @if ($isAdmin)
                <a href="{{ route('admin.users.index') }}"
                   class="sidebar__link {{ request()->routeIs('admin.users.*') ? 'is-active' : '' }}">
                    <x-ikon nama="people" /><span>Pengguna</span>
                </a>
            @endif

            <a href="{{ route('kelas.index') }}" class="sidebar__link {{ request()->routeIs('kelas.*') ? 'is-active' : '' }}">
                <x-ikon nama="building" /><span>{{ $isAdmin ? 'Kelas' : 'Kelas Saya' }}</span>
            </a>
            <a href="{{ route('mata-pelajaran.index') }}" class="sidebar__link {{ request()->routeIs('mata-pelajaran.*') ? 'is-active' : '' }}">
                <x-ikon nama="book" /><span>{{ $isAdmin ? 'Mata Pelajaran' : 'Mapel Saya' }}</span>
            </a>
            <a href="{{ route('jadwal.index') }}" class="sidebar__link {{ request()->routeIs('jadwal.*') ? 'is-active' : '' }}">
                <x-ikon nama="calendar3" /><span>Jadwal</span>
            </a>

            <div class="sidebar__section">Kegiatan</div>

            <a href="{{ route('jurnal.index') }}" class="sidebar__link {{ request()->routeIs('jurnal.*') ? 'is-active' : '' }}">
                <x-ikon nama="journal-text" /><span>Jurnal</span>
            </a>
            <a href="{{ route('presensi.index') }}" class="sidebar__link {{ request()->routeIs('presensi.*') ? 'is-active' : '' }}">
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
                <a href="{{ route('admin.cadangan.index') }}" class="sidebar__link {{ request()->routeIs('admin.cadangan.*') ? 'is-active' : '' }}">
                    <x-ikon nama="shield-lock" /><span>Cadangan Data</span>
                </a>
                <a href="{{ route('admin.sistem.index') }}" class="sidebar__link {{ request()->routeIs('admin.sistem.*') ? 'is-active' : '' }}">
                    <x-ikon nama="activity" /><span>Sistem &amp; Log</span>
                </a>
            @endif
        @endif
    </nav>

    <div class="sidebar__foot">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="sidebar__link"><x-ikon nama="box-arrow-left" /><span>Logout</span></button>
        </form>
    </div>
</aside>

<div class="main">
    <header class="topbar">
        <div class="d-flex align-items-center">
            <button class="sidebar-toggle" id="sidebarToggle" type="button" aria-label="Buka menu">
                <x-ikon nama="list" />
            </button>
            {{-- The top bar names the section ("Jurnal"); the page heading below
                 names the screen ("Isi Jurnal Mengajar"). --}}
            <h1 class="topbar__title">@yield('title', 'Dashboard')</h1>
        </div>

        <div class="topbar__right d-flex align-items-center gap-2">
            @if ($user?->isWaliKelas())
                <a class="user-chip" href="{{ $waliMode ? route('dashboard') : route('wali-kelas.dashboard') }}"
                   title="Beralih tampilan guru / wali kelas">
                    <x-ikon :nama="$waliMode ? 'mortarboard-fill' : 'mortarboard'" />
                    <span class="d-none d-sm-inline">{{ $waliMode ? 'Mode Guru' : 'Mode Wali Kelas' }}</span>
                </a>
            @endif

            <div class="dropdown">
            <button class="user-chip dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="avatar">{{ $user?->inisial() }}</span>
                <span class="d-none d-md-inline">{{ $user?->name }}</span>
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
                    <form method="POST" action="{{ route('logout') }}">
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

    <main class="content">
        {{-- Maintenance/disruption notices are for the people using the app, not
             for the admin who posts them and has the Sistem page instead. --}}
        <x-pengumuman-banner :untuk-peran="$user !== null && ! $isAdmin" />
        <x-flash />
        @yield('content')
    </main>
</div>

<script>
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const scrim = document.getElementById('sidebarScrim');

    const closeSidebar = () => {
        sidebar.classList.remove('is-open');
        scrim.classList.remove('is-open');
    };

    toggle?.addEventListener('click', () => {
        sidebar.classList.toggle('is-open');
        scrim.classList.toggle('is-open');
    });

    scrim?.addEventListener('click', closeSidebar);
</script>

@stack('scripts')
</body>
</html>
