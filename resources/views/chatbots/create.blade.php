@extends('layouts.app')
@section('page-title', 'Cipta Chatbot Baru')
@section('title', 'Cipta Chatbot')
@section('content')
<div class="max-w-2xl">
    <h1 class="text-2xl font-bold text-white mb-6">Cipta Chatbot Baru</h1>
    <form action="{{ route('chatbots.store') }}" method="POST" enctype="multipart/form-data" class="bg-white/[0.03] rounded-lg border border-white/[0.06] p-8 space-y-5">
        @csrf
        <div>
            <label class="block text-sm font-semibold text-white/80 mb-1.5">Nama Chatbot *</label>
            <input type="text" name="name" required class="w-full border border-white/[0.06] rounded-lg px-4 py-3 focus:ring-2 focus:ring-brand-500 outline-none text-sm" placeholder="Bot Sokongan Saya">
        </div>
        <div>
            <label class="block text-sm font-semibold text-white/80 mb-1.5">Nama Paparan Bot *</label>
            <input type="text" name="bot_name" required class="w-full border border-white/[0.06] rounded-lg px-4 py-3 focus:ring-2 focus:ring-brand-500 outline-none text-sm" placeholder="Pembantu Sokongan">
        </div>
        <div>
            <label class="block text-sm font-semibold text-white/80 mb-1.5">Mesej Alu-aluan *</label>
            <textarea name="welcome_message" required rows="2" class="w-full border border-white/[0.06] rounded-lg px-4 py-3 focus:ring-2 focus:ring-brand-500 outline-none text-sm">Helo! Bagaimana saya boleh bantu anda hari ini?</textarea>
        </div>
        <div>
            <label class="block text-sm font-semibold text-white/80 mb-1.5">Teks Placeholder</label>
            <input type="text" name="placeholder_text" value="Taip mesej anda..." class="w-full border border-white/[0.06] rounded-lg px-4 py-3 focus:ring-2 focus:ring-brand-500 outline-none text-sm">
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-white/80 mb-1.5">Warna Utama</label>
                <input type="color" name="primary_color" value="#4F46E5" class="w-full h-11 rounded-lg border border-white/[0.06] cursor-pointer">
            </div>
            <div>
                <label class="block text-sm font-semibold text-white/80 mb-1.5">Posisi</label>
                <select name="position" class="w-full border border-white/[0.06] rounded-lg px-4 py-3 focus:ring-2 focus:ring-brand-500 outline-none text-sm">
                    <option value="bottom-right">Kanan Bawah</option>
                    <option value="bottom-left">Kiri Bawah</option>
                </select>
            </div>
        </div>
        <div>
            <label class="block text-sm font-semibold text-white/80 mb-1.5">Arahan Sistem (pilihan)</label>
            <textarea name="system_prompt" rows="2" class="w-full border border-white/[0.06] rounded-lg px-4 py-3 focus:ring-2 focus:ring-brand-500 outline-none text-sm" placeholder="Anda adalah pembantu yang membantu..."></textarea>
        </div>
        <div>
            <label class="block text-sm font-semibold text-white/80 mb-1.5">Senarai Putih Domain (pilihan, pisah dengan koma)</label>
            <input type="text" name="domain_whitelist" class="w-full border border-white/[0.06] rounded-lg px-4 py-3 focus:ring-2 focus:ring-brand-500 outline-none text-sm" placeholder="example.com, lamanweb.com">
        </div>
        <button type="submit" class="bg-white text-[#050505] px-6 py-3 rounded-lg font-semibold text-sm hover:bg-white/90 transition">Cipta Chatbot</button>
    </form>
</div>
@endsection
