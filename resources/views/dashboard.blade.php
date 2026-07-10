@extends('layouts.app')
@section('page-title', 'Papan Pemuka')
@section('title', 'Papan Pemuka — ChatMe')
@section('content')
<h1 class="text-2xl font-bold text-neutral-950 mb-6">Papan Pemuka</h1>

<dl class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 mb-8">
    <div class="card p-5">
        <dt class="text-xs font-medium text-neutral-500 uppercase tracking-wide mb-1">Chatbot Aktif</dt>
        <dd class="text-3xl font-bold text-neutral-950">{{ $chatbots->where('is_active', true)->count() }}</dd>
    </div>
    <div class="card p-5">
        <dt class="text-xs font-medium text-neutral-500 uppercase tracking-wide mb-1">Jumlah Mesej</dt>
        <dd class="text-3xl font-bold text-neutral-950">{{ $totalMessages }}</dd>
    </div>
    <div class="card p-5 sm:col-span-2 xl:col-span-1">
        <dt class="text-xs font-medium text-neutral-500 uppercase tracking-wide mb-1">Pelan Semasa</dt>
        <dd class="text-3xl font-bold text-neutral-950">{{ $subscription?->plan?->name ?? 'Percuma' }}</dd>
    </div>
</dl>

<section aria-labelledby="dashboard-chatbots-heading">
    <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
        <h2 id="dashboard-chatbots-heading" class="text-lg font-semibold text-neutral-950">Chatbot Anda</h2>
        <a href="{{ route('chatbots.create') }}" class="btn btn-primary self-start sm:self-auto">+ Chatbot Baru</a>
    </header>

    <div class="card overflow-hidden">
        @if($chatbots->isEmpty())
            <div class="px-5 py-14 sm:p-16 text-center">
                <div class="w-14 h-14 bg-brand-50 rounded-xl flex items-center justify-center mx-auto mb-4" aria-hidden="true">
                    <svg class="w-7 h-7 text-brand-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                </div>
                <h3 class="font-semibold text-neutral-950 mb-1">Tiada chatbot lagi</h3>
                <p class="text-sm text-neutral-600 mb-5">Cipta chatbot pertama anda sekarang.</p>
                <a href="{{ route('chatbots.create') }}" class="btn btn-primary">Cipta Chatbot</a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="data-table min-w-[50rem]">
                    <caption class="sr-only">Ringkasan chatbot anda</caption>
                    <thead>
                        <tr><th scope="col">Chatbot</th><th scope="col">Status</th><th scope="col">Pengetahuan</th><th scope="col">Kunci API</th><th scope="col">Tindakan</th></tr>
                    </thead>
                    <tbody>
                    @foreach($chatbots as $bot)
                        <tr>
                            <th scope="row" class="font-normal">
                                <div class="flex items-center gap-3">
                                    <img src="{{ $bot->resolvedAvatarUrl() }}" alt="Avatar {{ $bot->name }}" class="w-9 h-9 rounded-full object-cover ring-1 ring-neutral-200">
                                    <span><span class="block font-semibold text-sm text-neutral-950">{{ $bot->name }}</span><span class="block text-xs text-neutral-500">{{ $bot->slug }}</span></span>
                                </div>
                            </th>
                            <td>
                                <form action="{{ route('chatbots.toggle', $bot) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="badge {{ $bot->is_active ? 'badge-active' : 'badge-inactive' }}" aria-label="Tukar status {{ $bot->name }}. Status semasa: {{ $bot->is_active ? 'Aktif' : 'Tidak Aktif' }}">{{ $bot->is_active ? 'Aktif' : 'Tidak Aktif' }}</button>
                                </form>
                            </td>
                            <td>{{ $bot->knowledge_items_count }}</td>
                            <td><code class="text-xs font-mono text-neutral-600" aria-label="Lapan aksara pertama kunci API">{{ substr($bot->api_key, 0, 8) }}…</code></td>
                            <td>
                                <div class="flex flex-wrap gap-x-3 gap-y-2 text-sm">
                                    <a href="{{ route('chatbots.edit', $bot) }}" class="text-brand-700 font-medium hover:underline">Sunting</a>
                                    <a href="{{ route('chatbots.embed', $bot) }}" class="text-brand-700 font-medium hover:underline">Benam</a>
                                    <a href="{{ route('knowledge.index', $bot) }}" class="text-brand-700 font-medium hover:underline">Pengetahuan</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</section>
@endsection
