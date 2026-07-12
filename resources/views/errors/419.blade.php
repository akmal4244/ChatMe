@extends('layouts.guest')

@section('title', 'Sesi Telah Tamat — ChatMe')

@section('content')
<section class="error-page error-panel" aria-labelledby="expired-heading">
        <p class="error-code" aria-hidden="true">419</p>
        <h1 id="expired-heading">Sesi telah tamat</h1>
        <p>Sila log masuk semula untuk meneruskan.</p>
        <a href="{{ route('login', ['session_expired' => 1]) }}" class="button button-primary">
            <i class="ph ph-arrow-left" aria-hidden="true"></i>
            Kembali ke halaman log masuk
        </a>
</section>
@endsection
