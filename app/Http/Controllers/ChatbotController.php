<?php

namespace App\Http\Controllers;

use App\Models\Chatbot;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChatbotController extends Controller
{
    /**
     * List all chatbots for the authenticated user.
     */
    public function index(Request $request)
    {
        $chatbots = $request->user()->chatbots()->withCount('knowledgeItems')->latest()->paginate(10);

        return view('chatbots.index', compact('chatbots'));
    }

    /**
     * Show the create chatbot form.
     */
    public function create()
    {
        return view('chatbots.create');
    }

    /**
     * Store a new chatbot.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'avatar_url' => ['nullable', 'url', 'max:2048'],
            'primary_color' => ['nullable', 'string', 'max:7'],
            'secondary_color' => ['nullable', 'string', 'max:7'],
            'position' => ['nullable', 'string', 'in:bottom-right,bottom-left'],
            'welcome_message' => ['nullable', 'string', 'max:1000'],
            'placeholder_text' => ['nullable', 'string', 'max:255'],
            'bot_name' => ['nullable', 'string', 'max:255'],
            'system_prompt' => ['nullable', 'string', 'max:5000'],
        ]);

        if (!$request->user()->canCreateChatbot()) {
            throw ValidationException::withMessages([
                'name' => 'Your current plan chatbot limit has been reached.',
            ]);
        }

        $validated['slug'] = Str::slug($request->name) . '-' . Str::random(6);
        $validated['api_key'] = Str::random(40);
        $validated['is_active'] = true;

        $chatbot = DB::transaction(function () use ($request, $validated): ?Chatbot {
            $owner = User::query()
                ->lockForUpdate()
                ->findOrFail($request->user()->id);

            if (! $owner->canCreateChatbot()) {
                return null;
            }

            return $owner->chatbots()->create($validated);
        });

        if (! $chatbot) {
            throw ValidationException::withMessages([
                'name' => 'Your current plan chatbot limit has been reached.',
            ]);
        }

        return redirect()->route('chatbots.index')
            ->with('success', 'Chatbot created successfully.');
    }

    /**
     * Show a single chatbot.
     */
    public function show(Chatbot $chatbot)
    {
        Gate::authorize('view', $chatbot);

        return redirect()->route('chatbots.edit', $chatbot);
    }

    /**
     * Show the edit form for a chatbot.
     */
    public function edit(Chatbot $chatbot)
    {
        Gate::authorize('view', $chatbot);

        return view('chatbots.edit', compact('chatbot'));
    }

    /**
     * Update a chatbot.
     */
    public function update(Request $request, Chatbot $chatbot)
    {
        Gate::authorize('update', $chatbot);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'avatar_url' => ['nullable', 'url', 'max:2048'],
            'primary_color' => ['nullable', 'string', 'max:7'],
            'secondary_color' => ['nullable', 'string', 'max:7'],
            'position' => ['nullable', 'string', 'in:bottom-right,bottom-left'],
            'welcome_message' => ['nullable', 'string', 'max:1000'],
            'placeholder_text' => ['nullable', 'string', 'max:255'],
            'bot_name' => ['nullable', 'string', 'max:255'],
            'system_prompt' => ['nullable', 'string', 'max:5000'],
            'domain_whitelist' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ]);

        $chatbot->update($validated);

        return redirect()->route('chatbots.index')
            ->with('success', 'Chatbot updated successfully.');
    }

    /**
     * Delete a chatbot.
     */
    public function destroy(Chatbot $chatbot)
    {
        Gate::authorize('delete', $chatbot);

        $chatbot->delete();

        return redirect()->route('chatbots.index')
            ->with('success', 'Chatbot deleted successfully.');
    }

    /**
     * Show the customization form for a specific chatbot.
     */
    public function customize(Chatbot $chatbot)
    {
        Gate::authorize('view', $chatbot);

        return view('chatbots.customize', compact('chatbot'));
    }

    /**
     * Update customization settings for a chatbot.
     */
    public function updateCustomization(Request $request, Chatbot $chatbot)
    {
        Gate::authorize('update', $chatbot);

        $validated = $request->validate([
            'primary_color' => ['required', 'string', 'max:7'],
            'secondary_color' => ['required', 'string', 'max:7'],
            'position' => ['required', 'string', 'in:bottom-right,bottom-left'],
            'welcome_message' => ['nullable', 'string', 'max:1000'],
            'placeholder_text' => ['nullable', 'string', 'max:255'],
            'bot_name' => ['nullable', 'string', 'max:255'],
            'avatar_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $chatbot->update($validated);

        return redirect()->route('chatbots.customize', $chatbot)
            ->with('success', 'Chatbot appearance updated successfully.');
    }

    public function toggle(Chatbot $chatbot)
    {
        Gate::authorize('update', $chatbot);
        $chatbot->update(['is_active' => !$chatbot->is_active]);
        return back()->with('success', 'Status chatbot dikemaskini.');
    }

    public function embed(Chatbot $chatbot)
    {
        Gate::authorize('view', $chatbot);
        return view('chatbots.embed', compact('chatbot'));
    }

    public function regenerateKey(Chatbot $chatbot)
    {
        Gate::authorize('update', $chatbot);
        $chatbot->regenerateApiKey();
        return back()->with('success', 'Kunci API berjaya dijana semula.');
    }
}
