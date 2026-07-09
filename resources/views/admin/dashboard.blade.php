@extends('layouts.app')
@section('page-title', 'Panel Pentadbir')
@section('title', 'Panel Pentadbir — ChatMe')
@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-white">Panel Pentadbir</h1>
        <p class="text-sm text-white/25 mt-1">Selamat datang, {{ auth()->user()->name }}. Anda log masuk sebagai pentadbir.</p>
    </div>
    <span class="bg-white/[0.03] text-white text-xs font-semibold px-3 py-1.5 rounded-full">Pentadbir</span>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="bg-white/[0.03] rounded-lg border border-white/[0.06] p-5">
        <p class="text-xs font-medium text-white/25 uppercase tracking-wide mb-1">Jumlah Pengguna</p>
        <p class="text-3xl font-bold text-white">{{ $stats['total_users'] }}</p>
    </div>
    <div class="bg-white/[0.03] rounded-lg border border-white/[0.06] p-5">
        <p class="text-xs font-medium text-white/25 uppercase tracking-wide mb-1">Jumlah Chatbot</p>
        <p class="text-3xl font-bold text-white">{{ $stats['total_chatbots'] }}</p>
    </div>
    <div class="bg-white/[0.03] rounded-lg border border-white/[0.06] p-5">
        <p class="text-xs font-medium text-white/25 uppercase tracking-wide mb-1">Jumlah Mesej</p>
        <p class="text-3xl font-bold text-white">{{ $stats['total_messages'] }}</p>
    </div>
    <div class="bg-white/[0.03] rounded-lg border border-white/[0.06] p-5">
        <p class="text-xs font-medium text-white/25 uppercase tracking-wide mb-1">Hari Ini</p>
        <p class="text-3xl font-bold text-white">{{ $stats['messages_today'] }}</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white/[0.03] rounded-lg border border-white/[0.06] overflow-hidden">
        <div class="px-6 py-4 border-b border-white/[0.06] flex items-center justify-between">
            <h2 class="font-semibold text-white">Pengguna Terbaru</h2>
            <a href="{{ route('admin.users') }}" class="text-sm text-white font-medium hover:underline">Lihat Semua</a>
        </div>
        <table class="w-full">
            <thead class="bg-white/[0.03] text-left text-xs font-medium text-white/25 uppercase tracking-wide">
                <tr><th class="px-6 py-3">Nama</th><th class="px-6 py-3">Email</th><th class="px-6 py-3">Chatbot</th><th class="px-6 py-3">Tarikh</th></tr>
            </thead>
            <tbody class="divide-y divide-white/[0.06]">
                @foreach($recent_users as $u)
                <tr class="hover:bg-white/[0.03] transition-colors">
                    <td class="px-6 py-3 text-sm font-medium text-white">{{ $u->name }}</td>
                    <td class="px-6 py-3 text-sm text-white/25">{{ $u->email }}</td>
                    <td class="px-6 py-3 text-sm text-white/25">{{ $u->chatbots_count ?? 0 }}</td>
                    <td class="px-6 py-3 text-xs text-white/25">{{ $u->created_at->format('d/m/Y') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="bg-white/[0.03] rounded-lg border border-white/[0.06] overflow-hidden">
        <div class="px-6 py-4 border-b border-white/[0.06] flex items-center justify-between">
            <h2 class="font-semibold text-white">Chatbot Terbaru</h2>
            <a href="{{ route('admin.chatbots') }}" class="text-sm text-white font-medium hover:underline">Lihat Semua</a>
        </div>
        <table class="w-full">
            <thead class="bg-white/[0.03] text-left text-xs font-medium text-white/25 uppercase tracking-wide">
                <tr><th class="px-6 py-3">Nama</th><th class="px-6 py-3">Pemilik</th><th class="px-6 py-3">Status</th><th class="px-6 py-3">Tarikh</th></tr>
            </thead>
            <tbody class="divide-y divide-white/[0.06]">
                @foreach($recent_chatbots as $bot)
                <tr class="hover:bg-white/[0.03] transition-colors">
                    <td class="px-6 py-3 text-sm font-medium text-white">{{ $bot->name }}</td>
                    <td class="px-6 py-3 text-sm text-white/25">{{ $bot->user->name ?? 'N/A' }}</td>
                    <td class="px-6 py-3"><span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $bot->is_active ? 'bg-emerald-500/10 text-emerald-400' : 'bg-white/[0.03] text-white/25' }}">{{ $bot->is_active ? 'Aktif' : 'Tidak Aktif' }}</span></td>
                    <td class="px-6 py-3 text-xs text-white/25">{{ $bot->created_at->format('d/m/Y') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection