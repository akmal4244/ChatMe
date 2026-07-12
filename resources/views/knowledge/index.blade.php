@extends('layouts.app')
@section('page-title', 'Soal jawab')
@section('title', 'Soal jawab chatbot — ' . $chatbot->name)
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
    $failedForm = old('knowledge_form');
@endphp

<header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-neutral-950">Soal jawab chatbot</h1>
        <p class="text-neutral-600 text-sm mt-1">Chatbot: {{ $chatbot->name }}</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <button type="button" class="btn btn-secondary" data-dialog-open="import-dialog">Import soal jawab</button>
        <button type="button" class="btn btn-primary" data-dialog-open="add-dialog">+ Tambah soal jawab</button>
    </div>
</header>

<section class="card overflow-hidden" aria-labelledby="knowledge-list-heading">
    <h2 id="knowledge-list-heading" class="sr-only">Senarai soal jawab</h2>
    @if($items->isEmpty())
        <div class="px-5 py-14 sm:p-16 text-center">
            <p class="font-medium text-neutral-950 mb-2">Belum ada soal jawab.</p>
            <p class="text-sm text-neutral-600">Tambah soalan dan jawapan supaya chatbot anda boleh membantu pengunjung.</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="data-table min-w-[42rem]">
                <caption class="sr-only">Soal jawab untuk {{ $chatbot->name }}</caption>
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
                        <td>{{ $item->category ?? 'Tiada maklumat' }}</td>
                        <td>
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" class="table-action" data-edit-knowledge="{{ $item->getKey() }}" aria-label="Sunting soal jawab: {{ $item->question }}" title="Sunting soal jawab">
                                    <i class="ph ph-pencil-simple" aria-hidden="true"></i>
                                </button>
                                <form action="{{ route('knowledge.destroy', [$chatbot, $item]) }}" method="POST" class="inline-flex"
                                      data-confirm-title="Padam soal jawab?"
                                      data-confirm-description="Padam soal jawab ini? Tindakan ini tidak boleh dibatalkan."
                                      data-confirm-text="Padam soal jawab"
                                      data-confirm-type="danger">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="table-action table-action-danger" aria-label="Padam soal jawab: {{ $item->question }}" title="Padam soal jawab">
                                        <i class="ph ph-trash" aria-hidden="true"></i>
                                    </button>
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
            <h2 id="add-dialog-title" class="text-lg font-semibold text-neutral-950">Tambah soal jawab</h2>
            <button type="button" class="btn btn-ghost btn-sm" aria-label="Tutup dialog tambah item" data-dialog-close>&times;</button>
        </div>
        <form action="{{ route('knowledge.store', $chatbot) }}" method="POST" class="space-y-4">
            @csrf
            <input type="hidden" name="knowledge_form" value="add">
            <div>
                <label for="add-question" class="label">Soalan</label>
                <input id="add-question" type="text" name="question" value="{{ $failedForm === 'add' ? old('question') : '' }}" required class="input" placeholder="Contoh: Apakah waktu operasi?" @if($failedForm === 'add' && $errors->has('question')) aria-invalid="true" aria-describedby="add-question-error" @endif>
                @if($failedForm === 'add' && $errors->has('question')) <p id="add-question-error" class="field-error" role="alert">{{ $errors->first('question') }}</p> @endif
            </div>
            <div>
                <label for="add-answer" class="label">Jawapan</label>
                <textarea id="add-answer" name="answer" required rows="4" class="input" placeholder="Tulis jawapan lengkap..." @if($failedForm === 'add' && $errors->has('answer')) aria-invalid="true" aria-describedby="add-answer-error" @endif>{{ $failedForm === 'add' ? old('answer') : '' }}</textarea>
                @if($failedForm === 'add' && $errors->has('answer')) <p id="add-answer-error" class="field-error" role="alert">{{ $errors->first('answer') }}</p> @endif
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label for="add-category" class="label">Kategori</label>
                    <input id="add-category" type="text" name="category" value="{{ $failedForm === 'add' ? old('category') : '' }}" class="input" placeholder="Contoh: Operasi" @if($failedForm === 'add' && $errors->has('category')) aria-invalid="true" aria-describedby="add-category-error" @endif>
                    @if($failedForm === 'add' && $errors->has('category')) <p id="add-category-error" class="field-error" role="alert">{{ $errors->first('category') }}</p> @endif
                </div>
                <div>
                    <label for="add-tags" class="label">Tag (pisah koma)</label>
                    <input id="add-tags" type="text" name="tags" value="{{ $failedForm === 'add' ? old('tags') : '' }}" class="input" placeholder="masa, pejabat" @if($failedForm === 'add' && $errors->has('tags')) aria-invalid="true" aria-describedby="add-tags-error" @endif>
                    @if($failedForm === 'add' && $errors->has('tags')) <p id="add-tags-error" class="field-error" role="alert">{{ $errors->first('tags') }}</p> @endif
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-full justify-center">Tambah soal jawab</button>
        </form>
    </div>
</dialog>

