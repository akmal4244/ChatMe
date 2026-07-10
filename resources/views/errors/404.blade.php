@extends('layouts.guest')

@section('title', 'Halaman Tidak Dijumpai — ChatMe')

@section('content')
<section class="error-page error-panel" aria-labelledby="not-found-heading">
        <p class="error-code" aria-hidden="true">404</p>
        <h1 id="not-found-heading">Halaman tidak dijumpai</h1>
        <p>Maaf, halaman yang anda cari tidak wujud atau telah dialihkan.</p>
        <a href="{{ route('landing') }}" class="button button-primary">
            <i class="ph ph-arrow-left" aria-hidden="true"></i>
            Kembali ke halaman utama
        </a>
</section>
@endsection
