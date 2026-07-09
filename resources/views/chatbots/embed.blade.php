@extends('layouts.app')
@section('page-title', 'Kod Benam')
@section('title', 'Benam — ' . $chatbot->name)
@section('content')
<div class="max-w-2xl">
    <h1 class="text-2xl font-bold text-white mb-2">Benam: {{ $chatbot->name }}</h1>
    <p class="text-white/25 mb-6">Salin kod ini dan tampal ke dalam HTML laman web anda, sebelum tag &lt;/body&gt;.</p>
    
    <div class="bg-white rounded-lg p-6 mb-6 relative shadow-xl">
        <pre class="text-sm font-mono overflow-x-auto leading-relaxed"><code class="text-blue-400">&lt;script</code> <code class="text-emerald-400">src</code><code class="text-white">=</code><code class="text-amber-300">"{{ url('/widget/' . $chatbot->api_key . '.js') }}"</code><code class="text-blue-400">&gt;&lt;/script&gt;</code></pre>
        <button onclick="navigator.clipboard.writeText('&lt;script src=&quot;{{ url('/widget/' . $chatbot->api_key . '.js') }}&quot;&gt;&lt;/script&gt;')" class="absolute top-3 right-3 bg-neutral-700 hover:bg-neutral-600 text-white text-xs px-3 py-1.5 rounded-lg transition">Salin</button>
    </div>
    
    <div class="bg-white/[0.03] rounded-lg border border-white/[0.06] p-6 mb-6">
        <h2 class="font-semibold text-white mb-4">Kunci API</h2>
        <div class="flex items-center gap-3 mb-3">
            <code class="flex-1 bg-white/[0.03] rounded-lg px-4 py-3 text-sm font-mono text-white/80">{{ $chatbot->api_key }}</code>
            <button onclick="navigator.clipboard.writeText('{{ $chatbot->api_key }}')" class="text-sm text-white font-medium hover:underline whitespace-nowrap">Salin</button>
        </div>
        <form action="{{ route('chatbots.regenerate-key', $chatbot) }}" method="POST" class="inline">
            @csrf
            <button type="submit" class="text-sm text-red-500 font-medium hover:underline" onclick="return confirm('Jana semula kunci API? Kunci lama akan berhenti berfungsi.')">Jana Semula Kunci</button>
        </form>
    </div>

    <div class="bg-white/[0.03] border border-brand-100 rounded-lg p-6">
        <h2 class="font-semibold text-brand-900 mb-3">Pratonton</h2>
        <p class="text-sm text-white mb-4">Beginilah rupa chatbot anda di laman web:</p>
        <div class="bg-white/[0.03] rounded-lg border p-4 flex items-center gap-3">
            <img src="{{ $chatbot->avatar_url ? asset('storage/'.$chatbot->avatar_url) : asset('akmal3d.png') }}" class="w-12 h-12 rounded-full object-cover ring-2 ring-white/[0.06]">
            <div>
                <p class="font-semibold text-white">{{ $chatbot->bot_name }}</p>
                <p class="text-sm text-white/25">{{ $chatbot->welcome_message }}</p>
            </div>
            <div class="ml-auto w-3 h-3 rounded-full shadow" style="background:{{ $chatbot->primary_color }}"></div>
        </div>
    </div>
</div>
@endsection
