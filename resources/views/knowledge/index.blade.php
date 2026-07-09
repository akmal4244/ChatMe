@extends('layouts.app')
@section('page-title', 'Pengetahuan')
@section('title', 'Pangkalan Pengetahuan — ' . $chatbot->name)
@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-white">Pangkalan Pengetahuan</h1>
        <p class="text-white/25 text-sm mt-1">Chatbot: {{ $chatbot->name }}</p>
    </div>
    <div class="flex gap-2">
        <button onclick="document.getElementById('importModal').classList.remove('hidden')" class="border border-white/[0.06] text-white/80 px-4 py-2 rounded-lg text-sm font-medium hover:bg-white/[0.03] transition">Import JSON</button>
        <button onclick="document.getElementById('addModal').classList.remove('hidden')" class="bg-white text-[#050505] px-4 py-2 rounded-lg text-sm font-semibold hover:bg-white/90 transition shadow-sm">+ Tambah Item</button>
    </div>
</div>

<div class="bg-white/[0.03] rounded-lg border border-white/[0.06] overflow-hidden">
    @if($items->isEmpty())
        <div class="p-16 text-center">
            <p class="text-white/25 mb-2">Tiada item pengetahuan lagi.</p>
            <p class="text-sm text-white/25">Tambah pasangan Soal-Jawab supaya chatbot anda boleh menjawab soalan secara automatik.</p>
        </div>
    @else
        <div class="p-4 border-b bg-white/[0.03] text-sm text-white/25 flex">
            <div class="flex-1 font-medium">Soalan</div>
            <div class="w-32 font-medium">Kategori</div>
            <div class="w-24 font-medium">Tindakan</div>
        </div>
        @foreach($items as $item)
        <div class="p-4 border-b border-neutral-50 hover:bg-white/[0.03] flex items-start gap-4 transition-colors">
            <div class="flex-1">
                <p class="text-sm font-semibold text-white">{{ $item->question }}</p>
                <p class="text-sm text-white/25 mt-1">{{ Str::limit($item->answer, 150) }}</p>
            </div>
            <div class="w-32 text-sm text-white/25">{{ $item->category ?? '-' }}</div>
            <div class="w-24 flex gap-2 text-sm">
                <button onclick="editItem({{ $item->id }}, '{{ addslashes($item->question) }}', '{{ addslashes($item->answer) }}', '{{ $item->category }}', '{{ $item->tags }}')" class="text-white font-medium hover:underline">Sunting</button>
                <form action="{{ route('knowledge.destroy', [$chatbot, $item]) }}" method="POST" onsubmit="return confirm('Padam?')" class="inline">
                    @csrf @method('DELETE')
                    <button class="text-red-500 font-medium hover:underline">Padam</button>
                </form>
            </div>
        </div>
        @endforeach
        <div class="p-4">{{ $items->links() }}</div>
    @endif
</div>

<!-- Add Modal -->
<div id="addModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
    <div class="bg-white/[0.03] rounded-lg p-6 max-w-lg w-full mx-4 shadow-xl">
        <div class="flex justify-between items-center mb-4"><h2 class="text-lg font-semibold">Tambah Item Pengetahuan</h2><button onclick="this.closest('#addModal').classList.add('hidden')" class="text-white/25 text-xl">&times;</button></div>
        <form action="{{ route('knowledge.store', $chatbot) }}" method="POST" class="space-y-4">
            @csrf
            <input type="text" name="question" required class="w-full border border-white/[0.06] rounded-lg px-4 py-3 text-sm" placeholder="Soalan (contoh: Apakah waktu operasi?)">
            <textarea name="answer" required rows="4" class="w-full border border-white/[0.06] rounded-lg px-4 py-3 text-sm" placeholder="Jawapan..."></textarea>
            <div class="grid grid-cols-2 gap-3">
                <input type="text" name="category" class="border border-white/[0.06] rounded-lg px-4 py-3 text-sm" placeholder="Kategori">
                <input type="text" name="tags" class="border border-white/[0.06] rounded-lg px-4 py-3 text-sm" placeholder="Tag (pisah koma)">
            </div>
            <button type="submit" class="w-full bg-white text-[#050505] py-3 rounded-lg font-semibold text-sm hover:bg-white/90 transition">Tambah Item</button>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
    <div class="bg-white/[0.03] rounded-lg p-6 max-w-lg w-full mx-4 shadow-xl">
        <div class="flex justify-between items-center mb-4"><h2 class="text-lg font-semibold">Sunting Item</h2><button onclick="this.closest('#editModal').classList.add('hidden')" class="text-white/25 text-xl">&times;</button></div>
        <form id="editForm" method="POST" class="space-y-4">
            @csrf @method('PUT')
            <input type="text" name="question" id="editQuestion" required class="w-full border border-white/[0.06] rounded-lg px-4 py-3 text-sm">
            <textarea name="answer" id="editAnswer" required rows="4" class="w-full border border-white/[0.06] rounded-lg px-4 py-3 text-sm"></textarea>
            <div class="grid grid-cols-2 gap-3">
                <input type="text" name="category" id="editCategory" class="border border-white/[0.06] rounded-lg px-4 py-3 text-sm" placeholder="Kategori">
                <input type="text" name="tags" id="editTags" class="border border-white/[0.06] rounded-lg px-4 py-3 text-sm" placeholder="Tag">
            </div>
            <button type="submit" class="w-full bg-white text-[#050505] py-3 rounded-lg font-semibold text-sm hover:bg-white/90 transition">Simpan Perubahan</button>
        </form>
    </div>
</div>

<script>
function editItem(id, question, answer, category, tags) {
    document.getElementById('editForm').action = '{{ route("knowledge.update", [$chatbot, "__ID__"]) }}'.replace('__ID__', id);
    document.getElementById('editQuestion').value = question;
    document.getElementById('editAnswer').value = answer;
    document.getElementById('editCategory').value = category || '';
    document.getElementById('editTags').value = tags || '';
    document.getElementById('editModal').classList.remove('hidden');
}
</script>

<div id="importModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
    <div class="bg-white/[0.03] rounded-lg p-6 max-w-lg w-full mx-4 shadow-xl">
        <div class="flex justify-between items-center mb-4"><h2 class="text-lg font-semibold">Import Pengetahuan (JSON)</h2><button onclick="this.closest('#importModal').classList.add('hidden')" class="text-white/25 text-xl">&times;</button></div>
        <form action="{{ route('knowledge.import', $chatbot) }}" method="POST" class="space-y-4">
            @csrf
            <p class="text-sm text-white/25">Tampal array JSON dengan field "question" dan "answer":</p>
            <textarea name="json_data" rows="8" class="w-full border border-white/[0.06] rounded-lg px-4 py-3 font-mono text-sm" placeholder='[{"question": "...", "answer": "...", "category": "...", "tags": "..."}]'></textarea>
            <button type="submit" class="w-full bg-white text-[#050505] py-3 rounded-lg font-semibold text-sm hover:bg-white/90 transition">Import</button>
        </form>
    </div>
</div>
@endsection
