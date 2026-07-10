<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="ChatMe — Cipta chatbot AI untuk laman web anda. Platform SaaS buatan Malaysia.">
    <meta property="og:title" content="@yield('title', 'ChatMe — Chatbot AI untuk Laman Web')">
    <meta property="og:description" content="Latih chatbot dengan pengetahuan sendiri dan benamkan di laman web anda.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ config('app.url') }}">
    <meta name="twitter:card" content="summary">
    <title>@yield('title', 'ChatMe')</title>

    <link rel="icon" type="image/png" href="{{ asset('akmal3d.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Newsreader:opsz,wght@6..72,400;6..72,500;6..72,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
    @php($stylesheetVersion = substr(hash_file('sha256', public_path('css/app.css')), 0, 12))
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ $stylesheetVersion }}">
    @stack('styles')
</head>
<body class="guest-body">
    <a href="#main-content" class="skip-link">Langkau ke kandungan</a>

    <main id="main-content" tabindex="-1">
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
