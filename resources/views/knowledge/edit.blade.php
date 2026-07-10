@extends('layouts.app')
@section('page-title', 'Sunting Pengetahuan')
@section('title', 'Sunting Pengetahuan — ' . $chatbot->name)
@section('content')
<div class="max-w-2xl">
    <a href="{{ route('knowledge.index', $chatbot) }}" class="btn btn-ghost mb-6">&larr; Kembali</a>
    <section class="card p-5 sm:p-6" aria-labelledby="knowledge-edit-heading">
        <h1 id="knowledge-edit-heading" class="text-xl font-semibold text-neutral-950">Sunting Item Pengetahuan</h1>
        <p class="text-sm text-neutral-600 mt-1">Kemaskini item ini. Perubahan akan mempengaruhi respons chatbot.</p>
        <form action="{{ route('knowledge.update', [$chatbot, $knowledge]) }}" method="POST" class="mt-6 space-y-5">
            @csrf
            @method('PUT')
            <div>
                <label for="question" class="label">Soalan</label>
                <input id="question" name="question" type="text" value="{{ old('question', $knowledge->question) }}" required
                       class="input">
            </div>
            <div>
                <label for="answer" class="label">Jawapan</label>
                <textarea id="answer" name="answer" rows="8" required
                          class="input">{{ old('answer', $knowledge->answer) }}</textarea>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="category" class="label">Kategori</label>
                    <input id="category" name="category" type="text" value="{{ old('category', $knowledge->category) }}"
                           class="input">
                </div>
                <div>
                    <label for="tags" class="label">Tag (pisah dengan koma)</label>
                    <input id="tags" name="tags" type="text" value="{{ old('tags', $knowledge->tags) }}"
                           class="input">
                </div>
            </div>
            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="btn btn-primary">Simpan</button>
                <a href="{{ route('knowledge.index', $chatbot) }}" class="btn btn-ghost">Batal</a>
            </div>
        </form>
    </section>
</div>
@endsection
