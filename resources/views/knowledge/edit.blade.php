@extends('layouts.app')
@section('title', 'Sunting Pengetahuan — ' . $chatbot->name)
@section('content')
<div class="max-w-2xl">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('knowledge.index', $chatbot) }}" class="text-white/25 hover:text-white/40 transition">&larr; Kembali</a>
    </div>
    <div class="bg-white/[0.03] rounded-lg border border-white/[0.06] p-6">
        <h2 class="text-lg font-semibold text-white">Sunting Item Pengetahuan</h2>
        <p class="text-sm text-white/25 mt-1">Kemaskini item ini. Perubahan akan mempengaruhi respons chatbot.</p>
        <form action="{{ route('knowledge.update', [$chatbot, $knowledge]) }}" method="POST" class="mt-6 space-y-5">
            @csrf
            @method('PUT')
            <div>
                <label for="question" class="block text-sm font-medium text-white/80">Soalan</label>
                <input id="question" name="question" type="text" value="{{ old('question', $knowledge->question) }}" required
                       class="mt-1.5 block w-full rounded-lg border border-white/[0.06] px-3.5 py-2.5 text-white placeholder-neutral-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-200 outline-none transition-shadow sm:text-sm">
            </div>
            <div>
                <label for="answer" class="block text-sm font-medium text-white/80">Jawapan</label>
                <textarea id="answer" name="answer" rows="8" required
                          class="mt-1.5 block w-full rounded-lg border border-white/[0.06] px-3.5 py-2.5 text-white placeholder-neutral-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-200 outline-none transition-shadow sm:text-sm">{{ old('answer', $knowledge->answer) }}</textarea>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="category" class="block text-sm font-medium text-white/80">Kategori</label>
                    <input id="category" name="category" type="text" value="{{ old('category', $knowledge->category) }}"
                           class="mt-1.5 block w-full rounded-lg border border-white/[0.06] px-3.5 py-2.5 text-white focus:border-brand-500 focus:ring-2 focus:ring-brand-200 outline-none transition-shadow sm:text-sm">
                </div>
                <div>
                    <label for="tags" class="block text-sm font-medium text-white/80">Tag (pisah dengan koma)</label>
                    <input id="tags" name="tags" type="text" value="{{ old('tags', $knowledge->tags) }}"
                           class="mt-1.5 block w-full rounded-lg border border-white/[0.06] px-3.5 py-2.5 text-white focus:border-brand-500 focus:ring-2 focus:ring-brand-200 outline-none transition-shadow sm:text-sm">
                </div>
            </div>
            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="bg-white text-[#050505] px-5 py-2.5 rounded-lg font-semibold text-sm hover:bg-white/90 transition">Simpan</button>
                <a href="{{ route('knowledge.index', $chatbot) }}" class="text-sm font-medium text-white/25 hover:text-white/80">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection