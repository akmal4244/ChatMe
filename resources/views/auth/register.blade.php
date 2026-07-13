@extends('layouts.guest')

@section('title', 'Daftar — ChatMe')

@section('content')
<section class="auth-page" aria-labelledby="register-heading">
    <div class="auth-panel">
        <header class="auth-header">
            <a href="{{ route('landing') }}" class="brand-link" aria-label="ChatMe — halaman utama">
                <img src="{{ asset('akmal3d.png') }}" alt="" class="brand-logo" width="40" height="40">
                <span>ChatMe</span>
            </a>
            <h1 id="register-heading">Cipta akaun</h1>
            <p>Mula bina chatbot anda dalam beberapa minit.</p>
        </header>

        <div class="auth-card">
            @if($errors->any())
                <div class="alert alert-error" role="alert">
                    Sila semak dan betulkan ruangan berikut.
                </div>
            @endif

            @if($googleAuthAvailable ?? false)
                <a class="google-auth-button" href="{{ route('auth.google.redirect') }}">
                    <img
                        class="google-auth-button__mark"
                        src="{{ asset('images/google-g-logo.svg') }}"
                        alt=""
                        aria-hidden="true"
                        width="20"
                        height="20"
                    >
                    <span>Teruskan dengan Google</span>
                </a>

                <div class="auth-divider" role="separator" aria-label="Pilihan pendaftaran">
                    <span>atau teruskan dengan e-mel</span>
                </div>
            @endif

            <form method="POST" action="{{ route('register') }}" class="auth-form">
                @csrf

                <div class="form-field">
                    <label for="name">Nama penuh</label>
                    <input
                        id="name"
                        name="name"
                        type="text"
                        value="{{ old('name') }}"
                        autocomplete="name"
                        placeholder="Ahmad bin Ali"
                        @error('name') aria-describedby="name-error" aria-invalid="true" @enderror
                        required
                        @unless($googleAuthAvailable ?? false) autofocus @endunless
                    >
                    @error('name')
                        <p id="name-error" class="field-error">{{ $message }}</p>
                    @enderror
                </div>

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
                    >
                    @error('email')
                        <p id="email-error" class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-field">
                    <label for="password">Kata laluan</label>
                    <p id="password-hint" class="field-hint">Gunakan sekurang-kurangnya 8 aksara.</p>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        autocomplete="new-password"
                        aria-describedby="password-hint{{ $errors->has('password') ? ' password-error' : '' }}"
                        @error('password') aria-invalid="true" @enderror
                        required
                    >
                    @error('password')
                        <p id="password-error" class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-field">
                    <label for="password_confirmation">Sahkan kata laluan</label>
                    <input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        @error('password_confirmation') aria-describedby="password-confirmation-error" aria-invalid="true" @enderror
                        required
                    >
                    @error('password_confirmation')
                        <p id="password-confirmation-error" class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="button button-primary button-full">
                    Cipta akaun
                    <i class="ph ph-arrow-right" aria-hidden="true"></i>
                </button>
            </form>

            <p class="auth-switch">
                Sudah ada akaun? <a href="{{ route('login') }}">Log masuk</a>
            </p>
        </div>
    </div>
</section>
@endsection
