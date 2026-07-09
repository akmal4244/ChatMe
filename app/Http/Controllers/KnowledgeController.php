<?php

namespace App\Http\Controllers;

use App\Models\Chatbot;
use App\Models\KnowledgeItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class KnowledgeController extends Controller
{
    /**
     * List all knowledge items for a chatbot.
     */
    public function index(Request $request, Chatbot $chatbot)
    {
        Gate::authorize('view', $chatbot);

        $items = $chatbot->knowledgeItems()
            ->when($request->search, fn($q, $search) =>
                $q->where(fn($searchQuery) =>
                    $searchQuery->where('question', 'like', "%{$search}%")
                        ->orWhere('answer', 'like', "%{$search}%")
                )
            )
            ->when($request->category, fn($q, $cat) =>
                $q->where('category', $cat)
            )
            ->latest()
            ->paginate(15);

        $categories = $chatbot->knowledgeItems()
            ->select('category')
            ->distinct()
            ->whereNotNull('category')
            ->pluck('category');

        return view('knowledge.index', compact('chatbot', 'items', 'categories'));
    }

    /**
     * Show the create form for a knowledge item.
     */
    public function create(Chatbot $chatbot)
    {
        Gate::authorize('update', $chatbot);

        return view('knowledge.create', compact('chatbot'));
    }

    /**
     * Store a new knowledge item.
     */
    public function store(Request $request, Chatbot $chatbot)
    {
        Gate::authorize('update', $chatbot);

        $validated = $request->validate([
            'question' => ['required', 'string', 'max:2000'],
            'answer' => ['required', 'string', 'max:10000'],
            'category' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['chatbot_id'] = $chatbot->id;
        $validated['is_active'] = true;

        KnowledgeItem::create($validated);

        return redirect()->route('knowledge.index', $chatbot)
            ->with('success', 'Knowledge item added successfully.');
    }

    /**
     * Show a specific knowledge item.
     */
    public function show(Chatbot $chatbot, KnowledgeItem $item)
    {
        Gate::authorize('view', $chatbot);
        $this->ensureItemBelongsToChatbot($chatbot, $item);

        return view('knowledge.show', ['chatbot' => $chatbot, 'knowledge' => $item]);
    }

    /**
     * Show the edit form for a knowledge item.
     */
    public function edit(Chatbot $chatbot, KnowledgeItem $item)
    {
        Gate::authorize('update', $chatbot);
        $this->ensureItemBelongsToChatbot($chatbot, $item);

        return view('knowledge.edit', ['chatbot' => $chatbot, 'knowledge' => $item]);
    }

    /**
     * Update a knowledge item.
     */
    public function update(Request $request, Chatbot $chatbot, KnowledgeItem $item)
    {
        Gate::authorize('update', $chatbot);
        $this->ensureItemBelongsToChatbot($chatbot, $item);

        $validated = $request->validate([
            'question' => ['required', 'string', 'max:2000'],
            'answer' => ['required', 'string', 'max:10000'],
            'category' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ]);

        $item->update($validated);

        return redirect()->route('knowledge.index', $chatbot)
            ->with('success', 'Knowledge item updated successfully.');
    }

    /**
     * Delete a knowledge item.
     */
    public function destroy(Chatbot $chatbot, KnowledgeItem $item)
    {
        Gate::authorize('delete', $chatbot);
        $this->ensureItemBelongsToChatbot($chatbot, $item);

        $item->delete();

        return redirect()->route('knowledge.index', $chatbot)
            ->with('success', 'Knowledge item deleted successfully.');
    }

    /**
     * Bulk import knowledge items from CSV/JSON.
     */
    public function import(Request $request, Chatbot $chatbot)
    {
        Gate::authorize('update', $chatbot);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,json,txt', 'max:10240'],
        ]);

        $file = $request->file('file');
        $content = file_get_contents($file->getRealPath());
        $rows = array_map('str_getcsv', explode("
", trim($content)));
        $headers = array_shift($rows); // First row as headers

        $imported = 0;
        foreach ($rows as $row) {
            if (count($row) < 2) continue;
            KnowledgeItem::create([
                'chatbot_id' => $chatbot->id,
                'question' => $row[0],
                'answer' => $row[1],
                'category' => $row[2] ?? null,
                'tags' => $row[3] ?? null,
                'is_active' => true,
            ]);
            $imported++;
        }

        return redirect()->route('knowledge.index', $chatbot)
            ->with('success', "{$imported} knowledge items imported successfully.");
    }

    private function ensureItemBelongsToChatbot(Chatbot $chatbot, KnowledgeItem $item): void
    {
        abort_unless($item->chatbot_id === $chatbot->id, 404);
    }
}
