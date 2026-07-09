@extends('layouts.app')
@section('page-title', 'Urus Pengguna')
@section('title', 'Pengurusan Pengguna — Panel Pentadbir')
@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-white">Pengurusan Pengguna</h1>
        <p class="text-sm text-white/25 mt-1">{{ $users->total() }} pengguna berdaftar</p>
    </div>
    <a href="{{ route('admin.dashboard') }}" class="text-sm text-white/25 hover:text-white/80">&larr; Kembali ke Panel</a>
</div>
<div class="bg-white/[0.03] rounded-lg border border-white/[0.06] overflow-hidden">
    <table class="w-full">
        <thead class="bg-white/[0.03] text-left text-xs font-medium text-white/25 uppercase tracking-wide">
            <tr><th class="px-6 py-3">Pengguna</th><th class="px-6 py-3">Email</th><th class="px-6 py-3">Chatbot</th><th class="px-6 py-3">Peranan</th><th class="px-6 py-3">Tarikh Daftar</th><th class="px-6 py-3">Tindakan</th></tr>
        </thead>
        <tbody class="divide-y divide-white/[0.06]">
            @foreach($users as $u)
            <tr class="hover:bg-white/[0.03] transition-colors">
                <td class="px-6 py-4"><p class="text-sm font-semibold text-white">{{ $u->name }}</p></td>
                <td class="px-6 py-4 text-sm text-white/25">{{ $u->email }}</td>
                <td class="px-6 py-4 text-sm text-white/25">{{ $u->chatbots_count }}</td>
                <td class="px-6 py-4"><span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $u->is_admin ? 'bg-white/[0.03] text-indigo-700' : 'bg-white/[0.03] text-white/25' }}">{{ $u->is_admin ? 'Pentadbir' : 'Pengguna' }}</span></td>
                <td class="px-6 py-4 text-xs text-white/25">{{ $u->created_at->format('d/m/Y') }}</td>
                <td class="px-6 py-4">
                    @if($u->id !== auth()->id())
                    <form action="{{ route('admin.users.toggle-admin', $u) }}" method="POST">
                        @csrf
                        <button class="text-xs font-medium {{ $u->is_admin ? 'text-red-600 hover:text-red-700' : 'text-white hover:text-indigo-700' }}">
                            {{ $u->is_admin ? 'Buang Admin' : 'Jadikan Admin' }}
                        </button>
                    </form>
                    @else
                    <span class="text-xs text-white/25">Anda</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $users->links() }}</div>
@endsection