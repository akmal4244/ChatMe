<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="ChatMe — Urus chatbot AI dan soal jawab anda.">
    @php($documentTitle = trim($__env->yieldContent('title', 'Papan pemuka')))
    <title>{{ str_contains($documentTitle, 'ChatMe') ? $documentTitle : $documentTitle.' — ChatMe' }}</title>

    <link rel="icon" type="image/png" href="{{ asset('akmal3d.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Newsreader:opsz,wght@6..72,400;6..72,500;6..72,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
    @php($stylesheetVersion = substr(hash_file('sha256', public_path('css/app.css')), 0, 12))
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ $stylesheetVersion }}">
    @stack('styles')
</head>
<body class="app-body">
    <a href="#main-content" class="skip-link">Langkau ke kandungan</a>

    <aside id="sidebar" class="sidebar" aria-label="Navigasi utama" aria-hidden="true">
        <a href="{{ route('dashboard') }}" class="sidebar-header" aria-label="ChatMe, papan pemuka">
            <span class="sidebar-logo"><img src="{{ asset('akmal3d.png') }}" alt="" width="32" height="32"></span>
            <span class="sidebar-brand"><strong>ChatMe</strong><small>Chatbot AI</small></span>
        </a>

        <nav class="nav-scroll">
            <p class="nav-group-title">Utama</p>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" aria-label="Papan pemuka" @if(request()->routeIs('dashboard')) aria-current="page" @endif>
                        <i class="ph ph-gauge nav-icon" aria-hidden="true"></i>
                        <span class="nav-text">Papan pemuka</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('chatbots.index') }}" class="nav-link {{ request()->routeIs('chatbots.*', 'knowledge.*') ? 'active' : '' }}" aria-label="Chatbot saya" @if(request()->routeIs('chatbots.*', 'knowledge.*')) aria-current="page" @endif>
                        <i class="ph ph-robot nav-icon" aria-hidden="true"></i>
                        <span class="nav-text">Chatbot saya</span>
                    </a>
                </li>
            </ul>

            <p class="nav-group-title">Akaun</p>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="{{ route('subscription.plans') }}" class="nav-link {{ request()->routeIs('subscription.*') ? 'active' : '' }}" aria-label="Pelan Langganan" @if(request()->routeIs('subscription.*')) aria-current="page" @endif>
                        <i class="ph ph-crown nav-icon" aria-hidden="true"></i>
                        <span class="nav-text">Pelan Langganan</span>
                    </a>
                </li>
            </ul>

            @if(auth()->user()?->is_admin)
                <p class="nav-group-title">Pentadbir</p>
                <ul class="nav-list">
                    <li class="nav-item">
                        <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" aria-label="Panel Pentadbir" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>
                            <i class="ph ph-shield-check nav-icon" aria-hidden="true"></i>
                            <span class="nav-text">Panel pentadbir <span class="nav-badge-admin">Pentadbir</span></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.users') }}" class="nav-link {{ request()->routeIs('admin.users') ? 'active' : '' }}" aria-label="Urus Pengguna" @if(request()->routeIs('admin.users')) aria-current="page" @endif>
                            <i class="ph ph-users nav-icon" aria-hidden="true"></i>
                            <span class="nav-text">Urus pengguna <span class="nav-badge-admin">Pentadbir</span></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.chatbots') }}" class="nav-link {{ request()->routeIs('admin.chatbots') ? 'active' : '' }}" aria-label="Semua Chatbot" @if(request()->routeIs('admin.chatbots')) aria-current="page" @endif>
                            <i class="ph ph-chats-circle nav-icon" aria-hidden="true"></i>
                            <span class="nav-text">Semua chatbot <span class="nav-badge-admin">Pentadbir</span></span>
                        </a>
                    </li>
                </ul>
            @endif
        </nav>
    </aside>
    <button type="button" class="sidebar-overlay" id="sidebar-overlay" aria-label="Tutup navigasi" aria-hidden="true" tabindex="-1" disabled></button>

    <div class="app-shell" id="app-shell">
        <header class="topbar">
            <button type="button" id="toggle-sidebar" class="icon-button" aria-label="Buka atau tutup navigasi" aria-controls="sidebar" aria-expanded="false">
                <i class="ph ph-list" aria-hidden="true"></i>
            </button>
            <span class="topbar-title">@yield('page-title', 'Papan pemuka')</span>

            <div class="user-menu">
                <button type="button" class="user-btn" id="user-btn" aria-label="Menu akaun untuk {{ auth()->user()->name ?? 'Pengguna' }}" aria-expanded="false" aria-controls="user-dropdown">
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
                        <li><a href="{{ route('dashboard') }}"><i class="ph ph-gauge" aria-hidden="true"></i>Papan pemuka</a></li>
                        <li><a href="{{ route('subscription.plans') }}"><i class="ph ph-crown" aria-hidden="true"></i>Pelan Langganan</a></li>
                        @if(auth()->user()?->is_admin)
                            <li><a href="{{ route('admin.dashboard') }}"><i class="ph ph-shield-check" aria-hidden="true"></i>Panel Pentadbir</a></li>
                        @endif
                    </ul>
                    <div class="user-dropdown-divider"></div>
                    <form method="POST" action="{{ route('logout') }}" id="form-logout-dropdown">
                        @csrf
                        <button type="button" class="dropdown-danger" id="logout-trigger"><i class="ph ph-sign-out" aria-hidden="true"></i>Log keluar</button>
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
            <div class="modal-body"><h2 id="logout-modal-title">Log keluar</h2><p id="logout-modal-description">Adakah anda pasti mahu log keluar? Sesi anda akan ditamatkan.</p></div>
            <div class="modal-actions">
                <button type="button" class="modal-cancel" data-close-modal="logout-modal">Batal</button>
                <button type="button" class="modal-confirm" id="logout-confirm">Log keluar</button>
            </div>
        </section>
    </div>

    <div class="modal-backdrop" id="confirm-modal" hidden>
        <section class="modal-box" role="dialog" aria-modal="true" aria-labelledby="modal-title" aria-describedby="modal-desc">
            <div class="modal-icon-wrap"><i id="modal-icon" class="ph ph-warning" aria-hidden="true"></i></div>
            <div class="modal-body"><h2 id="modal-title">Sahkan tindakan</h2><p id="modal-desc">Adakah anda pasti mahu meneruskan?</p></div>
            <div class="modal-actions">
                <button type="button" class="modal-cancel" data-close-modal="confirm-modal">Batal</button>
                <button type="button" id="modal-confirm-btn" class="modal-confirm">Teruskan</button>
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
        let sidebarReturnFocus = null;

        const isMobile = () => window.matchMedia('(max-width: 1024px)').matches;
        const setSidebarState = (open, manageFocus = false) => {
            if (isMobile()) {
                const wasOpen = sidebar.classList.contains('mobile-open');
                sidebar.classList.toggle('mobile-open', open);
                overlay.classList.toggle('visible', open);
                sidebar.inert = !open;
                appShell.inert = open;
                document.querySelector('.skip-link').inert = open;
                sidebar.setAttribute('aria-hidden', open ? 'false' : 'true');
                overlay.disabled = !open;
                overlay.setAttribute('aria-hidden', open ? 'false' : 'true');
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');

                if (manageFocus && open && !wasOpen) {
                    sidebarReturnFocus = document.activeElement;
                    requestAnimationFrame(() => sidebar.querySelector('a[href]')?.focus());
                } else if (manageFocus && !open && wasOpen) {
                    (sidebarReturnFocus || toggle)?.focus();
                    sidebarReturnFocus = null;
                }

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
        sidebar.inert = isMobile();
        sidebar.setAttribute('aria-hidden', isMobile() ? 'true' : 'false');
        toggle?.setAttribute('aria-expanded', !isMobile() && !collapsed ? 'true' : 'false');

        window.addEventListener('resize', () => {
            if (isMobile()) {
                setSidebarState(sidebar.classList.contains('mobile-open'));
            } else {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('visible');
                sidebar.inert = false;
                appShell.inert = false;
                document.querySelector('.skip-link').inert = false;
                sidebar.setAttribute('aria-hidden', 'false');
                overlay.disabled = true;
                overlay.setAttribute('aria-hidden', 'true');
                toggle?.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            }
        });

        toggle?.addEventListener('click', () => setSidebarState(isMobile() ? !sidebar.classList.contains('mobile-open') : collapsed, true));
        overlay?.addEventListener('click', () => setSidebarState(false, true));
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
            appShell.inert = false;
            sidebar.inert = isMobile() && !sidebar.classList.contains('mobile-open');
            document.querySelector('.skip-link').inert = false;
            lastFocused?.focus();
        };
        const openModal = (modal) => {
            if (!modal) return;
            lastFocused = userDropdown?.contains(document.activeElement) ? userButton : document.activeElement;
            closeUserMenu();
            modal.hidden = false;
            document.body.classList.add('modal-open');
            appShell.inert = true;
            sidebar.inert = true;
            document.querySelector('.skip-link').inert = true;
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
                if (isMobile() && sidebar.classList.contains('mobile-open')) {
                    setSidebarState(false, true);
                }
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
            document.getElementById('modal-title').textContent = config.title || 'Sahkan tindakan';
            document.getElementById('modal-desc').textContent = config.desc || 'Adakah anda pasti mahu meneruskan?';
            document.getElementById('modal-icon').className = config.type === 'danger' ? 'ph ph-warning' : 'ph ph-question';
            confirmButton.textContent = config.confirmText || 'Teruskan';
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
