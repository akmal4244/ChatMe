@extends('layouts.app')
@section('page-title', 'Selamat Datang')
@section('title', 'Selamat Datang — ChatMe')
@section('content')
<div class="min-h-[80dvh] flex items-center justify-center px-4">
    <div class="card max-w-lg w-full text-center p-6 sm:p-10">
        <div class="w-20 h-20 bg-brand-50 rounded-3xl flex items-center justify-center mx-auto mb-6" aria-hidden="true">
            <svg class="w-10 h-10 text-brand-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
        </div>
        <h1 class="text-3xl font-extrabold tracking-tight text-neutral-950 mb-3">Selamat Datang ke ChatMe! 🎉</h1>
        <p class="text-neutral-600 leading-relaxed mb-8">Akaun anda berjaya dicipta. Mari mulakan dengan mencipta chatbot AI pertama anda — hanya ambil masa 2 minit.</p>
        <div class="flex flex-col gap-3">
            <a href="{{ route('chatbots.create') }}" class="btn btn-primary justify-center">Cipta Chatbot Pertama Anda</a>
            <a href="{{ route('dashboard') }}" class="btn btn-ghost justify-center">Pergi ke Papan Pemuka</a>
        </div>
    </div>
</div>
@endsection
