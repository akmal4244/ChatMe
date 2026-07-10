@extends('layouts.guest')

@section('title', 'Sistem Sedang Diselenggara — ChatMe')

@section('content')
<section class="error-page error-panel" aria-labelledby="maintenance-heading">
        <p class="error-code" aria-hidden="true">503</p>
        <h1 id="maintenance-heading">Sistem sedang diselenggara</h1>
        <p>Kami sedang menambah baik ChatMe. Sila cuba lagi sebentar lagi.</p>
        <a href="{{ route('landing') }}" class="button button-primary">
            <i class="ph ph-arrow-left" aria-hidden="true"></i>
            Kembali ke halaman utama
        </a>
</section>
@endsection
