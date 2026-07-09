<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Papan Pemuka') &mdash; ChatMe</title>

    <meta name="description" content="ChatMe — Cipta Chatbot AI Custom untuk Laman Web Anda.">
    <meta property="og:title" content="ChatMe — Cipta Chatbot AI Custom untuk Laman Web Anda">
    <meta property="og:description" content="Platform SaaS buatan Malaysia. Latih chatbot dengan pengetahuan sendiri.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://chatme.akmalmarvis.com">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="icon" type="image/png" href="{{ asset('akmal3d.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Newsreader:opsz,wght@6..72,300;6..72,400;6..72,500;6..72,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    {{-- Phosphor Icons (Regular/Light weight) --}}
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">

    <link rel="stylesheet" href="{{ asset('css/app.css') }}">

    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        html{height:100%;scroll-behavior:smooth;overflow-x:hidden}
        body{
            height:100%;font-family:'Plus Jakarta Sans','Switzer',sans-serif;
            background:#050505;color:#fff;
            font-size:14px;line-height:1.6;
            -webkit-font-smoothing:antialiased;
            letter-spacing:-0.01em;overflow-x:hidden;
        }

        /* ══ SIDEBAR — Dark Glass ════════════════════════════ */
        .sidebar{
            width:240px;background:rgba(255,255,255,0.02);
            border-right:1px solid rgba(255,255,255,0.05);
            display:flex;flex-direction:column;height:100vh;
            position:fixed;top:0;left:0;z-index:40;
            transition:width 0.35s cubic-bezier(0.32,0.72,0,1);overflow:hidden;
        }
        .sidebar.collapsed{width:64px}
        .sidebar.collapsed .nav-text,
        .sidebar.collapsed .nav-group-title,
        .sidebar.collapsed .sidebar-name,
        .sidebar.collapsed .sidebar-sub{display:none}
        .sidebar.collapsed .sidebar-header{padding:10px 8px;justify-content:center}
        .sidebar.collapsed .nav-link{justify-content:center;padding:10px}

        .sidebar-header{
            display:flex;align-items:center;gap:10px;
            padding:20px 16px;border-bottom:1px solid rgba(255,255,255,0.04);
            min-height:64px;flex-shrink:0;
        }
        .sidebar-logo{width:32px;height:32px;flex-shrink:0}
        .sidebar-logo img{width:32px;height:32px;border-radius:6px}
        .sidebar-name{font-size:14px;font-weight:600;color:#fff;white-space:nowrap;line-height:1.2;font-family:'Newsreader',serif;letter-spacing:-0.02em}
        .sidebar-sub{font-size:10px;font-weight:500;color:rgba(255,255,255,0.25);white-space:nowrap;text-transform:uppercase;letter-spacing:0.06em}

        .nav-scroll{flex:1;overflow-y:auto;padding:12px 8px}
        .nav-scroll::-webkit-scrollbar{width:3px}
        .nav-scroll::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.06);border-radius:99px}
        .nav-list{list-style:none}.nav-item{margin-bottom:1px}
        .nav-group-title{padding:20px 12px 6px;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:rgba(255,255,255,0.2);white-space:nowrap}
        .nav-link{
            display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:10px;
            color:rgba(255,255,255,0.4);font-size:13px;font-weight:500;
            text-decoration:none;transition:all 0.3s cubic-bezier(0.32,0.72,0,1);
            white-space:nowrap;overflow:hidden;cursor:pointer;border:none;background:none;width:100%;
        }
        .nav-link:hover{background:rgba(255,255,255,0.04);color:rgba(255,255,255,0.8)}
        .nav-link.active{background:rgba(255,255,255,0.06);color:#fff;font-weight:600;box-shadow:inset 3px 0 0 #fff}
        .nav-icon{width:18px;text-align:center;flex-shrink:0;font-size:16px;display:flex;align-items:center;justify-content:center}
        .nav-text{flex:1;min-width:0;display:flex;align-items:center;gap:5px;overflow:hidden}
        .nav-text-label{flex-shrink:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .nav-badge-admin{
            flex-shrink:0;font-size:8px;font-weight:700;background:rgba(255,255,255,0.1);
            color:rgba(255,255,255,0.6);padding:2px 6px;border-radius:99px;text-transform:uppercase;letter-spacing:0.04em;
        }

        /* ══ TOPBAR ══════════════════════════════════════════ */
        .topbar{
            height:52px;background:rgba(5,5,5,0.85);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);
            border-bottom:1px solid rgba(255,255,255,0.04);
            display:flex;align-items:center;padding:0 20px;gap:12px;
            position:sticky;top:0;z-index:30;
        }
        #toggle-sidebar{
            width:34px;height:34px;border-radius:10px;
            display:flex;align-items:center;justify-content:center;
            background:transparent;border:none;cursor:pointer;
            color:rgba(255,255,255,0.4);transition:all 0.3s cubic-bezier(0.32,0.72,0,1);
        }
        #toggle-sidebar:hover{background:rgba(255,255,255,0.04);color:#fff}
        .topbar-title{flex:1;min-width:0;font-size:14px;font-weight:600;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .topbar-actions{display:flex;align-items:center;gap:8px;flex-shrink:0}

        .user-btn{display:flex;align-items:center;gap:8px;padding:4px 10px 4px 4px;border-radius:10px;background:transparent;cursor:pointer;transition:all 0.3s;border:none;}
        .user-btn:hover{background:rgba(255,255,255,0.04)}
        .user-avatar-sm{width:28px;height:28px;border-radius:8px;background:#fff;color:#050505;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;}
        .user-btn-name{font-size:13px;font-weight:500;color:#fff;max-width:110px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .user-btn-chevron{color:rgba(255,255,255,0.3);font-size:10px;transition:transform 0.3s}
        .user-btn.open .user-btn-chevron{transform:rotate(180deg)}

        .user-dropdown{
            position:absolute;top:calc(100% + 4px);right:0;width:220px;
            background:rgba(20,20,20,0.95);backdrop-filter:blur(30px);-webkit-backdrop-filter:blur(30px);
            border:1px solid rgba(255,255,255,0.08);border-radius:16px;
            box-shadow:0 8px 40px rgba(0,0,0,0.5);z-index:200;display:none;overflow:hidden;
        }
        .user-dropdown.open{display:block;animation:slideIn 0.2s cubic-bezier(0.32,0.72,0,1)}
        .user-dropdown-header{padding:14px 16px 10px;border-bottom:1px solid rgba(255,255,255,0.05)}
        .user-dropdown-header p{font-size:13px;font-weight:600;color:#fff}
        .user-dropdown-header span{font-size:11px;color:rgba(255,255,255,0.3)}
        .user-dropdown-role{
            display:inline-flex;align-items:center;gap:4px;font-size:9px;font-weight:600;
            padding:2px 8px;border-radius:99px;background:rgba(255,255,255,0.08);color:rgba(255,255,255,0.5);
            margin-top:6px;text-transform:uppercase;letter-spacing:0.05em;
        }
        .user-dropdown ul{list-style:none;padding:4px}
        .user-dropdown ul li a,.user-dropdown ul li button{
            display:flex;align-items:center;gap:9px;width:100%;padding:8px 12px;border-radius:8px;
            font-size:13px;color:rgba(255,255,255,0.5);text-decoration:none;background:none;border:none;cursor:pointer;transition:all 0.15s;
        }
        .user-dropdown ul li a:hover,.user-dropdown ul li button:hover{background:rgba(255,255,255,0.04);color:#fff}
        .user-dropdown ul li.danger button{color:rgba(239,68,68,0.7)}
        .user-dropdown ul li.danger button:hover{background:rgba(239,68,68,0.08);color:#EF4444}
        .user-dropdown-divider{height:1px;background:rgba(255,255,255,0.05);margin:3px 0}

        @keyframes slideIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}

        /* ══ LAYOUT ═════════════════════════════════════════ */
        .main-content{margin-left:240px;display:flex;flex-direction:column;min-height:100vh;transition:margin-left 0.35s cubic-bezier(0.32,0.72,0,1)}
        .main-content.collapsed{margin-left:64px}
        .page-content{flex:1;min-height:0;padding:32px 36px 64px;overflow-y:auto;overflow-x:hidden;position:relative;z-index:1;word-wrap:break-word;overflow-wrap:break-word}

        /* ══ CARDS ══════════════════════════════════════════ */
        .card{background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.05);border-radius:16px;transition:all 0.3s cubic-bezier(0.32,0.72,0,1)}
        .card:hover{border-color:rgba(255,255,255,0.1)}

        /* ══ BUTTONS ════════════════════════════════════════ */
        .btn{display:inline-flex;align-items:center;gap:7px;padding:8px 18px;border-radius:999px;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:all 0.4s cubic-bezier(0.32,0.72,0,1);text-decoration:none;white-space:nowrap;line-height:1}
        .btn:active{transform:scale(0.98)}
        .btn-primary{background:#fff;color:#050505}
        .btn-primary:hover{background:rgba(255,255,255,0.9)}
        .btn-secondary{background:rgba(255,255,255,0.04);color:rgba(255,255,255,0.6);border:1px solid rgba(255,255,255,0.06)}
        .btn-secondary:hover{background:rgba(255,255,255,0.06);color:#fff}
        .btn-ghost{background:transparent;color:rgba(255,255,255,0.4)}
        .btn-ghost:hover{background:rgba(255,255,255,0.04);color:#fff}
        .btn-danger{background:rgba(239,68,68,0.08);color:rgba(239,68,68,0.8)}
        .btn-danger:hover{background:#EF4444;color:#fff}
        .btn-sm{padding:5px 12px;font-size:11px}

        /* ══ FORMS ══════════════════════════════════════════ */
        .input{width:100%;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:10px 14px;font-size:14px;color:#fff;outline:none;font-family:inherit;transition:all 0.3s cubic-bezier(0.32,0.72,0,1)}
        .input:focus{border-color:rgba(255,255,255,0.2);background:rgba(255,255,255,0.06)}
        .label{display:block;font-size:12px;font-weight:600;color:rgba(255,255,255,0.4);margin-bottom:6px}

        /* ══ TABLES ═════════════════════════════════════════ */
        table.data-table{width:100%;border-collapse:collapse}
        table.data-table thead{border-bottom:1px solid rgba(255,255,255,0.05)}
        table.data-table th{text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:rgba(255,255,255,0.25);padding:12px 16px;white-space:nowrap}
        table.data-table td{padding:12px 16px;font-size:13px;color:rgba(255,255,255,0.7);border-bottom:1px solid rgba(255,255,255,0.03)}
        table.data-table tbody tr{transition:background 0.15s}
        table.data-table tbody tr:hover{background:rgba(255,255,255,0.02)}

        /* ══ BADGES ════════════════════════════════════════ */
        .badge{display:inline-flex;align-items:center;padding:2px 10px;border-radius:99px;font-size:10px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase}
        .badge-active{background:rgba(52,211,153,0.1);color:rgba(52,211,153,0.8)}
        .badge-inactive{background:rgba(255,255,255,0.04);color:rgba(255,255,255,0.3)}
        .badge-admin{background:rgba(99,102,241,0.1);color:rgba(165,180,252,0.8)}

        /* ══ FLASH ═════════════════════════════════════════ */
        .flash{display:flex;align-items:center;gap:10px;padding:12px 16px;margin:0 16px 8px;border-radius:14px;font-size:13px;font-weight:500;animation:fadeSlide 0.3s cubic-bezier(0.32,0.72,0,1)}
        .flash-success{background:rgba(52,211,153,0.08);color:rgba(52,211,153,0.8);border:1px solid rgba(52,211,153,0.1)}
        .flash-error{background:rgba(239,68,68,0.08);color:rgba(252,165,165,0.8);border:1px solid rgba(239,68,68,0.1)}
        .flash-info{background:rgba(99,102,241,0.08);color:rgba(165,180,252,0.8);border:1px solid rgba(99,102,241,0.1)}
        .flash-close{margin-left:auto;background:none;border:none;color:inherit;cursor:pointer;font-size:16px;opacity:0.5}
        @keyframes fadeSlide{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}

        /* ══ MODAL ═════════════════════════════════════════ */
        .modal-backdrop{display:none;position:fixed;inset:0;z-index:60;background:rgba(0,0,0,0.6);align-items:center;justify-content:center}
        .modal-backdrop.open{display:flex}
        .modal-box{background:rgba(20,20,20,0.95);backdrop-filter:blur(30px);border:1px solid rgba(255,255,255,0.08);border-radius:20px;padding:28px;max-width:420px;width:90%;box-shadow:0 12px 60px rgba(0,0,0,0.5);animation:slideIn 0.25s cubic-bezier(0.32,0.72,0,1)}
        .modal-icon-wrap{width:40px;height:40px;border-radius:14px;background:rgba(255,255,255,0.06);display:flex;align-items:center;justify-content:center;margin-bottom:16px}
        .modal-body h3{font-size:18px;font-weight:600;color:#fff;margin-bottom:6px}
        .modal-body p{font-size:14px;color:rgba(255,255,255,0.4);line-height:1.6}
        .modal-actions{display:flex;gap:8px;margin-top:20px}
        .modal-cancel{flex:1;padding:10px;border-radius:999px;background:rgba(255,255,255,0.04);color:rgba(255,255,255,0.5);border:1px solid rgba(255,255,255,0.06);cursor:pointer;font-size:13px;font-weight:500}
        .modal-confirm{flex:1;padding:10px;border-radius:999px;background:#fff;color:#050505;border:none;cursor:pointer;font-size:13px;font-weight:600}
        .modal-confirm-danger{flex:1;padding:10px;border-radius:999px;background:#EF4444;color:#fff;border:none;cursor:pointer;font-size:13px;font-weight:600}

        /* ══ TOAST ═════════════════════════════════════════ */
        #toast-container{position:fixed;bottom:24px;right:24px;z-index:60;display:flex;flex-direction:column;gap:8px}
        .toast{padding:10px 18px;border-radius:999px;font-size:13px;font-weight:500;animation:fadeSlide 0.25s cubic-bezier(0.32,0.72,0,1)}
        .toast-success{background:rgba(52,211,153,0.15);color:rgba(52,211,153,0.9)}
        .toast-error{background:rgba(239,68,68,0.15);color:rgba(252,165,165,0.9)}

        /* ══ SKELETON ══════════════════════════════════════ */
        .skeleton{background:linear-gradient(90deg,rgba(255,255,255,0.03) 25%,rgba(255,255,255,0.06) 50%,rgba(255,255,255,0.03) 75%);background-size:200% 100%;animation:shimmer 1.5s infinite;border-radius:8px}
        .skeleton-text{height:14px;width:80%;margin-bottom:8px}
        .skeleton-heading{height:24px;width:40%;margin-bottom:12px}
        @keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}

        /* ══ FOOTER ════════════════════════════════════════ */
        .app-footer{text-align:center;padding:20px 24px;border-top:1px solid rgba(255,255,255,0.04);font-size:12px;color:rgba(255,255,255,0.15)}

        ::-webkit-scrollbar{width:4px}::-webkit-scrollbar-track{background:transparent}::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.06);border-radius:99px}

        a:focus-visible,button:focus-visible,.input:focus-visible{outline:2px solid rgba(255,255,255,0.4);outline-offset:2px}

        @media(max-width:1024px){
            .sidebar{transform:translateX(-100%);width:min(280px,84vw);z-index:50;box-shadow:12px 0 40px rgba(0,0,0,0.6)}
            .sidebar.mobile-open{transform:translateX(0)}
            .main-content{margin-left:0!important}
            .sidebar-overlay{display:none;position:fixed;inset:0;z-index:45;background:rgba(0,0,0,0.4)}
            .sidebar.mobile-open~.sidebar-overlay{display:block}
            .page-content{padding:20px 16px 48px}
        }
        @media(max-width:640px){
            .topbar{height:52px;padding:0 14px;gap:8px}
            #toggle-sidebar{width:38px;height:38px}
            .user-btn{padding:4px}.user-btn-name,.user-btn-chevron{display:none}
            .user-dropdown{position:fixed;top:56px;right:8px;left:8px;width:auto;border-radius:16px}
            .btn{min-height:40px;justify-content:center}.input{min-height:42px}
            .page-content{padding:14px 12px 40px}
            table.data-table{display:block;overflow-x:auto;-webkit-overflow-scrolling:touch;max-width:100%}
            .card{border-radius:14px}
        }
        @media(prefers-reduced-motion:reduce){
            *,*::before,*::after{scroll-behavior:auto!important;animation-duration:0.01ms!important;transition-duration:0.01ms!important}
        }
    </style>
    @stack('styles')
</head>
<body>

<a href="#app-content"
   style="position:fixed;top:-100px;left:12px;z-index:609;background:#fff;color:#050505;padding:10px 18px;border-radius:999px;font-size:13px;font-weight:600;text-decoration:none;transition:top 0.2s;"
   onfocus="this.style.top='12px'" onblur="this.style.top='-100px'">Langkau ke kandungan</a>

{{-- ══ SIDEBAR ═══════════════════════════════════════════════ --}}
<aside id="sidebar" class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo"><img src="{{ asset('akmal3d.png') }}" alt="ChatMe"></div>
        <div><div class="sidebar-name">ChatMe</div><div class="sidebar-sub">SaaS</div></div>
    </div>
    <div class="nav-scroll">
        <ul class="nav-list">
            <li><div class="nav-group-title">Utama</div></li>
            <li class="nav-item">
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i class="ph ph-gauge nav-icon"></i>
                    <span class="nav-text"><span class="nav-text-label">Papan Pemuka</span></span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('chatbots.index') }}" class="nav-link {{ request()->routeIs('chatbots.*') ? 'active' : '' }}">
                    <i class="ph ph-robot nav-icon"></i>
                    <span class="nav-text"><span class="nav-text-label">Chatbot Saya</span></span>
                </a>
            </li>
            <li><div class="nav-group-title">Akaun</div></li>
            <li class="nav-item">
                <a href="{{ route('subscription.plans') }}" class="nav-link {{ request()->routeIs('subscription.*') ? 'active' : '' }}">
                    <i class="ph ph-crown nav-icon"></i>
                    <span class="nav-text"><span class="nav-text-label">Pelan Langganan</span></span>
                </a>
            </li>
            @if(auth()->user() && auth()->user()->is_admin)
            <li><div class="nav-group-title">Pentadbir</div></li>
            <li class="nav-item">
                <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <i class="ph ph-shield-check nav-icon"></i>
                    <span class="nav-text"><span class="nav-text-label">Panel Pentadbir</span><span class="nav-badge-admin">Admin</span></span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('admin.users') }}" class="nav-link {{ request()->routeIs('admin.users') ? 'active' : '' }}">
                    <i class="ph ph-users nav-icon"></i>
                    <span class="nav-text"><span class="nav-text-label">Urus Pengguna</span><span class="nav-badge-admin">Admin</span></span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('admin.chatbots') }}" class="nav-link {{ request()->routeIs('admin.chatbots') ? 'active' : '' }}">
                    <i class="ph ph-chats-circle nav-icon"></i>
                    <span class="nav-text"><span class="nav-text-label">Semua Chatbot</span><span class="nav-badge-admin">Admin</span></span>
                </a>
            </li>
            @endif
        </ul>
    </div>
</aside>
<div class="sidebar-overlay" id="sidebar-overlay"></div>

{{-- ══ MAIN CONTENT ═══════════════════════════════════════════ --}}
<div class="main-content" id="main-content">
    <header class="topbar">
        <button id="toggle-sidebar" aria-label="Togol sidebar">
            <i class="ph ph-list" style="font-size:18px;"></i>
        </button>
        <span class="topbar-title">@yield('page-title', 'Papan Pemuka')</span>
        <div class="topbar-actions">
            <div style="position:relative;">
                <button class="user-btn" id="user-btn">
                    <div class="user-avatar-sm">{{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}</div>
                    <span class="user-btn-name">{{ auth()->user()->name ?? 'Pengguna' }}</span>
                    <i class="ph ph-caret-down user-btn-chevron" style="font-size:12px;"></i>
                </button>
                <div class="user-dropdown" id="user-dropdown">
                    <div class="user-dropdown-header">
                        <p>{{ auth()->user()->name ?? 'Pengguna' }}</p>
                        <span>{{ auth()->user()->email ?? '' }}</span><br>
                        <span class="user-dropdown-role">{{ auth()->user() && auth()->user()->is_admin ? 'Pentadbir' : 'Pengguna' }}</span>
                    </div>
                    <ul>
                        <li><a href="{{ route('dashboard') }}"><i class="ph ph-gauge" style="width:16px;text-align:center;"></i>Papan Pemuka</a></li>
                        <li><a href="{{ route('subscription.plans') }}"><i class="ph ph-crown" style="width:16px;text-align:center;"></i>Pelan Langganan</a></li>
                        @if(auth()->user() && auth()->user()->is_admin)
                        <li><a href="{{ route('admin.dashboard') }}"><i class="ph ph-shield-check" style="width:16px;text-align:center;"></i>Panel Pentadbir</a></li>
                        @endif
                    </ul>
                    <div class="user-dropdown-divider"></div>
                    <ul>
                        <li class="danger">
                            <form method="POST" action="{{ route('logout') }}" id="form-logout-dropdown">@csrf
                                <button type="button" onclick="confirmLogout()"><i class="ph ph-sign-out" style="width:16px;text-align:center;"></i>Log Keluar</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </header>

    @if(session('success'))<div class="flash flash-success"><i class="ph ph-check-circle"></i><span>{{ session('success') }}</span><button class="flash-close" onclick="this.parentElement.remove()">&times;</button></div>@endif
    @if(session('error'))<div class="flash flash-error"><i class="ph ph-x-circle"></i><span>{{ session('error') }}</span><button class="flash-close" onclick="this.parentElement.remove()">&times;</button></div>@endif
    @if(session('info'))<div class="flash flash-info"><i class="ph ph-info"></i><span>{{ session('info') }}</span><button class="flash-close" onclick="this.parentElement.remove()">&times;</button></div>@endif

    <main class="page-content" id="app-content">@yield('content')</main>
    <div class="app-footer">&copy; {{ date('Y') }} ChatMe &mdash; Kuala Lumpur, Malaysia</div>
</div>

<div id="toast-container"></div>

{{-- Logout Modal --}}
<div class="modal-backdrop" id="logout-modal">
    <div class="modal-box">
        <div class="modal-icon-wrap"><i class="ph ph-sign-out" style="font-size:18px;color:rgba(255,255,255,0.5);"></i></div>
        <div class="modal-body"><h3>Log Keluar</h3><p>Sesi anda akan ditamatkan.</p></div>
        <div class="modal-actions">
            <button class="modal-cancel" onclick="document.getElementById('logout-modal').classList.remove('open')">Batal</button>
            <button class="modal-confirm" onclick="handleLogout()">Log Keluar</button>
        </div>
    </div>
</div>

{{-- Confirm Modal --}}
<div class="modal-backdrop" id="confirm-modal">
    <div class="modal-box">
        <div class="modal-icon-wrap"><i id="modal-icon" class="ph ph-warning" style="font-size:18px;color:rgba(255,255,255,0.5);"></i></div>
        <div class="modal-body"><h3 id="modal-title">Sahkan</h3><p id="modal-desc">Teruskan?</p></div>
        <div class="modal-actions">
            <button class="modal-cancel" onclick="closeConfirm()">Batal</button>
            <button id="modal-confirm-btn" class="modal-confirm">Ya</button>
        </div>
    </div>
</div>

<script>
(function() {
    var sidebar=document.getElementById('sidebar'),mainContent=document.getElementById('main-content'),overlay=document.getElementById('sidebar-overlay'),toggleBtn=document.getElementById('toggle-sidebar');
    var collapsed=localStorage.getItem('sidebarCollapsed')==='true';
    if(collapsed&&window.innerWidth>1024){sidebar.classList.add('collapsed');mainContent.classList.add('collapsed');}
    toggleBtn?.addEventListener('click',function(){
        if(window.innerWidth<=1024){sidebar.classList.toggle('mobile-open');overlay.style.display=sidebar.classList.contains('mobile-open')?'block':'none';}
        else{collapsed=!collapsed;sidebar.classList.toggle('collapsed',collapsed);mainContent.classList.toggle('collapsed',collapsed);localStorage.setItem('sidebarCollapsed',collapsed?'true':'false');}
    });
    overlay?.addEventListener('click',function(){sidebar.classList.remove('mobile-open');overlay.style.display='none';});
    sidebar.querySelectorAll('a[href]').forEach(function(a){a.addEventListener('click',function(e){var h=a.getAttribute('href')||'';if(!h||h.charAt(0)==='#'||/^javascript:/i.test(h))return;if(a.target==='_blank')return;sidebar.classList.remove('mobile-open');if(overlay)overlay.style.display='none';});});
    var userBtn=document.getElementById('user-btn'),userDropdown=document.getElementById('user-dropdown');
    userBtn?.addEventListener('click',function(e){e.stopPropagation();userDropdown.classList.toggle('open');userBtn.classList.toggle('open');});
    document.addEventListener('click',function(){userDropdown?.classList.remove('open');userBtn?.classList.remove('open');});
    document.querySelectorAll('.flash').forEach(function(f){setTimeout(function(){f.style.opacity='0';setTimeout(function(){f.remove();},300);},5000);});
})();
function confirmLogout(){document.getElementById('logout-modal').classList.add('open');}
function handleLogout(){document.getElementById('form-logout-dropdown').submit();}
window.sahkan=function(cfg){cfg=cfg||{};var m=document.getElementById('confirm-modal');var t=document.getElementById('modal-title');var d=document.getElementById('modal-desc');var b=document.getElementById('modal-confirm-btn');if(!m){confirm((cfg.title||'Sahkan')+'\n'+(cfg.desc||'Teruskan?'))&&cfg.onConfirm&&cfg.onConfirm();return}t.textContent=cfg.title||'Sahkan';d.innerHTML=cfg.desc||'Teruskan?';b.textContent=cfg.confirmText||'Ya';b.className=cfg.type==='danger'?'modal-confirm-danger':'modal-confirm';document.getElementById('modal-icon').className=cfg.type==='danger'?'ph ph-warning':'ph ph-question';b.onclick=function(){closeConfirm();cfg.onConfirm&&cfg.onConfirm();};m.classList.add('open');};
function closeConfirm(){document.getElementById('confirm-modal').classList.remove('open');}
window.showToast=function(m,t){t=t||'success';var c=document.getElementById('toast-container');var e=document.createElement('div');e.className='toast toast-'+t;e.textContent=m;c.appendChild(e);setTimeout(function(){e.style.opacity='0';setTimeout(function(){e.remove();},300);},3500);};
</script>
@stack('scripts')
</body>
</html>
