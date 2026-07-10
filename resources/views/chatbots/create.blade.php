@extends('layouts.app')
@section('page-title', 'Cipta Chatbot Baru')
@section('title', 'Cipta Chatbot')
@section('content')
<div class="max-w-2xl">
    <h1 class="text-2xl font-bold text-neutral-950 mb-6">Cipta Chatbot Baru</h1>
    <form action="{{ route('chatbots.store') }}" method="POST" class="card p-5 sm:p-8 space-y-5">
        @csrf
        <div>
            <label for="name" class="label">Nama Chatbot <span aria-hidden="true">*</span></label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required autocomplete="off" class="input" placeholder="Bot Sokongan Saya">
        </div>
        <div>
            <label for="bot_name" class="label">Nama Paparan Bot <span aria-hidden="true">*</span></label>
            <input id="bot_name" type="text" name="bot_name" value="{{ old('bot_name') }}" required autocomplete="off" class="input" placeholder="Pembantu Sokongan">
        </div>
        <div>
            <label for="welcome_message" class="label">Mesej Alu-aluan <span aria-hidden="true">*</span></label>
            <textarea id="welcome_message" name="welcome_message" required rows="3" class="input">{{ old('welcome_message', 'Helo! Bagaimana saya boleh bantu anda hari ini?') }}</textarea>
        </div>
        <div>
            <label for="placeholder_text" class="label">Teks Placeholder</label>
            <input id="placeholder_text" type="text" name="placeholder_text" value="{{ old('placeholder_text', 'Taip mesej anda...') }}" class="input">
        </div>
        <div>
            <label for="avatar_url" class="label">URL Avatar (pilihan)</label>
            <input id="avatar_url" type="url" name="avatar_url" value="{{ old('avatar_url') }}" inputmode="url" autocomplete="url" class="input" placeholder="https://example.com/avatar.png">
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="primary_color" class="label">Warna Utama</label>
                <input id="primary_color" type="color" name="primary_color" value="{{ old('primary_color', '#4F46E5') }}" class="input h-11 cursor-pointer p-1">
            </div>
            <div>
                <label for="position" class="label">Posisi</label>
                <select id="position" name="position" class="input">
                    <option value="bottom-right" @selected(old('position', 'bottom-right') === 'bottom-right')>Kanan Bawah</option>
                    <option value="bottom-left" @selected(old('position') === 'bottom-left')>Kiri Bawah</option>
                </select>
            </div>
        </div>
        <div>
            <label for="system_prompt" class="label">Arahan Sistem (pilihan)</label>
            <textarea id="system_prompt" name="system_prompt" rows="3" class="input" placeholder="Anda adalah pembantu yang membantu...">{{ old('system_prompt') }}</textarea>
        </div>
        <div>
            <label for="domain_whitelist" class="label">Senarai Putih Domain (pilihan, pisah dengan koma)</label>
            <input id="domain_whitelist" type="text" name="domain_whitelist" value="{{ old('domain_whitelist') }}" class="input" placeholder="example.com, lamanweb.com">
        </div>
        <button type="submit" class="btn btn-primary">Cipta Chatbot</button>
    </form>
</div>
@endsection
