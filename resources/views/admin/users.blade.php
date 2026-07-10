@extends('layouts.app')
@section('page-title', 'Urus Pengguna')
@section('title', 'Pengurusan Pengguna — Panel Pentadbir')
@section('content')
<header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-neutral-950">Pengurusan Pengguna</h1>
        <p class="text-sm text-neutral-600 mt-1">{{ $users->total() }} pengguna berdaftar</p>
    </div>
    <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost self-start sm:self-auto">&larr; Kembali ke Panel</a>
</header>
<div class="card overflow-hidden">
    <div class="overflow-x-auto">
    <table class="data-table min-w-[52rem]">
        <caption class="sr-only">Senarai semua pengguna ChatMe</caption>
        <thead>
            <tr><th scope="col">Pengguna</th><th scope="col">Email</th><th scope="col">Chatbot</th><th scope="col">Peranan</th><th scope="col">Tarikh Daftar</th><th scope="col">Tindakan</th></tr>
        </thead>
        <tbody>
            @foreach($users as $u)
            <tr>
                <th scope="row" class="font-semibold text-neutral-950">{{ $u->name }}</th>
                <td>{{ $u->email }}</td>
                <td>{{ $u->chatbots_count }}</td>
                <td><span class="badge {{ $u->is_admin ? 'badge-admin' : 'badge-inactive' }}">{{ $u->is_admin ? 'Pentadbir' : 'Pengguna' }}</span></td>
                <td><time datetime="{{ $u->created_at->toDateString() }}">{{ $u->created_at->format('d/m/Y') }}</time></td>
                <td>
                    @if($u->id !== auth()->id())
                    <form action="{{ route('admin.users.toggle-admin', $u) }}" method="POST">
                        @csrf
                        <button type="submit" class="text-xs font-medium {{ $u->is_admin ? 'text-red-700 hover:text-red-800' : 'text-brand-700 hover:text-brand-800' }}" aria-label="{{ $u->is_admin ? 'Buang peranan pentadbir daripada '.$u->name : 'Jadikan '.$u->name.' sebagai pentadbir' }}">
                            {{ $u->is_admin ? 'Buang Admin' : 'Jadikan Admin' }}
                        </button>
                    </form>
                    @else
                    <span class="text-xs text-neutral-500">Anda</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>
</div>
<div class="mt-4">{{ $users->links() }}</div>
@endsection
