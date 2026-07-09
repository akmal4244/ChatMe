<!DOCTYPE html>
<html lang="ms" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="{{ asset('akmal3d.png') }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="ChatMe — Cipta Chatbot AI Custom untuk Laman Web Anda. Platform SaaS buatan Malaysia.">
    <meta property="og:title" content="@yield('title', 'ChatMe — Cipta Chatbot AI Custom')">
    <meta property="og:description" content="Platform SaaS buatan Malaysia. Latih chatbot dengan pengetahuan sendiri dan benamkan di mana-mana laman web.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://chatme.akmalmarvis.com">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title', 'ChatMe — Chatbot AI Custom')">
    <meta name="twitter:description" content="Platform SaaS buatan Malaysia. Cipta chatbot AI dalam masa 2 minit.">
    <title>@yield('title', 'ChatMe')</title>

    {{-- Typography — high-end: Newsreader editorial + Jakarta Sans --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Newsreader:opsz,wght@6..72,300;6..72,400;6..72,500;6..72,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    {{-- Phosphor Icons (Light weight) — high-end-visual-design protocol --}}
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">

    <link rel="stylesheet" href="{{ asset('css/app.css') }}">

    <style>
        :root {
            --text: #ffffff;
            --text-secondary: rgba(255,255,255,0.5);
            --text-muted: rgba(255,255,255,0.25);
            --border: rgba(255,255,255,0.06);
            --surface: rgba(255,255,255,0.03);
            --surface-alt: rgba(255,255,255,0.05);
            --bg: #050505;
            --font-ui: 'Plus Jakarta Sans', 'Switzer', 'Helvetica Neue', sans-serif;
            --font-editorial: 'Newsreader', 'Playfair Display', serif;
            --font-mono: 'JetBrains Mono', 'Geist Mono', monospace;
            --ease-out: cubic-bezier(0.32,0.72,0,1);
        }
        html { scroll-behavior: smooth; }
        body {
            font-family: var(--font-ui);
            background: var(--bg);
            color: var(--text);
            -webkit-font-smoothing: antialiased;
            letter-spacing: -0.01em;
            line-height: 1.6;
            overflow-x: hidden;
        }
        #skip-link {
            position: absolute; top: -100px; left: 16px; z-index: 99999;
            background: #fff; color: #050505; padding: 10px 18px;
            border-radius: 999px; font-size: 13px; font-weight: 600; transition: top 0.2s;
        }
        #skip-link:focus { top: 16px; }
        a:focus-visible, button:focus-visible, input:focus-visible {
            outline: 2px solid rgba(255,255,255,0.5); outline-offset: 2px;
        }

        /* ══ RESPONSIVE OVERRIDES ═══════════════════════════ */
        @media(max-width:900px){
            [style*="grid-template-columns:repeat(12,1fr)"]{grid-template-columns:1fr!important}
            [style*="grid-column:span 8"],[style*="grid-column:span 4"]{grid-column:span 1!important}
            [style*="grid-template-columns:repeat(auto-fit,minmax(300px,1fr))"]{grid-template-columns:1fr!important}
        }
        @media(max-width:640px){
            nav[style*="position:fixed"]>div{flex-wrap:wrap!important;justify-content:center!important;gap:4px!important;padding:12px 10px!important}
            nav[style*="position:fixed"] a{padding:6px 10px!important;font-size:11px!important}
            [style*="width:1px;height:20px"]{display:none!important}
            section[style*="min-height:100dvh"]{padding-top:140px!important;padding-bottom:60px!important}
        }
    </style>
    @stack('styles')
</head>
<body style="background:#050505;color:#fff;">

    <a href="#main-content" id="skip-link">Langkau ke kandungan</a>

    @yield('content')

    @stack('scripts')
</body>
</html>
