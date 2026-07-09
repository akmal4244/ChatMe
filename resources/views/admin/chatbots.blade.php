@extends('layouts.app')
@section('page-title', 'Semua Chatbot')
@section('title', 'Semua Chatbot — Panel Pentadbir')
@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-white">Semua Chatbot</h1>
        <p class="text-sm text-white/25 mt-1">{{ $chatbots->total() }} chatbot berdaftar</p>
    </div>
    <a href="{{ route('admin.dashboard') }}" class="text-sm text-white/25 hover:text-white/80">&larr; Kembali ke Panel</a>
</div>
<div class="bg-white/[0.03] rounded-lg border border-white/[0.06] overflow-hidden">
    <table class="w-full">
        <thead class="bg-white/[0.03] text-left text-xs font-medium text-white/25 uppercase tracking-wide">
            <tr><th class="px-6 py-3">Chatbot</th><th class="px-6 py-3">Pemilik</th><th class="px-6 py-3">Pengetahuan</th><th class="px-6 py-3">Status</th><th class="px-6 py-3">Tarikh</th></tr>
        </thead>
        <tbody class="divide-y divide-white/[0.06]">
            @foreach($chatbots as $bot)
            <tr class="hover:bg-white/[0.03] transition-colors">
                <td class="px-6 py-4"><p class="text-sm font-semibold text-white">{{ $bot->name }}</p><p class="text-xs text-white/25">{{ $bot->slug }}</p></td>
                <td class="px-6 py-4 text-sm text-white/25">{{ $bot->user->name ?? 'N/A' }}</td>
                <td class="px-6 py-4 text-sm text-white/25">{{ $bot->knowledge_items_count }}</td>
                <td class="px-6 py-4"><span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $bot->is_active ? 'bg-emerald-500/10 text-emerald-400' : 'bg-white/[0.03] text-white/25' }}">{{ $bot->is_active ? 'Aktif' : 'Tidak Aktif' }}</span></td>
                <td class="px-6 py-4 text-xs text-white/25">{{ $bot->created_at->format('d/m/Y') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $chatbots->links() }}</div>
@endsection