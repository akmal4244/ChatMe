@extends('layouts.app')
@section('page-title', 'Selamat Datang')
@section('title', 'Selamat Datang — ChatMe')
@section('content')
<div class="min-h-[80dvh] flex items-center justify-center px-4">
    <div class="max-w-lg w-full text-center">
        <div class="w-20 h-20 bg-indigo-100 rounded-3xl flex items-center justify-center mx-auto mb-6">
            <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
        </div>
        <h1 class="text-3xl font-extrabold tracking-tight text-white mb-3">Selamat Datang ke ChatMe! 🎉</h1>
        <p class="text-white/25 leading-relaxed mb-8">Akaun anda berjaya dicipta. Mari mulakan dengan mencipta chatbot AI pertama anda — hanya ambil masa 2 minit.</p>
        <div class="flex flex-col gap-3">
            <a href="{{ route('chatbots.create') }}" class="bg-white text-[#050505] px-6 py-3.5 rounded-lg font-semibold text-sm hover:bg-white/90 transition-colors active:scale-[0.98]">Cipta Chatbot Pertama Anda</a>
            <a href="{{ route('dashboard') }}" class="text-white/25 text-sm font-medium hover:text-white/80 transition-colors">Pergi ke Papan Pemuka</a>
        </div>
    </div>
</div>
@endsection