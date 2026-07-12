@extends('layouts.guest')

@section('title', 'Log Masuk — ChatMe')

@section('content')
<section class="auth-page" aria-labelledby="login-heading">
    <div class="auth-panel">
        <header class="auth-header">
            <a href="{{ route('landing') }}" class="brand-link" aria-label="ChatMe — halaman utama">
                <img src="{{ asset('akmal3d.png') }}" alt="" class="brand-logo" width="40" height="40">
                <span>ChatMe</span>
            </a>
            <h1 id="login-heading">Selamat kembali</h1>
            <p>Log masuk untuk mengurus chatbot dan langganan anda.</p>
        </header>

        <div class="auth-card">
            @if($errors->any())
                <div class="alert alert-error" role="alert">
                    Sila semak maklumat log masuk anda.
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="auth-form">
                @csrf

                <div class="form-field">
                    <label for="email">E-mel</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        autocomplete="email"
                        inputmode="email"
                        placeholder="nama@example.com"
                        @error('email') aria-describedby="email-error" aria-invalid="true" @enderror
                        required
                        autofocus
                    >
                    @error('email')
                        <p id="email-error" class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-field">
                    <label for="password">Kata laluan</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        @error('password') aria-describedby="password-error" aria-invalid="true" @enderror
                        required
                    >
                    @error('password')
                        <p id="password-error" class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-options">
                    <label for="remember" class="checkbox-field">
                        <input id="remember" name="remember" type="checkbox" value="1" @checked(old('remember'))>
                        <span>Teruskan log masuk pada peranti ini</span>
                    </label>
                    <a href="{{ route('password.request') }}">Lupa kata laluan?</a>
                </div>

                <button type="submit" class="button button-primary button-full">
                    Log masuk
                    <i class="ph ph-arrow-right" aria-hidden="true"></i>
                </button>
            </form>

            <p class="auth-switch">
                Belum ada akaun? <a href="{{ route('register') }}">Daftar percuma</a>
            </p>
        </div>
    </div>
</section>
@endsection
