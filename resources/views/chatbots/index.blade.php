@extends('layouts.app')
@section('page-title', 'Chatbot Saya')
@section('title', 'Chatbot Saya — ChatMe')
@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-white">Chatbot Saya</h1>
    <a href="{{ route('chatbots.create') }}" class="bg-white text-[#050505] px-4 py-2.5 rounded-lg text-sm font-semibold hover:bg-white/90 transition shadow-sm">+ Chatbot Baru</a>
</div>
<div class="bg-white/[0.03] rounded-lg border border-white/[0.06] overflow-hidden">
    @if($chatbots->isEmpty())
        <div class="p-16 text-center">
            <h3 class="text-lg font-semibold text-white mb-2">Tiada chatbot lagi</h3>
            <p class="text-white/25 mb-5">Cipta chatbot AI pertama anda dalam masa kurang 2 minit.</p>
            <a href="{{ route('chatbots.create') }}" class="inline-block bg-white text-[#050505] px-6 py-2.5 rounded-lg font-semibold text-sm hover:bg-white/90 transition">Cipta Chatbot Pertama Anda</a>
        </div>
    @else
        <table class="w-full">
            <thead class="bg-white/[0.03] text-left text-sm text-white/25 border-b border-white/[0.06]">
                <tr><th class="px-6 py-3 font-medium">Chatbot</th><th class="px-6 py-3 font-medium">Status</th><th class="px-6 py-3 font-medium">Pengetahuan</th><th class="px-6 py-3 font-medium">Mesej</th><th class="px-6 py-3 font-medium">Tindakan</th></tr>
            </thead>
            <tbody class="divide-y divide-white/[0.06]">
                @foreach($chatbots as $bot)
                <tr class="hover:bg-white/[0.03] transition-colors">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <img src="{{ $bot->avatar_url ? asset('storage/'.$bot->avatar_url) : asset('akmal3d.png') }}" class="w-10 h-10 rounded-full object-cover ring-2 ring-white/[0.06]">
                            <div><p class="font-semibold text-white text-sm">{{ $bot->name }}</p><p class="text-xs text-white/25">{{ $bot->slug }}</p></div>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <form action="{{ route('chatbots.toggle', $bot) }}" method="POST">@csrf
                            <button class="text-xs font-semibold px-2.5 py-1 rounded-full {{ $bot->is_active ? 'bg-emerald-500/10 text-emerald-400' : 'bg-white/[0.03] text-white/25' }}">{{ $bot->is_active ? 'Aktif' : 'Tidak Aktif' }}</button>
                        </form>
                    </td>
                    <td class="px-6 py-4 text-sm">{{ $bot->knowledge_items_count }} item</td>
                    <td class="px-6 py-4 text-sm">{{ $bot->chatLogs()->count() }}</td>
                    <td class="px-6 py-4">
                        <div class="flex gap-2 text-sm">
                            <a href="{{ route('chatbots.edit', $bot) }}" class="text-white font-medium hover:underline">Sunting</a>
                            <a href="{{ route('knowledge.index', $bot) }}" class="text-purple-400 font-medium hover:underline">Pengetahuan</a>
                            <a href="{{ route('chatbots.embed', $bot) }}" class="text-emerald-400 font-medium hover:underline">Benam</a>
                            <form action="{{ route('chatbots.destroy', $bot) }}" method="POST" onsubmit="return confirm('Padam chatbot ini?')" class="inline">@csrf @method('DELETE')
                                <button class="text-red-500 font-medium hover:underline">Padam</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
