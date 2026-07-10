<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="ChatMe — Urus chatbot AI dan pengetahuan anda.">
    <title>@yield('title', 'Papan Pemuka') &mdash; ChatMe</title>

    <link rel="icon" type="image/png" href="{{ asset('akmal3d.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Newsreader:opsz,wght@6..72,400;6..72,500;6..72,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('styles')
</head>
<body class="app-body">
    <a href="#main-content" class="skip-link">Langkau ke kandungan</a>

    <aside id="sidebar" class="sidebar" aria-label="Navigasi utama">
        <a href="{{ route('dashboard') }}" class="sidebar-header" aria-label="ChatMe, papan pemuka">
            <span class="sidebar-logo"><img src="{{ asset('akmal3d.png') }}" alt="" width="32" height="32"></span>
            <span class="sidebar-brand"><strong>ChatMe</strong><small>Chatbot AI</small></span>
        </a>

        <nav class="nav-scroll">
            <p class="nav-group-title">Utama</p>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif>
                        <i class="ph ph-gauge nav-icon" aria-hidden="true"></i>
                        <span class="nav-text">Papan Pemuka</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('chatbots.index') }}" class="nav-link {{ request()->routeIs('chatbots.*', 'knowledge.*') ? 'active' : '' }}" @if(request()->routeIs('chatbots.*', 'knowledge.*')) aria-current="page" @endif>
                        <i class="ph ph-robot nav-icon" aria-hidden="true"></i>
                        <span class="nav-text">Chatbot Saya</span>
                    </a>
                </li>
            </ul>

            <p class="nav-group-title">Akaun</p>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="{{ route('subscription.plans') }}" class="nav-link {{ request()->routeIs('subscription.*') ? 'active' : '' }}" @if(request()->routeIs('subscription.*')) aria-current="page" @endif>
                        <i class="ph ph-crown nav-icon" aria-hidden="true"></i>
                        <span class="nav-text">Pelan Langganan</span>
                    </a>
                </li>
            </ul>

            @if(auth()->user()?->is_admin)
                <p class="nav-group-title">Pentadbir</p>
                <ul class="nav-list">
                    <li class="nav-item">
                        <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>
                            <i class="ph ph-shield-check nav-icon" aria-hidden="true"></i>
                            <span class="nav-text">Panel Pentadbir <span class="nav-badge-admin">Admin</span></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.users') }}" class="nav-link {{ request()->routeIs('admin.users') ? 'active' : '' }}" @if(request()->routeIs('admin.users')) aria-current="page" @endif>
                            <i class="ph ph-users nav-icon" aria-hidden="true"></i>
                            <span class="nav-text">Urus Pengguna <span class="nav-badge-admin">Admin</span></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.chatbots') }}" class="nav-link {{ request()->routeIs('admin.chatbots') ? 'active' : '' }}" @if(request()->routeIs('admin.chatbots')) aria-current="page" @endif>
                            <i class="ph ph-chats-circle nav-icon" aria-hidden="true"></i>
                            <span class="nav-text">Semua Chatbot <span class="nav-badge-admin">Admin</span></span>
                        </a>
                    </li>
                </ul>
            @endif
        </nav>
    </aside>
    <button type="button" class="sidebar-overlay" id="sidebar-overlay" aria-label="Tutup navigasi" tabindex="-1"></button>

    <div class="app-shell" id="app-shell">
        <header class="topbar">
            <button type="button" id="toggle-sidebar" class="icon-button" aria-label="Buka atau tutup navigasi" aria-controls="sidebar" aria-expanded="false">
                <i class="ph ph-list" aria-hidden="true"></i>
            </button>
            <span class="topbar-title">@yield('page-title', 'Papan Pemuka')</span>

            <div class="user-menu">
                <button type="button" class="user-btn" id="user-btn" aria-expanded="false" aria-controls="user-dropdown">
                    <span class="user-avatar-sm" aria-hidden="true">{{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}</span>
                    <span class="user-btn-name">{{ auth()->user()->name ?? 'Pengguna' }}</span>
                    <i class="ph ph-caret-down user-btn-chevron" aria-hidden="true"></i>
                </button>
                <div class="user-dropdown" id="user-dropdown" hidden>
                    <div class="user-dropdown-header">
                        <strong>{{ auth()->user()->name ?? 'Pengguna' }}</strong>
                        <span>{{ auth()->user()->email ?? '' }}</span>
                        <small>{{ auth()->user()?->is_admin ? 'Pentadbir' : 'Pengguna' }}</small>
                    </div>
                    <ul>
                        <li><a href="{{ route('dashboard') }}"><i class="ph ph-gauge" aria-hidden="true"></i>Papan Pemuka</a></li>
                        <li><a href="{{ route('subscription.plans') }}"><i class="ph ph-crown" aria-hidden="true"></i>Pelan Langganan</a></li>
                        @if(auth()->user()?->is_admin)
                            <li><a href="{{ route('admin.dashboard') }}"><i class="ph ph-shield-check" aria-hidden="true"></i>Panel Pentadbir</a></li>
                        @endif
                    </ul>
                    <div class="user-dropdown-divider"></div>
                    <form method="POST" action="{{ route('logout') }}" id="form-logout-dropdown">
                        @csrf
                        <button type="button" class="dropdown-danger" id="logout-trigger"><i class="ph ph-sign-out" aria-hidden="true"></i>Log Keluar</button>
                    </form>
                </div>
            </div>
        </header>

        <div class="flash-region" aria-live="polite">
            @if(session('success'))
                <div class="flash flash-success" role="status"><i class="ph ph-check-circle" aria-hidden="true"></i><span>{{ session('success') }}</span><button type="button" class="flash-close" aria-label="Tutup mesej">&times;</button></div>
            @endif
            @if(session('error'))
                <div class="flash flash-error" role="alert"><i class="ph ph-x-circle" aria-hidden="true"></i><span>{{ session('error') }}</span><button type="button" class="flash-close" aria-label="Tutup mesej">&times;</button></div>
            @endif
            @if(session('info'))
                <div class="flash flash-info" role="status"><i class="ph ph-info" aria-hidden="true"></i><span>{{ session('info') }}</span><button type="button" class="flash-close" aria-label="Tutup mesej">&times;</button></div>
            @endif
        </div>

        <main class="page-content" id="main-content" tabindex="-1">@yield('content')</main>
        <footer class="app-footer">&copy; {{ date('Y') }} ChatMe &mdash; Kuala Lumpur, Malaysia</footer>
    </div>

    <div id="toast-container" aria-live="polite" aria-atomic="true"></div>

    <div class="modal-backdrop" id="logout-modal" hidden>
        <section class="modal-box" role="dialog" aria-modal="true" aria-labelledby="logout-modal-title" aria-describedby="logout-modal-description">
            <div class="modal-icon-wrap"><i class="ph ph-sign-out" aria-hidden="true"></i></div>
            <div class="modal-body"><h2 id="logout-modal-title">Log Keluar</h2><p id="logout-modal-description">Sesi anda akan ditamatkan.</p></div>
            <div class="modal-actions">
                <button type="button" class="modal-cancel" data-close-modal="logout-modal">Batal</button>
                <button type="button" class="modal-confirm" id="logout-confirm">Log Keluar</button>
            </div>
        </section>
    </div>

    <div class="modal-backdrop" id="confirm-modal" hidden>
        <section class="modal-box" role="dialog" aria-modal="true" aria-labelledby="modal-title" aria-describedby="modal-desc">
            <div class="modal-icon-wrap"><i id="modal-icon" class="ph ph-warning" aria-hidden="true"></i></div>
            <div class="modal-body"><h2 id="modal-title">Sahkan</h2><p id="modal-desc">Teruskan?</p></div>
            <div class="modal-actions">
                <button type="button" class="modal-cancel" data-close-modal="confirm-modal">Batal</button>
                <button type="button" id="modal-confirm-btn" class="modal-confirm">Ya</button>
            </div>
        </section>
    </div>

    <script>
    (() => {
        const sidebar = document.getElementById('sidebar');
        const appShell = document.getElementById('app-shell');
        const overlay = document.getElementById('sidebar-overlay');
        const toggle = document.getElementById('toggle-sidebar');
        const userButton = document.getElementById('user-btn');
        const userDropdown = document.getElementById('user-dropdown');
        let collapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        let lastFocused = null;

        const isMobile = () => window.matchMedia('(max-width: 1024px)').matches;
        const setSidebarState = (open) => {
            if (isMobile()) {
                sidebar.classList.toggle('mobile-open', open);
                overlay.classList.toggle('visible', open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                return;
            }
            collapsed = !open;
            sidebar.classList.toggle('collapsed', collapsed);
            appShell.classList.toggle('collapsed', collapsed);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            localStorage.setItem('sidebarCollapsed', collapsed ? 'true' : 'false');
        };

        if (!isMobile() && collapsed) {
            sidebar.classList.add('collapsed');
            appShell.classList.add('collapsed');
        }
        toggle?.setAttribute('aria-expanded', !isMobile() && !collapsed ? 'true' : 'false');

        toggle?.addEventListener('click', () => setSidebarState(isMobile() ? !sidebar.classList.contains('mobile-open') : collapsed));
        overlay?.addEventListener('click', () => setSidebarState(false));
        sidebar?.querySelectorAll('a[href]').forEach((link) => link.addEventListener('click', () => {
            if (isMobile()) setSidebarState(false);
        }));

        const closeUserMenu = () => {
            userDropdown.hidden = true;
            userButton?.setAttribute('aria-expanded', 'false');
        };
        userButton?.addEventListener('click', (event) => {
            event.stopPropagation();
            const opening = userDropdown.hidden;
            userDropdown.hidden = !opening;
            userButton.setAttribute('aria-expanded', opening ? 'true' : 'false');
        });
        document.addEventListener('click', closeUserMenu);
        userDropdown?.addEventListener('click', (event) => event.stopPropagation());

        const closeModal = (modal) => {
            if (!modal) return;
            modal.hidden = true;
            document.body.classList.remove('modal-open');
            lastFocused?.focus();
        };
        const openModal = (modal) => {
            if (!modal) return;
            lastFocused = document.activeElement;
            modal.hidden = false;
            document.body.classList.add('modal-open');
            modal.querySelector('button')?.focus();
        };

        document.querySelectorAll('[data-close-modal]').forEach((button) => button.addEventListener('click', () => closeModal(document.getElementById(button.dataset.closeModal))));
        document.querySelectorAll('.modal-backdrop').forEach((modal) => modal.addEventListener('click', (event) => {
            if (event.target === modal) closeModal(modal);
        }));
        document.addEventListener('keydown', (event) => {
            const activeModal = document.querySelector('.modal-backdrop:not([hidden])');

            if (event.key === 'Escape') {
                closeUserMenu();
                if (activeModal) closeModal(activeModal);
                return;
            }

            if (event.key !== 'Tab' || !activeModal) return;

            const focusable = [...activeModal.querySelectorAll('button:not([disabled]), a[href], input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])')];
            if (focusable.length === 0) return;

            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });

        document.getElementById('logout-trigger')?.addEventListener('click', () => openModal(document.getElementById('logout-modal')));
        document.getElementById('logout-confirm')?.addEventListener('click', () => document.getElementById('form-logout-dropdown')?.submit());
        document.querySelectorAll('.flash-close').forEach((button) => button.addEventListener('click', () => button.closest('.flash')?.remove()));

        window.sahkan = (config = {}) => {
            const modal = document.getElementById('confirm-modal');
            const confirmButton = document.getElementById('modal-confirm-btn');
            document.getElementById('modal-title').textContent = config.title || 'Sahkan';
            document.getElementById('modal-desc').textContent = config.desc || 'Teruskan?';
            document.getElementById('modal-icon').className = config.type === 'danger' ? 'ph ph-warning' : 'ph ph-question';
            confirmButton.textContent = config.confirmText || 'Ya';
            confirmButton.className = config.type === 'danger' ? 'modal-confirm-danger' : 'modal-confirm';
            confirmButton.onclick = () => {
                closeModal(modal);
                config.onConfirm?.();
            };
            openModal(modal);
        };
        window.closeConfirm = () => closeModal(document.getElementById('confirm-modal'));
        window.confirmLogout = () => openModal(document.getElementById('logout-modal'));
        window.handleLogout = () => document.getElementById('form-logout-dropdown')?.submit();
        window.showToast = (message, type = 'success') => {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.textContent = message;
            document.getElementById('toast-container')?.appendChild(toast);
            setTimeout(() => toast.remove(), 3500);
        };
    })();
    </script>
    @stack('scripts')
</body>
</html>
