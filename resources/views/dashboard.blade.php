@extends('layouts.app')
@section('page-title', 'Papan Pemuka')
@section('title', 'Papan Pemuka — ChatMe')
@section('content')
<h1 class="text-2xl font-bold text-white mb-6">Papan Pemuka</h1>
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
  <div class="bg-white/[0.03] rounded-lg border border-white/[0.06] p-5"><p class="text-xs font-medium text-white/25 uppercase tracking-wide mb-1">Chatbot Aktif</p><p class="text-3xl font-bold text-white">{{ $chatbots->where('is_active', true)->count() }}</p></div>
  <div class="bg-white/[0.03] rounded-lg border border-white/[0.06] p-5"><p class="text-xs font-medium text-white/25 uppercase tracking-wide mb-1">Jumlah Mesej</p><p class="text-3xl font-bold text-white">{{ $totalMessages }}</p></div>
  <div class="bg-white/[0.03] rounded-lg border border-white/[0.06] p-5"><p class="text-xs font-medium text-white/25 uppercase tracking-wide mb-1">Pelan Semasa</p><p class="text-3xl font-bold text-white">{{ $subscription?->plan?->name ?? 'Percuma' }}</p></div>
</div>
<div class="flex items-center justify-between mb-4">
  <h2 class="text-lg font-semibold text-white">Chatbot Anda</h2>
  <a href="{{ route('chatbots.create') }}" class="bg-white text-[#050505] px-4 py-2 rounded-lg text-sm font-semibold hover:bg-white/90 transition-colors active:scale-[0.98]">+ Chatbot Baru</a>
</div>
<div class="bg-white/[0.03] rounded-lg border border-white/[0.06] overflow-hidden">
  @if($chatbots->isEmpty())
    <div class="p-16 text-center"><div class="w-14 h-14 bg-white/[0.03] rounded-lg flex items-center justify-center mx-auto mb-4"><svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg></div><h3 class="font-semibold text-white mb-1">Tiada chatbot lagi</h3><p class="text-sm text-white/25 mb-5">Cipta chatbot pertama anda sekarang.</p><a href="{{ route('chatbots.create') }}" class="inline-block bg-white text-[#050505] px-5 py-2.5 rounded-lg font-semibold text-sm hover:bg-white/90 transition-colors">Cipta Chatbot</a></div>
  @else
    <table class="w-full"><thead class="bg-white/[0.03] text-left text-xs font-medium text-white/25 uppercase tracking-wide border-b border-white/[0.06]"><tr><th class="px-6 py-3">Chatbot</th><th class="px-6 py-3">Status</th><th class="px-6 py-3">Pengetahuan</th><th class="px-6 py-3">Kunci API</th><th class="px-6 py-3">Tindakan</th></tr></thead>
    <tbody class="divide-y divide-white/[0.06]">@foreach($chatbots as $bot)<tr class="hover:bg-white/[0.03] transition-colors"><td class="px-6 py-4"><div class="flex items-center gap-3"><img src="{{ $bot->avatar_url ? asset('storage/'.$bot->avatar_url) : asset('akmal3d.png') }}" class="w-9 h-9 rounded-full object-cover ring-2 ring-white/[0.06]"><div><p class="font-semibold text-sm text-white">{{ $bot->name }}</p><p class="text-xs text-white/25">{{ $bot->slug }}</p></div></div></td><td class="px-6 py-4"><form action="{{ route('chatbots.toggle', $bot) }}" method="POST">@csrf<button class="text-xs font-semibold px-2.5 py-1 rounded-full {{ $bot->is_active ? 'bg-emerald-500/10 text-emerald-400' : 'bg-white/[0.03] text-white/25' }}">{{ $bot->is_active ? 'Aktif' : 'Tidak Aktif' }}</button></form></td><td class="px-6 py-4 text-sm text-white/40">{{ $bot->knowledge_items_count }}</td><td class="px-6 py-4 text-sm font-mono text-white/25">{{ substr($bot->api_key, 0, 8) }}...</td><td class="px-6 py-4"><div class="flex gap-2 text-sm"><a href="{{ route('chatbots.edit', $bot) }}" class="text-white font-medium hover:underline">Sunting</a><a href="{{ route('chatbots.embed', $bot) }}" class="text-emerald-400 font-medium hover:underline">Benam</a><a href="{{ route('knowledge.index', $bot) }}" class="text-purple-400 font-medium hover:underline">Pengetahuan</a></div></td></tr>@endforeach</tbody></table>
  @endif
</div>
@endsection
