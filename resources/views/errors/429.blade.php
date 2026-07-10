@extends('layouts.guest')

@section('title', 'Terlalu Banyak Permintaan — ChatMe')

@section('content')
<section class="error-page error-panel" aria-labelledby="rate-limit-heading">
        <p class="error-code" aria-hidden="true">429</p>
        <h1 id="rate-limit-heading">Terlalu banyak permintaan</h1>
        <p>Sila tunggu sebentar sebelum mencuba lagi.</p>
        <a href="{{ route('landing') }}" class="button button-primary">
            <i class="ph ph-arrow-left" aria-hidden="true"></i>
            Kembali ke halaman utama
        </a>
</section>
@endsection
