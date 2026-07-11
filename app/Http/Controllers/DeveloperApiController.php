<?php

namespace App\Http\Controllers;

use App\Models\Chatbot;
use App\Models\ChatLog;
use App\Models\User;
use App\Services\ChatbotResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeveloperApiController extends Controller
{
    public function __invoke(Request $request, ChatbotResponseService $responses): JsonResponse
    {
        $chatbot = $request->attributes->get('developer_chatbot');
        abort_unless($chatbot instanceof Chatbot, 401);

        if (! $chatbot->user->canSendChatMessage()) {
            return $this->monthlyLimitResponse();
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'session_id' => ['nullable', 'string', 'max:100'],
        ]);

        $sessionId = $validated['session_id'] ?? 'session_'.uniqid();
        $userMessage = trim($validated['message']);
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
            return $this->monthlyLimitResponse();
        }

        return response()->json([
            'response' => $response,
            'session_id' => $sessionId,
        ]);
    }

    private function monthlyLimitResponse(): JsonResponse
    {
        return response()->json(['error' => __('chatme.api.monthly_limit')], 429);
    }
}
