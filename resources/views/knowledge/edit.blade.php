@extends('layouts.app')
@section('page-title', 'Sunting soal jawab')
@section('title', 'Sunting soal jawab — ' . $chatbot->name)
@section('content')
<div class="max-w-2xl">
    <a href="{{ route('knowledge.index', $chatbot) }}" class="btn btn-ghost mb-6">&larr; Kembali</a>
    <section class="card p-5 sm:p-6" aria-labelledby="knowledge-edit-heading">
        <h1 id="knowledge-edit-heading" class="text-xl font-semibold text-neutral-950">Sunting soal jawab</h1>
        <p class="text-sm text-neutral-600 mt-1">Kemas kini soal jawab ini. Perubahan akan digunakan dalam jawapan chatbot.</p>
        <form action="{{ route('knowledge.update', [$chatbot, $knowledge]) }}" method="POST" class="mt-6 space-y-5">
            @csrf
            @method('PUT')
            <div>
                <label for="question" class="label">Soalan</label>
                <input id="question" name="question" type="text" value="{{ old('question', $knowledge->question) }}" required
                       class="input" @error('question') aria-invalid="true" aria-describedby="question-error" @enderror>
                @error('question') <p id="question-error" class="field-error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="answer" class="label">Jawapan</label>
                <textarea id="answer" name="answer" rows="8" required
                          class="input" @error('answer') aria-invalid="true" aria-describedby="answer-error" @enderror>{{ old('answer', $knowledge->answer) }}</textarea>
                @error('answer') <p id="answer-error" class="field-error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="category" class="label">Kategori</label>
                    <input id="category" name="category" type="text" value="{{ old('category', $knowledge->category) }}"
                           class="input" @error('category') aria-invalid="true" aria-describedby="category-error" @enderror>
                    @error('category') <p id="category-error" class="field-error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="tags" class="label">Tag (pisahkan dengan koma)</label>
                    <input id="tags" name="tags" type="text" value="{{ old('tags', $knowledge->tags) }}"
                           class="input" @error('tags') aria-invalid="true" aria-describedby="tags-error" @enderror>
                    @error('tags') <p id="tags-error" class="field-error" role="alert">{{ $message }}</p> @enderror
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
