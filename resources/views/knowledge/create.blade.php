@extends('layouts.app')
@section('page-title', 'Tambah soal jawab')
@section('title', 'Tambah soal jawab — ' . $chatbot->name)
@section('content')
<div class="max-w-2xl">
    <a href="{{ route('knowledge.index', $chatbot) }}" class="btn btn-ghost mb-6">&larr; Kembali</a>
    <section class="card p-5 sm:p-6" aria-labelledby="knowledge-create-heading">
        <h1 id="knowledge-create-heading" class="text-xl font-semibold text-neutral-950">Tambah soal jawab</h1>
        <p class="text-sm text-neutral-600 mt-1">Tambah soalan dan jawapan supaya chatbot anda boleh membantu pengunjung.</p>
        <form action="{{ route('knowledge.store', $chatbot) }}" method="POST" class="mt-6 space-y-5">
            @csrf
            <div>
                <label for="question" class="label">Soalan</label>
                <input id="question" name="question" type="text" value="{{ old('question') }}" required
                       class="input" placeholder="Contoh: Apakah waktu operasi?" @error('question') aria-invalid="true" aria-describedby="question-error" @enderror>
                @error('question') <p id="question-error" class="field-error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="answer" class="label">Jawapan</label>
                <textarea id="answer" name="answer" rows="8" required
                          class="input" placeholder="Tulis jawapan lengkap di sini..." @error('answer') aria-invalid="true" aria-describedby="answer-error" @enderror>{{ old('answer') }}</textarea>
                @error('answer') <p id="answer-error" class="field-error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="category" class="label">Kategori</label>
                    <input id="category" name="category" type="text" value="{{ old('category') }}"
                           class="input" placeholder="Contoh: Operasi" @error('category') aria-invalid="true" aria-describedby="category-error" @enderror>
                    @error('category') <p id="category-error" class="field-error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="tags" class="label">Tag (pisahkan dengan koma)</label>
                    <input id="tags" name="tags" type="text" value="{{ old('tags') }}"
                           class="input" placeholder="Contoh: waktu, pejabat" @error('tags') aria-invalid="true" aria-describedby="tags-error" @enderror>
                    @error('tags') <p id="tags-error" class="field-error" role="alert">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="pt-2">
                <button type="submit" class="btn btn-primary">Tambah soal jawab</button>
            </div>
        </form>
    </section>
</div>
@endsection
