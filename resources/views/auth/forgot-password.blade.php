@extends('layouts.guest')

@section('title', 'Lupa Kata Laluan — ChatMe')

@section('content')
<section class="auth-page" aria-labelledby="forgot-password-heading">
    <div class="auth-panel">
        <header class="auth-header">
            <a href="{{ route('landing') }}" class="brand-link" aria-label="ChatMe — halaman utama">
                <img src="{{ asset('akmal3d.png') }}" alt="" class="brand-logo" width="40" height="40">
                <span>ChatMe</span>
            </a>
            <h1 id="forgot-password-heading">Lupa kata laluan</h1>
            <p>Masukkan e-mel anda. Jika akaun tersebut wujud, kami akan menghantar pautan penetapan semula.</p>
        </header>

        <div class="auth-card">
            <form method="POST" action="{{ route('password.email') }}" class="auth-form">
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

                <button type="submit" class="button button-primary button-full">
                    Hantar pautan penetapan semula
                    <i class="ph ph-paper-plane-tilt" aria-hidden="true"></i>
                </button>
            </form>

            <p class="auth-switch"><a href="{{ route('login') }}">Kembali ke halaman log masuk</a></p>
        </div>
    </div>
</section>
@endsection
