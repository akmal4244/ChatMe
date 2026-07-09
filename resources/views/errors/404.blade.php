@extends('layouts.guest')
@section('title', 'Halaman Tidak Dijumpai — ChatMe')
@section('content')
<div class="min-h-[60dvh] flex items-center justify-center text-center">
    <div>
        <p class="text-white/40xl font-extrabold text-neutral-200 mb-4">404</p>
        <h1 class="text-2xl font-bold text-white mb-3">Halaman tidak dijumpai</h1>
        <p class="text-white/25 mb-8 max-w-md mx-auto">Maaf, halaman yang anda cari tidak wujud atau telah dialihkan.</p>
        <a href="/" class="inline-block bg-white text-[#050505] px-6 py-3 rounded-lg font-semibold text-sm hover:bg-white/90 active:scale-[0.97] transition-all duration-200">Kembali ke Utama</a>
    </div>
</div>
@endsection