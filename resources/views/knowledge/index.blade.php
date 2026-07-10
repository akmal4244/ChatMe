@extends('layouts.app')
@section('page-title', 'Pengetahuan')
@section('title', 'Pangkalan Pengetahuan — ' . $chatbot->name)
@section('content')
@php
    $editableItems = $items->getCollection()->mapWithKeys(fn ($item) => [
        (string) $item->getKey() => [
            'id' => $item->getKey(),
            'question' => $item->question,
            'answer' => $item->answer,
            'category' => $item->category,
            'tags' => $item->tags,
        ],
    ])->all();
@endphp

<header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-neutral-950">Pangkalan Pengetahuan</h1>
        <p class="text-neutral-600 text-sm mt-1">Chatbot: {{ $chatbot->name }}</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <button type="button" class="btn btn-secondary" data-dialog-open="import-dialog">Import JSON</button>
        <button type="button" class="btn btn-primary" data-dialog-open="add-dialog">+ Tambah Item</button>
    </div>
</header>

<section class="card overflow-hidden" aria-labelledby="knowledge-list-heading">
    <h2 id="knowledge-list-heading" class="sr-only">Senarai item pengetahuan</h2>
    @if($items->isEmpty())
        <div class="px-5 py-14 sm:p-16 text-center">
            <p class="font-medium text-neutral-950 mb-2">Tiada item pengetahuan lagi.</p>
            <p class="text-sm text-neutral-600">Tambah pasangan Soal-Jawab supaya chatbot anda boleh menjawab soalan secara automatik.</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="data-table min-w-[42rem]">
                <caption class="sr-only">Item pengetahuan untuk {{ $chatbot->name }}</caption>
                <thead>
                    <tr>
                        <th scope="col">Soalan dan jawapan</th>
                        <th scope="col">Kategori</th>
                        <th scope="col">Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($items as $item)
                    <tr>
                        <th scope="row" class="font-normal">
                            <span class="block text-sm font-semibold text-neutral-950">{{ $item->question }}</span>
                            <span class="block text-sm text-neutral-600 mt-1">{{ Str::limit($item->answer, 150) }}</span>
                        </th>
                        <td>{{ $item->category ?? '-' }}</td>
                        <td>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                                <button type="button" class="text-brand-700 font-medium hover:underline" data-edit-knowledge="{{ $item->getKey() }}">Sunting</button>
                                <form action="{{ route('knowledge.destroy', [$chatbot, $item]) }}" method="POST" onsubmit="return confirm('Padam item pengetahuan ini?')" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-700 font-medium hover:underline">Padam</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($items->hasPages())
            <div class="p-4 border-t border-neutral-200">{{ $items->links() }}</div>
        @endif
    @endif
</section>

<dialog id="add-dialog" class="w-[calc(100%-2rem)] max-w-lg rounded-xl border border-neutral-200 bg-white p-0 text-neutral-900 shadow-xl backdrop:bg-neutral-950/50" aria-labelledby="add-dialog-title">
    <div class="p-5 sm:p-6">
        <div class="flex justify-between items-center gap-4 mb-5">
            <h2 id="add-dialog-title" class="text-lg font-semibold text-neutral-950">Tambah Item Pengetahuan</h2>
            <button type="button" class="btn btn-ghost btn-sm" aria-label="Tutup dialog tambah item" data-dialog-close>&times;</button>
        </div>
        <form action="{{ route('knowledge.store', $chatbot) }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label for="add-question" class="label">Soalan</label>
                <input id="add-question" type="text" name="question" value="{{ old('question') }}" required class="input" placeholder="Contoh: Apakah waktu operasi?">
            </div>
            <div>
                <label for="add-answer" class="label">Jawapan</label>
                <textarea id="add-answer" name="answer" required rows="4" class="input" placeholder="Tulis jawapan lengkap...">{{ old('answer') }}</textarea>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label for="add-category" class="label">Kategori</label>
                    <input id="add-category" type="text" name="category" value="{{ old('category') }}" class="input" placeholder="Contoh: Operasi">
                </div>
                <div>
                    <label for="add-tags" class="label">Tag (pisah koma)</label>
                    <input id="add-tags" type="text" name="tags" value="{{ old('tags') }}" class="input" placeholder="masa, pejabat">
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-full justify-center">Tambah Item</button>
        </form>
    </div>
