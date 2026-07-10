@extends('layouts.app')
@section('page-title', 'Chatbot Saya')
@section('title', 'Chatbot Saya — ChatMe')
@section('content')
<header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-6">
    <h1 class="text-2xl font-bold text-neutral-950">Chatbot Saya</h1>
    <a href="{{ route('chatbots.create') }}" class="btn btn-primary self-start sm:self-auto">+ Chatbot Baru</a>
</header>
<div class="card overflow-hidden">
    @if($chatbots->isEmpty())
        <div class="px-5 py-14 sm:p-16 text-center">
            <h2 class="text-lg font-semibold text-neutral-950 mb-2">Tiada chatbot lagi</h2>
            <p class="text-neutral-600 mb-5">Cipta chatbot AI pertama anda dalam masa kurang 2 minit.</p>
            <a href="{{ route('chatbots.create') }}" class="btn btn-primary">Cipta Chatbot Pertama Anda</a>
        </div>
    @else
        <div class="overflow-x-auto">
        <table class="data-table min-w-[52rem]">
            <caption class="sr-only">Senarai chatbot milik anda</caption>
            <thead>
                <tr><th scope="col">Chatbot</th><th scope="col">Status</th><th scope="col">Pengetahuan</th><th scope="col">Mesej</th><th scope="col">Tindakan</th></tr>
            </thead>
            <tbody>
                @foreach($chatbots as $bot)
                <tr>
                    <th scope="row" class="font-normal">
                        <div class="flex items-center gap-3">
                            <img src="{{ $bot->resolvedAvatarUrl() }}" alt="Avatar {{ $bot->name }}" class="w-10 h-10 rounded-full object-cover ring-1 ring-neutral-200">
                            <span><span class="block font-semibold text-neutral-950 text-sm">{{ $bot->name }}</span><span class="block text-xs text-neutral-500">{{ $bot->slug }}</span></span>
                        </div>
                    </th>
                    <td>
                        <form action="{{ route('chatbots.toggle', $bot) }}" method="POST">@csrf
                            <button type="submit" class="badge {{ $bot->is_active ? 'badge-active' : 'badge-inactive' }}" aria-label="Tukar status {{ $bot->name }}. Status semasa: {{ $bot->is_active ? 'Aktif' : 'Tidak Aktif' }}">{{ $bot->is_active ? 'Aktif' : 'Tidak Aktif' }}</button>
                        </form>
                    </td>
                    <td>{{ $bot->knowledge_items_count }} item</td>
                    <td>{{ $bot->chatLogs()->count() }}</td>
                    <td>
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-2 text-sm">
                            <a href="{{ route('chatbots.edit', $bot) }}" class="text-brand-700 font-medium hover:underline">Sunting</a>
                            <a href="{{ route('knowledge.index', $bot) }}" class="text-brand-700 font-medium hover:underline">Pengetahuan</a>
                            <a href="{{ route('chatbots.embed', $bot) }}" class="text-brand-700 font-medium hover:underline">Benam</a>
                            <form action="{{ route('chatbots.destroy', $bot) }}" method="POST" onsubmit="return confirm('Padam chatbot ini?')" class="inline">@csrf @method('DELETE')
                                <button type="submit" class="text-red-700 font-medium hover:underline">Padam</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>
@if($chatbots->hasPages())
    <div class="mt-4">{{ $chatbots->links() }}</div>
@endif
@endsection
