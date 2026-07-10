@extends('layouts.guest')

@section('title', 'Akses Tidak Dibenarkan — ChatMe')

@section('content')
<section class="error-page error-panel" aria-labelledby="forbidden-heading">
        <p class="error-code" aria-hidden="true">403</p>
        <h1 id="forbidden-heading">Akses tidak dibenarkan</h1>
        <p>Maaf, anda tidak mempunyai kebenaran untuk membuka halaman ini.</p>
        <a href="{{ route('landing') }}" class="button button-primary">
            <i class="ph ph-arrow-left" aria-hidden="true"></i>
            Kembali ke halaman utama
        </a>
</section>
@endsection
