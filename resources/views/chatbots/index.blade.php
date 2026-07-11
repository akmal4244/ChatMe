@extends('layouts.app')
@section('page-title', 'Chatbot saya')
@section('title', 'Chatbot saya — ChatMe')
@section('content')
<header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-6">
    <h1 class="text-2xl font-bold text-neutral-950">Chatbot saya</h1>
    <a href="{{ route('chatbots.create') }}" class="btn btn-primary self-start sm:self-auto">+ Cipta chatbot baharu</a>
</header>
<div class="card overflow-hidden">
    @if($chatbots->isEmpty())
        <div class="px-5 py-14 sm:p-16 text-center">
            <h2 class="text-lg font-semibold text-neutral-950 mb-2">Tiada chatbot lagi</h2>
            <p class="text-neutral-600 mb-5">Cipta chatbot AI pertama anda dalam masa kurang 2 minit.</p>
            <a href="{{ route('chatbots.create') }}" class="btn btn-primary">Cipta chatbot pertama</a>
        </div>
    @else
        <div class="overflow-x-auto">
        <table class="data-table min-w-[52rem]">
            <caption class="sr-only">Senarai chatbot milik anda</caption>
            <thead>
                <tr><th scope="col">Chatbot</th><th scope="col">Status</th><th scope="col">Soal jawab</th><th scope="col">Mesej</th><th scope="col">Tindakan</th></tr>
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
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('chatbots.edit', $bot) }}" class="table-action" aria-label="Sunting chatbot {{ $bot->name }}" title="Sunting chatbot">
                                <i class="ph ph-pencil-simple" aria-hidden="true"></i>
                            </a>
                            <a href="{{ route('knowledge.index', $bot) }}" class="table-action" aria-label="Urus soal jawab {{ $bot->name }}" title="Urus soal jawab">
                                <i class="ph ph-books" aria-hidden="true"></i>
                            </a>
                            <a href="{{ route('chatbots.embed', $bot) }}" class="table-action" aria-label="Pasang {{ $bot->name }} di laman web" title="Pasang di laman web">
                                <i class="ph ph-code" aria-hidden="true"></i>
                            </a>
                            <form action="{{ route('chatbots.destroy', $bot) }}" method="POST" class="inline-flex"
                                  data-confirm-title="Padam chatbot?"
                                  data-confirm-description="Padam chatbot {{ $bot->name }}? Semua soal jawab dan sejarah sembang berkaitan akan dipadam. Tindakan ini tidak boleh dibatalkan."
                                  data-confirm-text="Padam chatbot"
                                  data-confirm-type="danger">@csrf @method('DELETE')
                                <button type="submit" class="table-action table-action-danger" aria-label="Padam chatbot {{ $bot->name }}" title="Padam chatbot">
                                    <i class="ph ph-trash" aria-hidden="true"></i>
                                </button>
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
