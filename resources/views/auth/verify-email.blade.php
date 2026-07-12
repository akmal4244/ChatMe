@extends('layouts.app')

@section('title', 'Sahkan E-mel')
@section('page-title', 'Sahkan e-mel')

@section('content')
<section class="subscription-page" aria-labelledby="verify-email-heading">
    <header class="subscription-header">
        <p class="eyebrow">Keselamatan akaun</p>
        <h1 id="verify-email-heading">Sahkan e-mel anda</h1>
        <p>Kami telah menghantar pautan pengesahan ke <strong>{{ $maskedEmail }}</strong>. Sahkan e-mel sebelum menggunakan fungsi ChatMe.</p>
    </header>

    <div class="auth-card mx-auto">
        <a href="{{ route('profile.edit') }}" class="button button-secondary button-full">Semak profil dan e-mel</a>
        <form method="POST" action="{{ route('verification.send') }}" class="auth-form">
            @csrf
            <button type="submit" class="button button-primary button-full">Hantar semula e-mel pengesahan</button>
        </form>
        <form method="POST" action="{{ route('logout') }}" class="auth-form mt-4">
            @csrf
            <button type="submit" class="button button-secondary button-full">Log keluar</button>
        </form>
    </div>
</section>
@endsection
