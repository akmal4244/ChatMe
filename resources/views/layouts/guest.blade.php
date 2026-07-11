<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="ChatMe — Cipta chatbot AI untuk laman web anda. Platform chatbot buatan Malaysia.">
    <meta property="og:title" content="@yield('title', 'ChatMe — Chatbot AI untuk Laman Web')">
    <meta property="og:description" content="Bina chatbot menggunakan maklumat anda sendiri dan pasangkannya pada laman web anda.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ config('app.url') }}">
    <meta name="twitter:card" content="summary">
    <title>@yield('title', 'ChatMe')</title>

    <link rel="icon" type="image/png" href="{{ asset('akmal3d.png') }}">
    @php($stylesheetVersion = substr(hash_file('sha256', public_path('css/app.css')), 0, 12))
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ $stylesheetVersion }}">
    @stack('styles')
</head>
<body class="guest-body">
    <a href="#main-content" class="skip-link">Langkau ke kandungan</a>

    <main id="main-content" tabindex="-1">
        @yield('content')
    </main>

    @include('partials.toasts')
    @stack('scripts')
</body>
</html>
