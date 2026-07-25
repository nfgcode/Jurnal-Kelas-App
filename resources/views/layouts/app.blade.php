<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard') · Jurnal Kelas</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    @stack('styles')
</head>
<body>

@include('partials.page-loader')

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
        <span class="sidebar__mark"><i class="bi bi-journal-text"></i></span>
        <span class="sidebar__wordmark">Jurnal Kelas</span>
    </a>

    <nav class="sidebar__nav">
        @if ($waliMode)
            <a href="{{ route('wali-kelas.dashboard', $waliQuery) }}"
               class="sidebar__link {{ request()->routeIs('wali-kelas.dashboard') ? 'is-active' : '' }}">
                <i class="bi bi-speedometer2"></i><span>Dashboard</span>
            </a>

            <div class="sidebar__section">Kelas Perwalian</div>

            <a href="{{ route('wali-kelas.siswa', $waliQuery) }}"
               class="sidebar__link {{ request()->routeIs('wali-kelas.siswa') ? 'is-active' : '' }}">
                <i class="bi bi-people"></i><span>Data Kelas</span>
            </a>
            <a href="{{ route('wali-kelas.jadwal', $waliQuery) }}"
               class="sidebar__link {{ request()->routeIs('wali-kelas.jadwal') ? 'is-active' : '' }}">
                <i class="bi bi-calendar3"></i><span>Jadwal Kelas</span>
            </a>
            <a href="{{ route('wali-kelas.jurnal', $waliQuery) }}"
               class="sidebar__link {{ request()->routeIs('wali-kelas.jurnal') ? 'is-active' : '' }}">
                <i class="bi bi-journal-text"></i><span>Jurnal Kelas</span>
            </a>
            <a href="{{ route('wali-kelas.presensi', $waliQuery) }}"
               class="sidebar__link {{ request()->routeIs('wali-kelas.presensi') ? 'is-active' : '' }}">
                <i class="bi bi-person-check"></i><span>Presensi Kelas</span>
            </a>
        @elseif ($isSiswa)
            {{-- A student only ever works with their own class: its timetable,
                 its journal and their attendance. Master data is not theirs. --}}
            <a href="{{ route('dashboard') }}"
               class="sidebar__link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}">
                <i class="bi bi-speedometer2"></i><span>Dashboard</span>
            </a>

            <div class="sidebar__section">Kegiatan</div>

            <a href="{{ route('jadwal.index') }}" class="sidebar__link {{ request()->routeIs('jadwal.*') ? 'is-active' : '' }}">
                <i class="bi bi-calendar3"></i><span>Jadwal Kelas</span>
            </a>
            <a href="{{ route('jurnal.index') }}" class="sidebar__link {{ request()->routeIs('jurnal.*') ? 'is-active' : '' }}">
                <i class="bi bi-journal-text"></i><span>Jurnal Kelas</span>
            </a>
            <a href="{{ route('presensi.index') }}" class="sidebar__link {{ request()->routeIs('presensi.*') ? 'is-active' : '' }}">
                <i class="bi bi-person-check"></i><span>Presensi Saya</span>
            </a>
        @else
            <a href="{{ $isAdmin ? route('admin.dashboard') : route('dashboard') }}"
               class="sidebar__link {{ request()->routeIs('dashboard', 'admin.dashboard') ? 'is-active' : '' }}">
                <i class="bi bi-speedometer2"></i><span>Dashboard</span>
            </a>

            <div class="sidebar__section">Data Master</div>

            @if ($isAdmin)
                <a href="{{ route('admin.users.index') }}"
                   class="sidebar__link {{ request()->routeIs('admin.users.*') ? 'is-active' : '' }}">
                    <i class="bi bi-people"></i><span>Pengguna</span>
                </a>
            @endif

            <a href="{{ route('kelas.index') }}" class="sidebar__link {{ request()->routeIs('kelas.*') ? 'is-active' : '' }}">
                <i class="bi bi-building"></i><span>{{ $isAdmin ? 'Kelas' : 'Kelas Saya' }}</span>
            </a>
            <a href="{{ route('mata-pelajaran.index') }}" class="sidebar__link {{ request()->routeIs('mata-pelajaran.*') ? 'is-active' : '' }}">
                <i class="bi bi-book"></i><span>{{ $isAdmin ? 'Mata Pelajaran' : 'Mapel Saya' }}</span>
            </a>
            <a href="{{ route('jadwal.index') }}" class="sidebar__link {{ request()->routeIs('jadwal.*') ? 'is-active' : '' }}">
                <i class="bi bi-calendar3"></i><span>Jadwal</span>
            </a>

            <div class="sidebar__section">Kegiatan</div>

            <a href="{{ route('jurnal.index') }}" class="sidebar__link {{ request()->routeIs('jurnal.*') ? 'is-active' : '' }}">
                <i class="bi bi-journal-text"></i><span>Jurnal</span>
            </a>
            <a href="{{ route('presensi.index') }}" class="sidebar__link {{ request()->routeIs('presensi.*') ? 'is-active' : '' }}">
                <i class="bi bi-person-check"></i><span>Presensi</span>
            </a>

            @if ($isAdmin)
                <div class="sidebar__section">Laporan</div>

                <a href="{{ route('admin.laporan.jurnal') }}" class="sidebar__link {{ request()->routeIs('admin.laporan.jurnal') ? 'is-active' : '' }}">
                    <i class="bi bi-clipboard-data"></i><span>Rekap Jurnal</span>
                </a>
                <a href="{{ route('admin.laporan.presensi') }}" class="sidebar__link {{ request()->routeIs('admin.laporan.presensi') ? 'is-active' : '' }}">
                    <i class="bi bi-bar-chart"></i><span>Rekap Presensi</span>
                </a>
                <a href="{{ route('admin.presensi.log') }}" class="sidebar__link {{ request()->routeIs('admin.presensi.log') ? 'is-active' : '' }}">
                    <i class="bi bi-clock-history"></i><span>Log Presensi</span>
                </a>
            @endif
        @endif
    </nav>

    <div class="sidebar__foot">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="sidebar__link"><i class="bi bi-box-arrow-left"></i><span>Logout</span></button>
        </form>
    </div>
</aside>

<div class="main">
    <header class="topbar">
        <div class="d-flex align-items-center">
            <button class="sidebar-toggle" id="sidebarToggle" type="button" aria-label="Buka menu">
                <i class="bi bi-list"></i>
            </button>
            {{-- The top bar names the section ("Jurnal"); the page heading below
                 names the screen ("Isi Jurnal Mengajar"). --}}
            <h1 class="topbar__title">@yield('title', 'Dashboard')</h1>
        </div>

        <div class="topbar__right d-flex align-items-center gap-2">
            @if ($user?->isWaliKelas())
                <a class="user-chip" href="{{ $waliMode ? route('dashboard') : route('wali-kelas.dashboard') }}"
                   title="Beralih tampilan guru / wali kelas">
                    <i class="bi {{ $waliMode ? 'bi-mortarboard-fill' : 'bi-mortarboard' }}"></i>
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
                            <i class="bi bi-box-arrow-left me-2"></i>Logout
                        </button>
                    </form>
                </li>
            </ul>
            </div>
        </div>
    </header>

    <main class="content">
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
