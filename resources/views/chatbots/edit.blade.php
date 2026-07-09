@extends('layouts.app')
@section('page-title', 'Edit Chatbot')
@section('title', 'Sunting — ' . $chatbot->name)
@section('content')
<div class="max-w-2xl">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-white">Sunting: {{ $chatbot->name }}</h1>
        <div class="flex gap-2">
            <a href="{{ route('chatbots.embed', $chatbot) }}" class="text-emerald-400 bg-emerald-500/10 px-3 py-1.5 rounded-lg text-sm font-semibold hover:bg-emerald-100 transition">Dapatkan Kod Benam</a>
            <a href="{{ route('knowledge.index', $chatbot) }}" class="text-purple-700 bg-purple-50 px-3 py-1.5 rounded-lg text-sm font-semibold hover:bg-purple-100 transition">Pangkalan Pengetahuan</a>
        </div>
    </div>
    <form action="{{ route('chatbots.update', $chatbot) }}" method="POST" enctype="multipart/form-data" class="bg-white/[0.03] rounded-lg border border-white/[0.06] p-8 space-y-5">
        @csrf @method('PUT')
        <div>
            <label class="block text-sm font-semibold text-white/80 mb-1.5">Nama Chatbot</label>
            <input type="text" name="name" value="{{ $chatbot->name }}" required class="w-full border border-white/[0.06] rounded-lg px-4 py-3 focus:ring-2 focus:ring-brand-500 outline-none text-sm">
        </div>
        <div>
            <label class="block text-sm font-semibold text-white/80 mb-1.5">Nama Paparan Bot</label>
            <input type="text" name="bot_name" value="{{ $chatbot->bot_name }}" required class="w-full border border-white/[0.06] rounded-lg px-4 py-3 focus:ring-2 focus:ring-brand-500 outline-none text-sm">
        </div>
        <div>
            <label class="block text-sm font-semibold text-white/80 mb-1.5">Mesej Alu-aluan</label>
            <textarea name="welcome_message" rows="2" required class="w-full border border-white/[0.06] rounded-lg px-4 py-3 focus:ring-2 focus:ring-brand-500 outline-none text-sm">{{ $chatbot->welcome_message }}</textarea>
        </div>
        <div>
            <label class="block text-sm font-semibold text-white/80 mb-1.5">Teks Placeholder</label>
            <input type="text" name="placeholder_text" value="{{ $chatbot->placeholder_text }}" class="w-full border border-white/[0.06] rounded-lg px-4 py-3 focus:ring-2 focus:ring-brand-500 outline-none text-sm">
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-white/80 mb-1.5">Warna Utama</label>
                <input type="color" name="primary_color" value="{{ $chatbot->primary_color }}" class="w-full h-11 rounded-lg border border-white/[0.06] cursor-pointer">
            </div>
            <div>
                <label class="block text-sm font-semibold text-white/80 mb-1.5">Posisi</label>
                <select name="position" class="w-full border border-white/[0.06] rounded-lg px-4 py-3 focus:ring-2 focus:ring-brand-500 outline-none text-sm">
                    <option value="bottom-right" {{ $chatbot->position == 'bottom-right' ? 'selected' : '' }}>Kanan Bawah</option>
                    <option value="bottom-left" {{ $chatbot->position == 'bottom-left' ? 'selected' : '' }}>Kiri Bawah</option>
                </select>
            </div>
        </div>
        <div>
            <label class="block text-sm font-semibold text-white/80 mb-1.5">Arahan Sistem</label>
            <textarea name="system_prompt" rows="2" class="w-full border border-white/[0.06] rounded-lg px-4 py-3 focus:ring-2 focus:ring-brand-500 outline-none text-sm">{{ $chatbot->system_prompt }}</textarea>
        </div>
        <div>
            <label class="block text-sm font-semibold text-white/80 mb-1.5">Senarai Putih Domain</label>
            <input type="text" name="domain_whitelist" value="{{ $chatbot->domain_whitelist }}" class="w-full border border-white/[0.06] rounded-lg px-4 py-3 focus:ring-2 focus:ring-brand-500 outline-none text-sm" placeholder="example.com, lamanweb.com">
        </div>
        <div class="flex items-center gap-3">
            <input type="checkbox" name="is_active" value="1" {{ $chatbot->is_active ? 'checked' : '' }} class="rounded border-white/[0.06] text-white focus:ring-brand-500">
            <label class="text-sm text-white/80 font-medium">Aktif (kelihatan di laman web)</label>
        </div>
        <button type="submit" class="bg-white text-[#050505] px-6 py-3 rounded-lg font-semibold text-sm hover:bg-white/90 transition">Simpan Perubahan</button>
    </form>
</div>
@endsection
