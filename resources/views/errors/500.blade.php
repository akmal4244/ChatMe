@extends('layouts.guest')

@section('title', 'Sistem Menghadapi Masalah — ChatMe')

@section('content')
<section class="error-page error-panel" aria-labelledby="server-error-heading">
        <p class="error-code" aria-hidden="true">500</p>
        <h1 id="server-error-heading">Sistem menghadapi masalah</h1>
        <p>Maaf, berlaku masalah yang tidak dijangka. Sila cuba lagi sebentar lagi.</p>
        <a href="{{ route('landing') }}" class="button button-primary">
            <i class="ph ph-arrow-left" aria-hidden="true"></i>
            Kembali ke halaman utama
        </a>
</section>
@endsection
