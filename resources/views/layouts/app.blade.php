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
                    <a href="{{ route('profile.edit') }}" class="nav-link {{ request()->routeIs('profile.*') ? 'active' : '' }}" aria-label="Profil akaun" @if(request()->routeIs('profile.*')) aria-current="page" @endif>
                        <i class="ph ph-user-circle nav-icon" aria-hidden="true"></i>
                        <span class="nav-text">Profil akaun</span>
                    </a>
                </li>
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
                        <li><a href="{{ route('profile.edit') }}"><i class="ph ph-user-circle" aria-hidden="true"></i>Profil akaun</a></li>
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

        <main class="page-content" id="main-content" tabindex="-1">@yield('content')</main>
        <footer class="app-footer">&copy; {{ date('Y') }} ChatMe &mdash; Kuala Lumpur, Malaysia</footer>
    </div>

    @stack('modals')
    <div
        id="session-expiry-config"
        data-expires-at="{{ now()->addMinutes((int) config('session.lifetime'))->timestamp }}"
        data-warning-seconds="300"
        data-login-url="{{ route('login', ['session_expired' => 1]) }}"
        data-header-name="X-Session-Expires-At"
        hidden
    ></div>
    @include('partials.toasts')

    <script nonce="{{ Vite::cspNonce() }}">
    (() => {
        const sessionConfig = document.getElementById('session-expiry-config');
        if (!sessionConfig || typeof window.showToast !== 'function') return;

        let expiresAt = Number(sessionConfig.dataset.expiresAt) * 1000;
        const warningSeconds = Number(sessionConfig.dataset.warningSeconds);
        const loginUrl = sessionConfig.dataset.loginUrl;
        const headerName = sessionConfig.dataset.headerName;
        let warningToast = null;
        let warningDismissed = false;
        let redirectStarted = false;

        const removeWarningToast = () => {
            warningToast?.remove();
            warningToast = null;
            warningDismissed = false;
        };

        const updateSessionCountdown = () => {
            if (!Number.isFinite(expiresAt) || !Number.isFinite(warningSeconds) || !loginUrl) return;

            const remainingSeconds = Math.max(0, Math.ceil((expiresAt - Date.now()) / 1000));
            if (remainingSeconds === 0) {
                if (!redirectStarted) {
                    redirectStarted = true;
                    window.location.replace(loginUrl);
                }
                return;
            }

            if (remainingSeconds > warningSeconds) return;

            if (warningToast && !warningToast.isConnected) {
                warningToast = null;
                warningDismissed = true;
            }

            if (!warningToast && !warningDismissed) {
                warningToast = window.showToast('Sesi anda akan tamat tidak lama lagi.', 'info', { duration: 0 });
            }

            const minutes = Math.floor(remainingSeconds / 60);
            const seconds = String(remainingSeconds % 60).padStart(2, '0');
            const message = warningToast?.querySelector('.toast-message');
            if (message) message.textContent = `Sesi anda akan tamat dalam ${String(minutes).padStart(2, '0')}:${seconds}.`;
        };

        const originalFetch = window.fetch.bind(window);
        window.fetch = async (...args) => {
            const response = await originalFetch(...args);
            const input = args[0];
            const requestTarget = input instanceof Request ? input.url : String(input);
            const requestUrl = new URL(requestTarget, window.location.href);

            if (requestUrl.origin === window.location.origin && response.ok) {
                const nextDeadline = Number(response.headers.get(headerName));
                if (Number.isFinite(nextDeadline) && nextDeadline > 0) {
                    expiresAt = nextDeadline * 1000;
                    sessionConfig.dataset.expiresAt = String(nextDeadline);
                    removeWarningToast();
                    updateSessionCountdown();
                }
            }

            return response;
        };

        updateSessionCountdown();
        window.setInterval(updateSessionCountdown, 1000);
    })();
    </script>

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

    <script nonce="{{ Vite::cspNonce() }}">
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

        const closeUserMenu = (restoreFocus = false) => {
            const wasOpen = userDropdown && !userDropdown.hidden;
            userDropdown.hidden = true;
            userButton?.setAttribute('aria-expanded', 'false');
            if (restoreFocus && wasOpen) userButton?.focus();
        };
        userButton?.addEventListener('click', (event) => {
            event.stopPropagation();
            const opening = userDropdown.hidden;
            userDropdown.hidden = !opening;
            userButton.setAttribute('aria-expanded', opening ? 'true' : 'false');
        });
        document.addEventListener('click', () => closeUserMenu(false));
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
            closeUserMenu(false);
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
                closeUserMenu(true);
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
        window.sahkan = (config = {}) => {
            const modal = document.getElementById('confirm-modal');
            const confirmButton = document.getElementById('modal-confirm-btn');
            document.getElementById('modal-title').textContent = config.title || 'Sahkan tindakan';
            document.getElementById('modal-desc').textContent = config.desc || 'Adakah anda pasti mahu meneruskan?';
            document.getElementById('modal-icon').className = config.type === 'danger' ? 'ph ph-warning' : 'ph ph-question';
            confirmButton.textContent = config.confirmText || 'Teruskan';
            confirmButton.className = config.type === 'danger' ? 'modal-confirm-danger' : 'modal-confirm';
            confirmButton.disabled = false;
            confirmButton.onclick = () => {
                if (confirmButton.disabled) return;
                confirmButton.disabled = true;
                closeModal(modal);
                config.onConfirm?.();
            };
            openModal(modal);
        };

        document.querySelectorAll('form[data-submit-loading]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                const submitButton = event.submitter instanceof HTMLButtonElement
                    ? event.submitter
                    : form.querySelector('button[type="submit"]');
                if (!submitButton) return;
                submitButton.disabled = true;
                submitButton.setAttribute('aria-disabled', 'true');
            });
        });

        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || !form.matches('form[data-confirm-title]')) return;
            if (form.dataset.confirmed === 'true') {
                delete form.dataset.confirmed;
                return;
            }

            event.preventDefault();
            window.sahkan({
                title: form.dataset.confirmTitle,
                desc: form.dataset.confirmDescription,
                confirmText: form.dataset.confirmText,
                type: form.dataset.confirmType,
                onConfirm: () => {
                    form.dataset.confirmed = 'true';
                    form.requestSubmit();
                },
            });
        });

        window.closeConfirm = () => closeModal(document.getElementById('confirm-modal'));
        window.confirmLogout = () => openModal(document.getElementById('logout-modal'));
        window.handleLogout = () => document.getElementById('form-logout-dropdown')?.submit();
    })();
    </script>
    @stack('scripts')
</body>
</html>
