@extends('layouts.app')
@section('page-title', 'Urus pengguna')
@section('title', 'Pengurusan pengguna — Panel pentadbir')
@section('content')
<header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-neutral-950">Pengurusan pengguna</h1>
        <p class="text-sm text-neutral-600 mt-1">{{ $users->total() }} pengguna berdaftar</p>
    </div>
    <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost self-start sm:self-auto">&larr; Kembali ke panel</a>
</header>
<div class="card overflow-hidden">
    <div class="overflow-x-auto">
    <table class="data-table min-w-[52rem]">
        <caption class="sr-only">Senarai semua pengguna ChatMe</caption>
        <thead>
            <tr><th scope="col">Pengguna</th><th scope="col">E-mel</th><th scope="col">Chatbot</th><th scope="col">Peranan</th><th scope="col">Tarikh daftar</th><th scope="col">Tindakan</th></tr>
        </thead>
        <tbody>
            @forelse($users as $u)
            <tr>
                <th scope="row" class="font-semibold text-neutral-950">{{ $u->name }}</th>
                <td>{{ $u->email }}</td>
                <td>{{ $u->chatbots_count }}</td>
                <td><span class="badge {{ $u->is_admin ? 'badge-admin' : 'badge-inactive' }}">{{ $u->is_admin ? 'Pentadbir' : 'Pengguna' }}</span></td>
                <td><time datetime="{{ $u->created_at->toDateString() }}">{{ $u->created_at->format('d/m/Y') }}</time></td>
                <td>
                    @if($u->id !== auth()->id())
                    <form action="{{ route('admin.users.toggle-admin', $u) }}" method="POST" class="inline-flex"
                          data-confirm-title="{{ $u->is_admin ? 'Tarik balik peranan pentadbir?' : 'Jadikan pentadbir?' }}"
                          data-confirm-description="{{ $u->is_admin ? 'Tarik balik peranan pentadbir '.$u->name.'? Pengguna ini tidak lagi boleh mengakses panel pentadbir.' : 'Jadikan '.$u->name.' sebagai pentadbir? Pengguna ini akan mendapat akses ke panel pentadbir.' }}"
                          data-confirm-text="{{ $u->is_admin ? 'Tarik balik' : 'Jadikan pentadbir' }}"
                          data-confirm-type="{{ $u->is_admin ? 'danger' : 'default' }}">
                        @csrf
                        <button type="submit" class="table-action {{ $u->is_admin ? 'table-action-danger' : '' }}" aria-label="{{ $u->is_admin ? 'Buang peranan pentadbir daripada '.$u->name : 'Jadikan '.$u->name.' sebagai pentadbir' }}" title="{{ $u->is_admin ? 'Tarik balik pentadbir' : 'Jadikan pentadbir' }}">
                            <i class="ph {{ $u->is_admin ? 'ph-shield-slash' : 'ph-shield-plus' }}" aria-hidden="true"></i>
                        </button>
                    </form>
                    @else
                    <span class="text-xs text-neutral-500">Anda</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="6" class="text-center text-neutral-600">Belum ada pengguna berdaftar.</td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
</div>
<div class="mt-4">{{ $users->links() }}</div>
@endsection
