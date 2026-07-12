<?php

namespace App\Http\Controllers;

use App\Models\Chatbot;
use App\Models\KnowledgeItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use JsonException;

class KnowledgeController extends Controller
{
    /**
     * List all knowledge items for a chatbot.
     */
    public function index(Request $request, Chatbot $chatbot)
    {
        Gate::authorize('view', $chatbot);

        $items = $chatbot->knowledgeItems()
            ->when($request->search, fn ($q, $search) => $q->where(fn ($searchQuery) => $searchQuery->where('question', 'like', "%{$search}%")
                ->orWhere('answer', 'like', "%{$search}%")
            )
            )
            ->when($request->category, fn ($q, $cat) => $q->where('category', $cat)
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
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string', 'max:10000'],
            'category' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($chatbot, $validated): void {
            $lockedChatbot = Chatbot::query()
                ->whereKey($chatbot->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedChatbot->user->canAddKnowledgeItems($lockedChatbot)) {
                throw ValidationException::withMessages([
                    'question' => 'Anda telah mencapai had soal jawab bagi pelan semasa.',
                ]);
            }

            $validated['chatbot_id'] = $lockedChatbot->id;
            $validated['is_active'] = true;

            KnowledgeItem::create($validated);
        });

        return redirect()->route('knowledge.index', $chatbot)
            ->with('success', 'Soal jawab berjaya ditambah.');
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
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string', 'max:10000'],
            'category' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $item->update($validated);

        return redirect()->route('knowledge.index', $chatbot)
            ->with('success', 'Soal jawab berjaya dikemas kini.');
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
            ->with('success', 'Soal jawab berjaya dipadam.');
    }

    /**
     * Bulk import knowledge items from JSON.
     */
    public function import(Request $request, Chatbot $chatbot)
    {
        Gate::authorize('update', $chatbot);

        $validated = $request->validate([
            'json_data' => ['required', 'string', 'max:100000'],
        ]);

        try {
            $decoded = json_decode($validated['json_data'], false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages([
                'json_data' => 'Data JSON tidak sah.',
            ]);
        }

        if (! is_array($decoded) || ! array_is_list($decoded) || count($decoded) < 1 || count($decoded) > 1000) {
            throw ValidationException::withMessages([
                'json_data' => 'Data JSON mestilah senarai yang mengandungi 1 hingga 1,000 objek.',
            ]);
        }

        $rows = [];
        foreach ($decoded as $item) {
            if (! $item instanceof \stdClass) {
                throw ValidationException::withMessages([
                    'json_data' => 'Setiap item dalam senarai JSON mestilah sebuah objek.',
                ]);
            }

            $rows[] = (array) $item;
        }

        $validator = Validator::make($rows, [
            '*.question' => ['required', 'string', 'max:255'],
            '*.answer' => ['required', 'string', 'max:10000'],
            '*.category' => ['nullable', 'string', 'max:255'],
            '*.tags' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages([
                'json_data' => 'Data JSON mengandungi soal jawab yang tidak sah.',
            ]);
        }

        DB::transaction(function () use ($chatbot, $rows): void {
            $lockedChatbot = Chatbot::query()
                ->whereKey($chatbot->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedChatbot->user->canAddKnowledgeItems($lockedChatbot, count($rows))) {
                throw ValidationException::withMessages([
                    'json_data' => 'Import ini melebihi had soal jawab pelan anda.',
                ]);
            }

            foreach ($rows as $row) {
                $lockedChatbot->knowledgeItems()->create([
                    'question' => $row['question'],
                    'answer' => $row['answer'],
                    'category' => $row['category'] ?? null,
                    'tags' => $row['tags'] ?? null,
                    'is_active' => true,
                ]);
            }
        });

        $imported = count($rows);

        return redirect()->route('knowledge.index', $chatbot)
            ->with('success', "{$imported} soal jawab berjaya diimport.");
    }

    private function ensureItemBelongsToChatbot(Chatbot $chatbot, KnowledgeItem $item): void
    {
        abort_unless($item->chatbot_id === $chatbot->id, 404);
    }
}
