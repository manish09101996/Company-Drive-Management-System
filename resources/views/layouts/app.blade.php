<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - {{ config('app.name', 'Company Drive') }}</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: #3b82f6;
            --primary-hover: #2563eb;
            --sidebar-width: 260px;
            --topbar-height: 64px;
            --bg-canvas: #f8fafc;
            --card-border: #e2e8f0;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-canvas);
            color: #1e293b;
            min-height: 100vh;
        }

        /* Sidebar Styling */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1040;
            background: #0f172a;
            color: #94a3b8;
            transition: all 0.3s ease;
            overflow-y: auto;
            border-right: 1px solid #1e293b;
        }

        .sidebar-brand {
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: #ffffff;
            font-weight: 700;
            font-size: 1.15rem;
            text-decoration: none;
            border-bottom: 1px solid #1e293b;
        }

        .sidebar-brand i {
            font-size: 1.5rem;
            color: var(--primary-color);
        }

        .sidebar-menu {
            padding: 1rem 0.75rem;
            list-style: none;
            margin: 0;
        }

        .menu-header {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            padding: 0.75rem 0.75rem 0.25rem;
            font-weight: 600;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.65rem 0.85rem;
            color: #94a3b8;
            border-radius: 0.5rem;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.15s ease;
            margin-bottom: 0.2rem;
        }

        .sidebar-link:hover, .sidebar-link.active {
            background-color: #1e293b;
            color: #ffffff;
        }

        .sidebar-link.active {
            background-color: #2563eb;
            color: #ffffff;
        }

        .sidebar-link i {
            font-size: 1.15rem;
            width: 24px;
            text-align: center;
        }

        /* Main Content Layout */
        .main-wrapper {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: margin-left 0.3s ease;
        }

        .topbar {
            height: var(--topbar-height);
            background: #ffffff;
            border-bottom: 1px solid var(--card-border);
            position: sticky;
            top: 0;
            z-index: 1030;
            padding: 0 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .content-area {
            padding: 1.75rem;
            flex: 1;
        }

        /* Card Enhancements */
        .card {
            border: 1px solid var(--card-border);
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
            background: #ffffff;
        }

        .card-header {
            background: #ffffff;
            border-bottom: 1px solid var(--card-border);
            padding: 1rem 1.25rem;
            font-weight: 600;
        }

        .stat-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .badge-role {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.35em 0.65em;
            border-radius: 9999px;
        }

        /* Responsive Mobile Sidebar */
        @media (max-width: 991.98px) {
            .sidebar {
                left: -100%;
            }
            .sidebar.show {
                left: 0;
            }
            .main-wrapper {
                margin-left: 0;
            }
        }
    </style>
    @yield('styles')
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <a href="{{ route('admin.dashboard') }}" class="sidebar-brand">
            <i class="bi bi-hdd-network-fill"></i>
            <span>{{ config('app.name', 'Company Drive') }}</span>
        </a>

        <ul class="sidebar-menu">
            <li class="menu-header">Overview</li>
            <li>
                <a href="{{ route('admin.dashboard') }}" class="sidebar-link {{ request()->is('dashboard') || request()->is('/') ? 'active' : '' }}">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <li class="menu-header">Drive Storage</li>
            <li>
                <a href="{{ route('drive.index') }}" class="sidebar-link {{ request()->is('drive*') ? 'active' : '' }}">
                    <i class="bi bi-folder2-open"></i>
                    <span>Company Drive</span>
                </a>
            </li>
            <li>
                <a href="{{ route('recycle-bin.index') }}" class="sidebar-link {{ request()->is('recycle-bin*') ? 'active' : '' }}">
                    <i class="bi bi-trash3"></i>
                    <span>Recycle Bin</span>
                </a>
            </li>

            <li class="menu-header">Data Migration</li>
            <li>
                <a href="{{ route('imports.index') }}" class="sidebar-link {{ request()->is('imports*') ? 'active' : '' }}">
                    <i class="bi bi-box-arrow-in-down"></i>
                    <span>Google Takeout</span>
                </a>
            </li>

            @if(Auth::user()->isSuperAdmin() || Auth::user()->hasPermission('users.manage') || Auth::user()->hasPermission('audit.view'))
            <li class="menu-header">Administration</li>
            @if(Auth::user()->isSuperAdmin() || Auth::user()->hasPermission('users.manage'))
            <li>
                <a href="{{ route('users.index') }}" class="sidebar-link {{ request()->is('users*') ? 'active' : '' }}">
                    <i class="bi bi-people"></i>
                    <span>User Management</span>
                </a>
            </li>
            @endif

            @if(Auth::user()->isSuperAdmin() || Auth::user()->hasPermission('roles.manage'))
            <li>
                <a href="{{ route('roles.index') }}" class="sidebar-link {{ request()->is('roles*') ? 'active' : '' }}">
                    <i class="bi bi-shield-lock"></i>
                    <span>Roles & Permissions</span>
                </a>
            </li>
            @endif

            @if(Auth::user()->isSuperAdmin() || Auth::user()->hasPermission('settings.manage'))
            <li>
                <a href="{{ route('departments.index') }}" class="sidebar-link {{ request()->is('departments*') ? 'active' : '' }}">
                    <i class="bi bi-diagram-3"></i>
                    <span>Departments</span>
                </a>
            </li>
            @endif

            @if(Auth::user()->isSuperAdmin() || Auth::user()->hasPermission('audit.view'))
            <li>
                <a href="{{ route('audit.index') }}" class="sidebar-link {{ request()->is('audit-logs') ? 'active' : '' }}">
                    <i class="bi bi-journal-text"></i>
                    <span>Activity Audit</span>
                </a>
            </li>
            <li>
                <a href="{{ route('audit.logins') }}" class="sidebar-link {{ request()->is('login-events') ? 'active' : '' }}">
                    <i class="bi bi-shield-check"></i>
                    <span>Security Logins</span>
                </a>
            </li>
            @endif
            @endif
        </ul>

        <div class="p-3 mt-auto text-center border-top border-secondary-subtle" style="font-size: 0.75rem;">
            <div class="text-secondary mb-1">Hostinger Shared Ready</div>
            <span class="badge bg-success-subtle text-success border border-success-subtle">
                <i class="bi bi-check-circle me-1"></i> Private Storage
            </span>
        </div>
    </aside>

    <!-- Main Wrapper -->
    <div class="main-wrapper">
        <!-- Top Navbar -->
        <header class="topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle" type="button">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <div class="d-none d-md-flex align-items-center text-muted small">
                    <i class="bi bi-shield-lock-fill text-primary me-2"></i>
                    <span>Enterprise Private Storage &bull; End-to-End Authorized</span>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <!-- Quick Upload action in topbar -->
                <a href="{{ route('drive.index') }}" class="btn btn-sm btn-primary d-none d-sm-inline-flex align-items-center gap-1">
                    <i class="bi bi-cloud-arrow-up"></i>
                    <span>Files</span>
                </a>

                <!-- User Dropdown -->
                <div class="dropdown">
                    <button class="btn btn-sm btn-light border d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; font-size: 0.85rem;">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </div>
                        <div class="text-start d-none d-sm-block">
                            <div class="fw-semibold text-dark small" style="line-height: 1.2;">{{ Auth::user()->name }}</div>
                            <div class="text-muted" style="font-size: 0.75rem;">{{ Auth::user()->roles->first()?->name ?? 'User' }}</div>
                        </div>
                        <i class="bi bi-chevron-down text-muted small ms-1"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                        <li class="px-3 py-2 border-bottom">
                            <div class="fw-bold">{{ Auth::user()->name }}</div>
                            <div class="text-muted small">{{ Auth::user()->email }}</div>
                            <div class="mt-1">
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                    {{ Auth::user()->roles->first()?->name ?? 'User' }}
                                </span>
                            </div>
                        </li>
                        <li><a class="dropdown-item py-2" href="{{ route('profile') }}"><i class="bi bi-person me-2"></i>My Profile & Security</a></li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="dropdown-item py-2 text-danger">
                                    <i class="bi bi-box-arrow-right me-2"></i>Sign Out
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Flash Messages -->
        <div class="px-4 pt-3">
            @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                <div>{{ session('success') }}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endif

            @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div>{{ session('error') }}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endif

            @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <div class="fw-bold mb-1"><i class="bi bi-exclamation-octagon-fill me-2"></i>Please fix the following errors:</div>
                <ul class="mb-0 ps-3">
                    @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endif
        </div>

        <!-- Content Area -->
        <main class="content-area">
            @yield('content')
        </main>

        <!-- Footer -->
        <footer class="mt-auto py-3 px-4 bg-white border-top text-muted small d-flex flex-wrap justify-content-between align-items-center">
            <div>
                &copy; {{ date('Y') }} <strong>Company Drive Management System</strong>. All rights reserved.
            </div>
            <div class="d-flex align-items-center gap-3">
                <span>Storage: Private Disk</span>
                <span>PHP {{ PHP_VERSION }}</span>
                <span>Laravel {{ app()->version() }}</span>
            </div>
        </footer>
    </div>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Sidebar Toggle for Mobile
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', () => {
                sidebar.classList.toggle('show');
            });
            document.addEventListener('click', (e) => {
                if (window.innerWidth < 992 && !sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                    sidebar.classList.remove('show');
                }
            });
        }

        // Global AJAX CSRF header & Base URL setup (Supports subdirectories like /Company Drive Management System/public)
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const appBaseUrl = "{{ rtrim(url('/'), '/') }}";
        window.appUrl = function(path) {
            if (!path.startsWith('/')) path = '/' + path;
            return appBaseUrl + path;
        };
    </script>

    @yield('scripts')
</body>
</html>
