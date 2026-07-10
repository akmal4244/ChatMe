@extends('layouts.app')
@section('page-title', 'Cipta chatbot baharu')
@section('title', 'Cipta chatbot')
@section('content')
<div class="max-w-2xl">
    <h1 class="text-2xl font-bold text-neutral-950 mb-6">Cipta chatbot baharu</h1>
    <form action="{{ route('chatbots.store') }}" method="POST" class="card p-5 sm:p-8 space-y-5">
        @csrf
        <div>
            <label for="name" class="label">Nama chatbot <span aria-hidden="true">*</span></label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required autocomplete="off" class="input" placeholder="Bot Sokongan Saya" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
            @error('name') <p id="name-error" class="field-error" role="alert">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="bot_name" class="label">Nama yang dipaparkan kepada pengunjung <span aria-hidden="true">*</span></label>
            <input id="bot_name" type="text" name="bot_name" value="{{ old('bot_name') }}" required autocomplete="off" class="input" placeholder="Pembantu Sokongan" @error('bot_name') aria-invalid="true" aria-describedby="bot-name-error" @enderror>
            @error('bot_name') <p id="bot-name-error" class="field-error" role="alert">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="welcome_message" class="label">Mesej alu-aluan <span aria-hidden="true">*</span></label>
            <textarea id="welcome_message" name="welcome_message" required rows="3" class="input" @error('welcome_message') aria-invalid="true" aria-describedby="welcome-message-error" @enderror>{{ old('welcome_message', 'Helo! Bagaimana saya boleh bantu anda hari ini?') }}</textarea>
            @error('welcome_message') <p id="welcome-message-error" class="field-error" role="alert">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="placeholder_text" class="label">Teks petunjuk dalam kotak mesej</label>
            <input id="placeholder_text" type="text" name="placeholder_text" value="{{ old('placeholder_text', 'Taip mesej anda...') }}" class="input" @error('placeholder_text') aria-invalid="true" aria-describedby="placeholder-text-error" @enderror>
            @error('placeholder_text') <p id="placeholder-text-error" class="field-error" role="alert">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="avatar_url" class="label">Pautan gambar profil (pilihan)</label>
            <input id="avatar_url" type="url" name="avatar_url" value="{{ old('avatar_url') }}" inputmode="url" autocomplete="url" class="input" placeholder="https://example.com/avatar.png" @error('avatar_url') aria-invalid="true" aria-describedby="avatar-url-error" @enderror>
            @error('avatar_url') <p id="avatar-url-error" class="field-error" role="alert">{{ $message }}</p> @enderror
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="primary_color" class="label">Warna utama</label>
                <input id="primary_color" type="color" name="primary_color" value="{{ old('primary_color', '#4F46E5') }}" class="input h-11 cursor-pointer p-1" @error('primary_color') aria-invalid="true" aria-describedby="primary-color-error" @enderror>
                @error('primary_color') <p id="primary-color-error" class="field-error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="position" class="label">Kedudukan pada skrin</label>
                <select id="position" name="position" class="input" @error('position') aria-invalid="true" aria-describedby="position-error" @enderror>
                    <option value="bottom-right" @selected(old('position', 'bottom-right') === 'bottom-right')>Kanan bawah</option>
                    <option value="bottom-left" @selected(old('position') === 'bottom-left')>Kiri bawah</option>
                </select>
                @error('position') <p id="position-error" class="field-error" role="alert">{{ $message }}</p> @enderror
            </div>
        </div>
        <div>
            <label for="system_prompt" class="label">Cara chatbot perlu menjawab (pilihan)</label>
            <textarea id="system_prompt" name="system_prompt" rows="3" class="input" placeholder="Anda adalah pembantu yang membantu..." @error('system_prompt') aria-invalid="true" aria-describedby="system-prompt-error" @enderror>{{ old('system_prompt') }}</textarea>
            @error('system_prompt') <p id="system-prompt-error" class="field-error" role="alert">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="domain_whitelist" class="label">Laman web yang dibenarkan (pilihan, pisahkan dengan koma)</label>
            <input id="domain_whitelist" type="text" name="domain_whitelist" value="{{ old('domain_whitelist') }}" class="input" placeholder="example.com, lamanweb.com" @error('domain_whitelist') aria-invalid="true" aria-describedby="domain-whitelist-error" @enderror>
            @error('domain_whitelist') <p id="domain-whitelist-error" class="field-error" role="alert">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="btn btn-primary">Cipta chatbot</button>
    </form>
</div>
@endsection
