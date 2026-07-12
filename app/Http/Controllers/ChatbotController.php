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
            'primary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'position' => ['nullable', 'string', 'in:bottom-right,bottom-left'],
            'welcome_message' => ['sometimes', 'required', 'string', 'max:1000'],
            'placeholder_text' => ['nullable', 'string', 'max:255'],
            'bot_name' => ['sometimes', 'required', 'string', 'max:255'],
            'system_prompt' => ['nullable', 'string', 'max:1000'],
            'fallback_message' => ['nullable', 'string', 'max:500'],
            'domain_whitelist' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! $request->user()->canCreateChatbot()) {
            throw ValidationException::withMessages([
                'name' => 'Anda telah mencapai had chatbot bagi pelan semasa.',
            ]);
        }

        $validated['slug'] = Str::slug($request->name).'-'.Str::random(6);
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
                'name' => 'Anda telah mencapai had chatbot bagi pelan semasa.',
            ]);
        }

        return redirect()->route('chatbots.index')
            ->with('success', 'Chatbot berjaya dicipta.');
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
            'primary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'position' => ['nullable', 'string', 'in:bottom-right,bottom-left'],
            'welcome_message' => ['sometimes', 'required', 'string', 'max:1000'],
            'placeholder_text' => ['nullable', 'string', 'max:255'],
            'bot_name' => ['sometimes', 'required', 'string', 'max:255'],
            'system_prompt' => ['nullable', 'string', 'max:1000'],
            'fallback_message' => ['nullable', 'string', 'max:500'],
            'domain_whitelist' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ]);

        $chatbot->update($validated);

        return redirect()->route('chatbots.index')
            ->with('success', 'Chatbot berjaya dikemas kini.');
    }

    /**
     * Delete a chatbot.
     */
    public function destroy(Chatbot $chatbot)
    {
        Gate::authorize('delete', $chatbot);

        $chatbot->delete();

        return redirect()->route('chatbots.index')
            ->with('success', 'Chatbot berjaya dipadam.');
    }

    public function toggle(Chatbot $chatbot)
    {
        Gate::authorize('update', $chatbot);
        $chatbot->update(['is_active' => ! $chatbot->is_active]);

        return back()->with('success', 'Status chatbot berjaya dikemas kini.');
    }

    public function embed(Chatbot $chatbot)
    {
        Gate::authorize('view', $chatbot);
        $apiAccess = (bool) $chatbot->user->currentPlan()?->api_access;

        return view('chatbots.embed', compact('apiAccess', 'chatbot'));
    }

    public function regenerateKey(Chatbot $chatbot)
    {
        Gate::authorize('update', $chatbot);
        $chatbot->regenerateApiKey();

        return back()->with('success', 'Kunci API berjaya dijana semula.');
    }
}
