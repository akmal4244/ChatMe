@extends('layouts.guest')

@section('title', 'Tetapkan Semula Kata Laluan — ChatMe')

@section('content')
<section class="auth-page" aria-labelledby="reset-password-heading">
    <div class="auth-panel">
        <header class="auth-header">
            <a href="{{ route('landing') }}" class="brand-link" aria-label="ChatMe — halaman utama">
                <img src="{{ asset('akmal3d.png') }}" alt="" class="brand-logo" width="40" height="40">
                <span>ChatMe</span>
            </a>
            <h1 id="reset-password-heading">Tetapkan semula kata laluan</h1>
            <p>Pilih kata laluan baharu untuk akaun ChatMe anda.</p>
        </header>

        <div class="auth-card">
            <form method="POST" action="{{ route('password.update') }}" class="auth-form">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="form-field">
                    <label for="email">E-mel</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email', $email) }}"
                        autocomplete="email"
                        inputmode="email"
                        @error('email') aria-describedby="email-error" aria-invalid="true" @enderror
                        required
                    >
                    @error('email')
                        <p id="email-error" class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-field">
                    <label for="password">Kata laluan baharu</label>
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
                    <label for="password_confirmation">Sahkan kata laluan baharu</label>
                    <input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        required
                    >
                </div>

                <button type="submit" class="button button-primary button-full">Tetapkan semula kata laluan</button>
            </form>
        </div>
    </div>
</section>
@endsection
