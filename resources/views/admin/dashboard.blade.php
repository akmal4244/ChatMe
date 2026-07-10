@extends('layouts.app')
@section('page-title', 'Panel pentadbir')
@section('title', 'Panel pentadbir — ChatMe')
@section('content')
<header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-neutral-950">Panel pentadbir</h1>
        <p class="text-sm text-neutral-600 mt-1">Selamat datang, {{ auth()->user()->name }}. Anda log masuk sebagai pentadbir.</p>
    </div>
    <span class="badge badge-admin self-start sm:self-auto">Pentadbir</span>
</header>

<dl class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
    <div class="card p-5">
        <dt class="text-xs font-medium text-neutral-500 uppercase tracking-wide mb-1">Jumlah pengguna</dt>
        <dd class="text-3xl font-bold text-neutral-950">{{ $stats['total_users'] }}</dd>
    </div>
    <div class="card p-5">
        <dt class="text-xs font-medium text-neutral-500 uppercase tracking-wide mb-1">Jumlah chatbot</dt>
        <dd class="text-3xl font-bold text-neutral-950">{{ $stats['total_chatbots'] }}</dd>
    </div>
    <div class="card p-5">
        <dt class="text-xs font-medium text-neutral-500 uppercase tracking-wide mb-1">Jumlah mesej</dt>
        <dd class="text-3xl font-bold text-neutral-950">{{ $stats['total_messages'] }}</dd>
    </div>
    <div class="card p-5">
        <dt class="text-xs font-medium text-neutral-500 uppercase tracking-wide mb-1">Hari ini</dt>
        <dd class="text-3xl font-bold text-neutral-950">{{ $stats['messages_today'] }}</dd>
    </div>
</dl>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <section class="card overflow-hidden" aria-labelledby="recent-users-heading">
        <div class="px-5 sm:px-6 py-4 border-b border-neutral-200 flex items-center justify-between gap-4">
            <h2 id="recent-users-heading" class="font-semibold text-neutral-950">Pengguna terkini</h2>
            <a href="{{ route('admin.users') }}" class="text-sm text-brand-700 font-medium hover:underline">Lihat semua</a>
        </div>
        <div class="overflow-x-auto">
        <table class="data-table min-w-[36rem]">
            <caption class="sr-only">Senarai pengguna yang baru mendaftar</caption>
            <thead>
                <tr><th scope="col">Nama</th><th scope="col">E-mel</th><th scope="col">Chatbot</th><th scope="col">Tarikh</th></tr>
            </thead>
            <tbody>
                @foreach($recent_users as $u)
                <tr>
                    <th scope="row" class="font-medium text-neutral-950">{{ $u->name }}</th>
                    <td>{{ $u->email }}</td>
                    <td>{{ $u->chatbots_count ?? 0 }}</td>
                    <td><time datetime="{{ $u->created_at->toDateString() }}">{{ $u->created_at->format('d/m/Y') }}</time></td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </section>
    <section class="card overflow-hidden" aria-labelledby="recent-chatbots-heading">
        <div class="px-5 sm:px-6 py-4 border-b border-neutral-200 flex items-center justify-between gap-4">
            <h2 id="recent-chatbots-heading" class="font-semibold text-neutral-950">Chatbot terkini</h2>
            <a href="{{ route('admin.chatbots') }}" class="text-sm text-brand-700 font-medium hover:underline">Lihat semua</a>
        </div>
        <div class="overflow-x-auto">
        <table class="data-table min-w-[36rem]">
            <caption class="sr-only">Senarai chatbot yang baru dicipta</caption>
            <thead>
                <tr><th scope="col">Nama</th><th scope="col">Pemilik</th><th scope="col">Status</th><th scope="col">Tarikh</th></tr>
            </thead>
            <tbody>
                @foreach($recent_chatbots as $bot)
                <tr>
                    <th scope="row" class="font-medium text-neutral-950">{{ $bot->name }}</th>
                    <td>{{ $bot->user->name ?? 'Tiada maklumat' }}</td>
                    <td><span class="badge {{ $bot->is_active ? 'badge-active' : 'badge-inactive' }}">{{ $bot->is_active ? 'Aktif' : 'Tidak Aktif' }}</span></td>
                    <td><time datetime="{{ $bot->created_at->toDateString() }}">{{ $bot->created_at->format('d/m/Y') }}</time></td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </section>
</div>
@endsection
