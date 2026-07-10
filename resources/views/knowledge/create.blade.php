@extends('layouts.app')
@section('page-title', 'Tambah Pengetahuan')
@section('title', 'Tambah Pengetahuan — ' . $chatbot->name)
@section('content')
<div class="max-w-2xl">
    <a href="{{ route('knowledge.index', $chatbot) }}" class="btn btn-ghost mb-6">&larr; Kembali</a>
    <section class="card p-5 sm:p-6" aria-labelledby="knowledge-create-heading">
        <h1 id="knowledge-create-heading" class="text-xl font-semibold text-neutral-950">Tambah Item Pengetahuan</h1>
        <p class="text-sm text-neutral-600 mt-1">Tambah pasangan Soal-Jawab supaya chatbot anda boleh menjawab soalan secara automatik.</p>
        <form action="{{ route('knowledge.store', $chatbot) }}" method="POST" class="mt-6 space-y-5">
            @csrf
            <div>
                <label for="question" class="label">Soalan</label>
                <input id="question" name="question" type="text" value="{{ old('question') }}" required
                       class="input" placeholder="Contoh: Apa itu diet seimbang?">
            </div>
            <div>
                <label for="answer" class="label">Jawapan</label>
                <textarea id="answer" name="answer" rows="8" required
                          class="input" placeholder="Tulis jawapan lengkap di sini...">{{ old('answer') }}</textarea>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="category" class="label">Kategori</label>
                    <input id="category" name="category" type="text" value="{{ old('category') }}"
                           class="input" placeholder="Cth: Pemakanan">
                </div>
                <div>
                    <label for="tags" class="label">Tag (pisah dengan koma)</label>
                    <input id="tags" name="tags" type="text" value="{{ old('tags') }}"
                           class="input" placeholder="Cth: diet,kurus,protein">
                </div>
            </div>
            <div class="pt-2">
                <button type="submit" class="btn btn-primary">Tambah Item</button>
            </div>
        </form>
    </section>
</div>
@endsection
