@extends('layouts.app')
@section('page-title', 'Langganan Berjaya')
@section('title', 'Langganan Berjaya')
@section('content')
<div class="max-w-lg mx-auto text-center py-16">
    <div class="w-20 h-20 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-6">
        <svg class="w-10 h-10 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
    </div>
    <h1 class="text-3xl font-bold text-white mb-3">Langganan Diaktifkan!</h1>
    <p class="text-white/25 mb-8">Pelan anda telah dinaik taraf. Anda kini mempunyai akses kepada semua ciri.</p>
    <a href="{{ route('dashboard') }}" class="inline-block bg-white text-[#050505] px-8 py-3 rounded-lg font-semibold text-sm hover:bg-white/90 transition">Ke Papan Pemuka</a>
</div>
@endsection
