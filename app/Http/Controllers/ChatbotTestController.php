<?php

namespace App\Http\Controllers;

use App\Models\Chatbot;
use App\Services\ChatbotResponseService;
use App\Services\TesterAiUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ChatbotTestController extends Controller
{
    public function __invoke(
        Request $request,
        Chatbot $chatbot,
        ChatbotResponseService $responses,
        TesterAiUsageService $testerUsage,
    ): JsonResponse {
        Gate::authorize('view', $chatbot);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $response = $responses->respond(
            $chatbot,
            trim($validated['message']),
            allowAi: (bool) config('services.cloudflare_ai.enabled'),
            beforeProvider: fn (): bool => $testerUsage->reserve($request->user()),
        );
        $payload = ['response' => $response->answer];

        if ($response->aiLimitReached) {
            $payload['notice'] = __('chatme.tester.ai_daily_limit');
        }

        return response()->json($payload);
    }
}
