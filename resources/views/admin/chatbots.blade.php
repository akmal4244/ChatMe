@extends('layouts.app')
@section('page-title', 'Semua chatbot')
@section('title', 'Semua chatbot — Panel pentadbir')
@section('content')
<header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-neutral-950">Semua chatbot</h1>
        <p class="text-sm text-neutral-600 mt-1">{{ $chatbots->total() }} chatbot berdaftar</p>
    </div>
    <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost self-start sm:self-auto">&larr; Kembali ke panel</a>
</header>
<div class="card overflow-hidden">
    <div class="overflow-x-auto">
    <table class="data-table min-w-[44rem]">
        <caption class="sr-only">Senarai semua chatbot yang didaftarkan</caption>
        <thead>
            <tr><th scope="col">Chatbot</th><th scope="col">Pemilik</th><th scope="col">Soal jawab</th><th scope="col">Status</th><th scope="col">Tarikh</th></tr>
        </thead>
        <tbody>
            @forelse($chatbots as $bot)
            <tr>
                <th scope="row"><span class="block text-sm font-semibold text-neutral-950">{{ $bot->name }}</span><span class="block text-xs font-normal text-neutral-500">{{ $bot->slug }}</span></th>
                <td>{{ $bot->user->name ?? 'Tiada maklumat' }}</td>
                <td>{{ $bot->knowledge_items_count }}</td>
                <td><span class="badge {{ $bot->is_active ? 'badge-active' : 'badge-inactive' }}">{{ $bot->is_active ? 'Aktif' : 'Tidak Aktif' }}</span></td>
                <td><time datetime="{{ $bot->created_at->toDateString() }}">{{ $bot->created_at->format('d/m/Y') }}</time></td>
            </tr>
            @empty
            <tr><td colspan="5" class="text-center text-neutral-600">Belum ada chatbot dicipta.</td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
</div>
<div class="mt-4">{{ $chatbots->links() }}</div>
@endsection