<dialog id="edit-dialog" class="w-[calc(100%-2rem)] max-w-lg rounded-xl border border-neutral-200 bg-white p-0 text-neutral-900 shadow-xl backdrop:bg-neutral-950/50" aria-labelledby="edit-dialog-title">
    <div class="p-5 sm:p-6">
        <div class="flex justify-between items-center gap-4 mb-5">
            <h2 id="edit-dialog-title" class="text-lg font-semibold text-neutral-950">Sunting soal jawab</h2>
            <button type="button" class="btn btn-ghost btn-sm" aria-label="Tutup dialog sunting item" data-dialog-close>&times;</button>
        </div>
        <form id="edit-form" method="POST" class="space-y-4">
            @csrf @method('PUT')
            <input type="hidden" name="knowledge_form" value="edit">
            <input id="edit-item-id" type="hidden" name="edit_item" value="{{ $failedForm === 'edit' ? old('edit_item') : '' }}">
            <div>
                <label for="edit-question" class="label">Soalan</label>
                <input id="edit-question" type="text" name="question" required class="input" @if($failedForm === 'edit' && $errors->has('question')) aria-invalid="true" aria-describedby="edit-question-error" @endif>
                @if($failedForm === 'edit' && $errors->has('question')) <p id="edit-question-error" class="field-error" role="alert">{{ $errors->first('question') }}</p> @endif
            </div>
            <div>
                <label for="edit-answer" class="label">Jawapan</label>
                <textarea id="edit-answer" name="answer" required rows="4" class="input" @if($failedForm === 'edit' && $errors->has('answer')) aria-invalid="true" aria-describedby="edit-answer-error" @endif></textarea>
                @if($failedForm === 'edit' && $errors->has('answer')) <p id="edit-answer-error" class="field-error" role="alert">{{ $errors->first('answer') }}</p> @endif
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label for="edit-category" class="label">Kategori</label>
                    <input id="edit-category" type="text" name="category" class="input" @if($failedForm === 'edit' && $errors->has('category')) aria-invalid="true" aria-describedby="edit-category-error" @endif>
                    @if($failedForm === 'edit' && $errors->has('category')) <p id="edit-category-error" class="field-error" role="alert">{{ $errors->first('category') }}</p> @endif
                </div>
                <div>
                    <label for="edit-tags" class="label">Tag (pisah koma)</label>
                    <input id="edit-tags" type="text" name="tags" class="input" @if($failedForm === 'edit' && $errors->has('tags')) aria-invalid="true" aria-describedby="edit-tags-error" @endif>
                    @if($failedForm === 'edit' && $errors->has('tags')) <p id="edit-tags-error" class="field-error" role="alert">{{ $errors->first('tags') }}</p> @endif
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-full justify-center">Simpan perubahan</button>
        </form>
    </div>
</dialog>

<dialog id="import-dialog" class="w-[calc(100%-2rem)] max-w-lg rounded-xl border border-neutral-200 bg-white p-0 text-neutral-900 shadow-xl backdrop:bg-neutral-950/50" aria-labelledby="import-dialog-title">
    <div class="p-5 sm:p-6">
        <div class="flex justify-between items-center gap-4 mb-5">
            <h2 id="import-dialog-title" class="text-lg font-semibold text-neutral-950">Import soal jawab (JSON)</h2>
            <button type="button" class="btn btn-ghost btn-sm" aria-label="Tutup dialog import" data-dialog-close>&times;</button>
        </div>
        <form action="{{ route('knowledge.import', $chatbot) }}" method="POST" class="space-y-4">
            @csrf
            <input type="hidden" name="knowledge_form" value="import">
            <div>
                <label for="json-data" class="label">Data JSON</label>
                <p id="json-data-help" class="text-sm text-neutral-600 mb-2">Tampal senarai JSON yang mempunyai ruangan "question" dan "answer".</p>
                <textarea id="json-data" name="json_data" rows="8" required class="input font-mono" aria-describedby="json-data-help{{ $failedForm === 'import' && $errors->has('json_data') ? ' json-data-error' : '' }}" @if($failedForm === 'import' && $errors->has('json_data')) aria-invalid="true" @endif placeholder='[{"question": "...", "answer": "...", "category": "...", "tags": "..."}]'>{{ $failedForm === 'import' ? old('json_data') : '' }}</textarea>
                @if($failedForm === 'import' && $errors->has('json_data')) <p id="json-data-error" class="field-error" role="alert">{{ $errors->first('json_data') }}</p> @endif
            </div>
            <button type="submit" class="btn btn-primary w-full justify-center">Import</button>
        </form>
    </div>
</dialog>

<script nonce="{{ Vite::cspNonce() }}">
document.addEventListener('DOMContentLoaded', () => {
    const editableItems = {{ Illuminate\Support\Js::from($editableItems) }};
    const updateRouteTemplate = {{ Illuminate\Support\Js::from(route('knowledge.update', [$chatbot, '__ITEM__'])) }};
    const failedForm = {{ Illuminate\Support\Js::from($failedForm) }};
    const failedEdit = {{ Illuminate\Support\Js::from([
        'id' => old('edit_item'),
        'question' => old('question'),
        'answer' => old('answer'),
        'category' => old('category'),
        'tags' => old('tags'),
    ]) }};

    const openEdit = (item) => {
        if (!item?.id) return;

        document.getElementById('edit-form').action = updateRouteTemplate.replace('__ITEM__', encodeURIComponent(item.id));
        document.getElementById('edit-item-id').value = item.id;
        document.getElementById('edit-question').value = item.question ?? '';
        document.getElementById('edit-answer').value = item.answer ?? '';
        document.getElementById('edit-category').value = item.category ?? '';
        document.getElementById('edit-tags').value = item.tags ?? '';
        document.getElementById('edit-dialog').showModal();
    };

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

            openEdit(item);
        });
    });

    if (failedForm === 'add') document.getElementById('add-dialog').showModal();
    if (failedForm === 'import') document.getElementById('import-dialog').showModal();
    if (failedForm === 'edit') openEdit(failedEdit);
});
</script>
@endsection