</dialog>

<dialog id="edit-dialog" class="w-[calc(100%-2rem)] max-w-lg rounded-xl border border-neutral-200 bg-white p-0 text-neutral-900 shadow-xl backdrop:bg-neutral-950/50" aria-labelledby="edit-dialog-title">
    <div class="p-5 sm:p-6">
        <div class="flex justify-between items-center gap-4 mb-5">
            <h2 id="edit-dialog-title" class="text-lg font-semibold text-neutral-950">Sunting Item Pengetahuan</h2>
            <button type="button" class="btn btn-ghost btn-sm" aria-label="Tutup dialog sunting item" data-dialog-close>&times;</button>
        </div>
        <form id="edit-form" method="POST" class="space-y-4">
            @csrf @method('PUT')
            <div>
                <label for="edit-question" class="label">Soalan</label>
                <input id="edit-question" type="text" name="question" required class="input">
            </div>
            <div>
                <label for="edit-answer" class="label">Jawapan</label>
                <textarea id="edit-answer" name="answer" required rows="4" class="input"></textarea>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label for="edit-category" class="label">Kategori</label>
                    <input id="edit-category" type="text" name="category" class="input">
                </div>
                <div>
                    <label for="edit-tags" class="label">Tag (pisah koma)</label>
                    <input id="edit-tags" type="text" name="tags" class="input">
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-full justify-center">Simpan Perubahan</button>
        </form>
    </div>
</dialog>

<dialog id="import-dialog" class="w-[calc(100%-2rem)] max-w-lg rounded-xl border border-neutral-200 bg-white p-0 text-neutral-900 shadow-xl backdrop:bg-neutral-950/50" aria-labelledby="import-dialog-title">
    <div class="p-5 sm:p-6">
        <div class="flex justify-between items-center gap-4 mb-5">
            <h2 id="import-dialog-title" class="text-lg font-semibold text-neutral-950">Import Pengetahuan (JSON)</h2>
            <button type="button" class="btn btn-ghost btn-sm" aria-label="Tutup dialog import" data-dialog-close>&times;</button>
        </div>
        <form action="{{ route('knowledge.import', $chatbot) }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label for="json-data" class="label">Data JSON</label>
                <p id="json-data-help" class="text-sm text-neutral-600 mb-2">Tampal array JSON dengan medan "question" dan "answer".</p>
                <textarea id="json-data" name="json_data" rows="8" required class="input font-mono" aria-describedby="json-data-help" placeholder='[{"question": "...", "answer": "...", "category": "...", "tags": "..."}]'>{{ old('json_data') }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary w-full justify-center">Import</button>
        </form>
    </div>
</dialog>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const editableItems = {{ Illuminate\Support\Js::from($editableItems) }};
    const updateRouteTemplate = {{ Illuminate\Support\Js::from(route('knowledge.update', [$chatbot, '__ITEM__'])) }};

    document.querySelectorAll('[data-dialog-open]').forEach((button) => {
        button.addEventListener('click', () => {
            const dialog = document.getElementById(button.dataset.dialogOpen);
            if (dialog) dialog.showModal();
        });
    });

    document.querySelectorAll('[data-dialog-close]').forEach((button) => {
        button.addEventListener('click', () => button.closest('dialog')?.close());
    });

    document.querySelectorAll('[data-edit-knowledge]').forEach((button) => {
        button.addEventListener('click', () => {
            const item = editableItems[button.dataset.editKnowledge];
            if (!item) return;

            document.getElementById('edit-form').action = updateRouteTemplate.replace('__ITEM__', encodeURIComponent(item.id));
            document.getElementById('edit-question').value = item.question ?? '';
            document.getElementById('edit-answer').value = item.answer ?? '';
            document.getElementById('edit-category').value = item.category ?? '';
            document.getElementById('edit-tags').value = item.tags ?? '';
            document.getElementById('edit-dialog').showModal();
        });
    });
});
</script>
@endsection
