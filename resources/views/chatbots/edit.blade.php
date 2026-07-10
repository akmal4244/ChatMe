@extends('layouts.app')
@section('page-title', 'Edit Chatbot')
@section('title', 'Sunting — ' . $chatbot->name)
@section('content')
<div class="max-w-2xl">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
        <h1 class="text-2xl font-bold text-neutral-950">Sunting: {{ $chatbot->name }}</h1>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('chatbots.embed', $chatbot) }}" class="btn btn-secondary btn-sm">Dapatkan Kod Benam</a>
            <a href="{{ route('knowledge.index', $chatbot) }}" class="btn btn-secondary btn-sm">Pangkalan Pengetahuan</a>
        </div>
    </header>
    <form action="{{ route('chatbots.update', $chatbot) }}" method="POST" class="card p-5 sm:p-8 space-y-5">
        @csrf @method('PUT')
        <div>
            <label for="name" class="label">Nama Chatbot</label>
            <input id="name" type="text" name="name" value="{{ old('name', $chatbot->name) }}" required class="input">
        </div>
        <div>
            <label for="bot_name" class="label">Nama Paparan Bot</label>
            <input id="bot_name" type="text" name="bot_name" value="{{ old('bot_name', $chatbot->bot_name) }}" required class="input">
        </div>
        <div>
            <label for="welcome_message" class="label">Mesej Alu-aluan</label>
            <textarea id="welcome_message" name="welcome_message" rows="3" required class="input">{{ old('welcome_message', $chatbot->welcome_message) }}</textarea>
        </div>
        <div>
            <label for="placeholder_text" class="label">Teks Placeholder</label>
            <input id="placeholder_text" type="text" name="placeholder_text" value="{{ old('placeholder_text', $chatbot->placeholder_text) }}" class="input">
        </div>
        <div>
            <label for="avatar_url" class="label">URL Avatar (pilihan)</label>
            <input id="avatar_url" type="url" name="avatar_url" value="{{ old('avatar_url', filter_var($chatbot->avatar_url, FILTER_VALIDATE_URL) ? $chatbot->avatar_url : '') }}" inputmode="url" autocomplete="url" class="input" placeholder="https://example.com/avatar.png">
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="primary_color" class="label">Warna Utama</label>
                <input id="primary_color" type="color" name="primary_color" value="{{ old('primary_color', $chatbot->primary_color) }}" class="input h-11 cursor-pointer p-1">
            </div>
            <div>
                <label for="position" class="label">Posisi</label>
                <select id="position" name="position" class="input">
                    <option value="bottom-right" @selected(old('position', $chatbot->position) === 'bottom-right')>Kanan Bawah</option>
                    <option value="bottom-left" @selected(old('position', $chatbot->position) === 'bottom-left')>Kiri Bawah</option>
                </select>
            </div>
        </div>
        <div>
            <label for="system_prompt" class="label">Arahan Sistem</label>
            <textarea id="system_prompt" name="system_prompt" rows="3" class="input">{{ old('system_prompt', $chatbot->system_prompt) }}</textarea>
        </div>
        <div>
            <label for="domain_whitelist" class="label">Senarai Putih Domain</label>
            <input id="domain_whitelist" type="text" name="domain_whitelist" value="{{ old('domain_whitelist', $chatbot->domain_whitelist) }}" class="input" placeholder="example.com, lamanweb.com">
        </div>
        <div class="flex items-center gap-3">
            <input id="is_active" type="checkbox" name="is_active" value="1" @checked(old('is_active', $chatbot->is_active)) class="h-4 w-4 rounded border-neutral-300 text-brand-600 focus:ring-brand-500">
            <label for="is_active" class="text-sm text-neutral-700 font-medium">Aktif (kelihatan di laman web)</label>
        </div>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
    </form>
</div>
@endsection
