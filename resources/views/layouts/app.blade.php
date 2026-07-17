<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Jurnal Kelas') - Jurnal Kelas</title>

    {{-- Google Font Inter --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    {{-- Bootstrap Icons --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    @vite(['resources/sass/app.scss', 'resources/js/app.js'])

    <style>
        :root {
            --sidebar-width: 250px;
            --sidebar-bg-start: #1d3557;
            --sidebar-bg-end: #0f1d33;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f6f9;
        }

        /* ── Sidebar ── */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, var(--sidebar-bg-start) 0%, var(--sidebar-bg-end) 100%);
            z-index: 1040;
            transition: transform 0.3s ease;
            overflow-y: auto;
        }

        .sidebar-brand {
            padding: 1.5rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-brand .brand-icon {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.15);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: #fff;
        }

        .sidebar-brand h5 {
            color: #fff;
            margin: 0;
            font-weight: 700;
            font-size: 1.15rem;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .sidebar-nav .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.7rem 1.25rem;
            color: rgba(255,255,255,0.6);
            font-size: 0.9rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }

        .sidebar-nav .nav-link:hover {
            color: #fff;
            background: rgba(255,255,255,0.08);
        }

        .sidebar-nav .nav-link.active {
            color: #fff;
            background: rgba(255,255,255,0.1);
            border-left-color: #4cc9f0;
        }

        .sidebar-nav .nav-link i {
            font-size: 1.15rem;
            width: 24px;
            text-align: center;
        }

        .sidebar-divider {
            border-top: 1px solid rgba(255,255,255,0.1);
            margin: 0.5rem 1.25rem;
        }

        .sidebar-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 0.5rem 0;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        /* ── Main Content ── */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }

        .top-navbar {
            background: #fff;
            padding: 0.85rem 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1030;
        }

        .top-navbar .page-title {
            font-size: 1.15rem;
            font-weight: 600;
            color: #1d3557;
            margin: 0;
        }

        .top-navbar .user-dropdown .btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            color: #495057;
        }

        .top-navbar .user-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: #4361ee;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .content-wrapper {
            padding: 1.5rem;
        }

        /* ── Mobile toggle ── */
        .sidebar-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.4rem;
            color: #1d3557;
            cursor: pointer;
            padding: 0;
            margin-right: 0.75rem;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1035;
        }

        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .sidebar-overlay.show {
                display: block;
            }

            .main-content {
                margin-left: 0;
            }

            .sidebar-toggle {
                display: inline-block;
            }
        }
    </style>

    @stack('styles')
</head>
<body>

    {{-- Sidebar Overlay (mobile) --}}
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    {{-- Sidebar --}}
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">
                <i class="bi bi-journal-bookmark-fill"></i>
            </div>
            <h5>Jurnal Kelas</h5>
        </div>

        <nav class="sidebar-nav">
            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
            <a href="{{ route('kelas.index') }}" class="nav-link {{ request()->routeIs('kelas.*') ? 'active' : '' }}">
                <i class="bi bi-building"></i>
                <span>Kelas</span>
            </a>
            <a href="{{ route('mata-pelajaran.index') }}" class="nav-link {{ request()->routeIs('mata-pelajaran.*') ? 'active' : '' }}">
                <i class="bi bi-book"></i>
                <span>Mata Pelajaran</span>
            </a>
            <a href="{{ route('jadwal.index') }}" class="nav-link {{ request()->routeIs('jadwal.*') ? 'active' : '' }}">
                <i class="bi bi-calendar3"></i>
                <span>Jadwal</span>
            </a>
            <a href="{{ route('jurnal.index') }}" class="nav-link {{ request()->routeIs('jurnal.*') ? 'active' : '' }}">
                <i class="bi bi-journal-text"></i>
                <span>Jurnal</span>
            </a>
            <a href="{{ route('presensi.index') }}" class="nav-link {{ request()->routeIs('presensi.*') ? 'active' : '' }}">
                <i class="bi bi-person-check"></i>
                <span>Presensi</span>
            </a>

            <div class="sidebar-divider"></div>
        </nav>

        <div class="sidebar-footer">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="nav-link w-100 text-start border-0 bg-transparent">
                    <i class="bi bi-box-arrow-left"></i>
                    <span>Logout</span>
                </button>
            </form>
        </div>
    </aside>

    {{-- Main Content --}}
    <div class="main-content">
        <header class="top-navbar">
            <div class="d-flex align-items-center">
                <button class="sidebar-toggle" id="sidebarToggle" type="button">
                    <i class="bi bi-list"></i>
                </button>
                <h1 class="page-title">@yield('title', 'Dashboard')</h1>
            </div>

            <div class="user-dropdown dropdown">
                <button class="btn btn-link text-decoration-none dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="user-avatar">
                        {{ strtoupper(substr(Auth::user()->name ?? 'U', 0, 1)) }}
                    </div>
                    <span class="d-none d-md-inline">{{ Auth::user()->name ?? 'User' }}</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-item-text text-muted small">{{ Auth::user()->email ?? '' }}</span></li>
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
        </header>

        <main class="content-wrapper">
            @yield('content')
        </main>
    </div>

    <script>
        // Sidebar toggle for mobile
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', () => {
                sidebar.classList.toggle('show');
                sidebarOverlay.classList.toggle('show');
            });
        }

        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', () => {
                sidebar.classList.remove('show');
                sidebarOverlay.classList.remove('show');
            });
        }
    </script>

    @stack('scripts')
</body>
</html>
