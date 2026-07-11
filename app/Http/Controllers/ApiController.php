<?php

namespace App\Http\Controllers;

use App\Models\Chatbot;
use App\Models\ChatLog;
use App\Models\User;
use App\Services\ChatbotResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiController extends Controller
{
    public function config(Request $request, $apiKey)
    {
        $chatbot = Chatbot::where('api_key', $apiKey)->where('is_active', true)->firstOrFail();

        if (! $this->isOriginAllowed($request, $chatbot)) {
            return response()->json(['error' => __('chatme.api.domain_forbidden')], 403);
        }

        return response()->json([
            'id' => $chatbot->id,
            'name' => $chatbot->name,
            'bot_name' => $chatbot->bot_name,
            'avatar_url' => $chatbot->resolvedAvatarUrl(),
            'primary_color' => $chatbot->primary_color,
            'secondary_color' => $chatbot->secondary_color,
            'position' => $chatbot->position,
            'welcome_message' => $chatbot->welcome_message,
            'placeholder_text' => $chatbot->placeholder_text,
        ])->header('Access-Control-Allow-Origin', '*');
    }

    public function chat(Request $request, $apiKey, ChatbotResponseService $responses)
    {
        $chatbot = Chatbot::where('api_key', $apiKey)->where('is_active', true)->firstOrFail();

        if (! $this->isOriginAllowed($request, $chatbot)) {
            return response()->json(['error' => __('chatme.api.domain_forbidden')], 403);
        }

        if (! $chatbot->user->canSendChatMessage()) {
            return response()->json(['error' => __('chatme.api.monthly_limit')], 429);
        }

        $data = $request->validate([
            'message' => 'required|string|max:1000',
            'session_id' => 'nullable|string|max:100',
        ]);

        $sessionId = $data['session_id'] ?? 'session_'.uniqid();
        $userMessage = trim($data['message']);
        $preparedResponse = $responses->respond($chatbot, $userMessage)->answer;

        $response = DB::transaction(function () use ($chatbot, $preparedResponse, $request, $sessionId, $userMessage): ?string {
            $owner = User::query()
                ->lockForUpdate()
                ->findOrFail($chatbot->user_id);

            if (! $owner->canSendChatMessage()) {
                return null;
            }

            ChatLog::create([
                'chatbot_id' => $chatbot->id,
                'session_id' => $sessionId,
                'message' => $userMessage,
                'role' => 'user',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            ChatLog::create([
                'chatbot_id' => $chatbot->id,
                'session_id' => $sessionId,
                'message' => $preparedResponse,
                'role' => 'bot',
            ]);

            return $preparedResponse;
        });

        if ($response === null) {
            return response()->json(['error' => __('chatme.api.monthly_limit')], 429);
        }

        return response()->json([
            'response' => $response,
            'session_id' => $sessionId,
        ])->header('Access-Control-Allow-Origin', '*');
    }

    private function isOriginAllowed(Request $request, Chatbot $chatbot): bool
    {
        if (blank($chatbot->domain_whitelist)) {
            return true;
        }

        $origin = $request->header('Origin') ?? $request->header('Referer');
        $originHost = $origin ? strtolower((string) parse_url($origin, PHP_URL_HOST)) : '';

        if ($originHost === '') {
            return false;
        }

        foreach (explode(',', $chatbot->domain_whitelist) as $entry) {
            $entry = strtolower(trim($entry));
            if ($entry === '*') {
                return true;
            }

            $allowedHost = (string) parse_url(
                str_contains($entry, '://') ? $entry : "https://{$entry}",
                PHP_URL_HOST
            );

            if ($allowedHost !== '' &&
                ($originHost === $allowedHost || str_ends_with($originHost, ".{$allowedHost}"))) {
                return true;
            }
        }

        return false;
    }
}
