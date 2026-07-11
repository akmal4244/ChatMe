<?php

namespace App\Http\Controllers;

use App\Models\Chatbot;
use App\Services\ChatbotResponseMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ChatbotTestController extends Controller
{
    public function __invoke(
        Request $request,
        Chatbot $chatbot,
        ChatbotResponseMatcher $matcher,
    ): JsonResponse {
        Gate::authorize('view', $chatbot);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        return response()->json([
            'response' => $matcher->respond($chatbot, trim($validated['message'])),
        ]);
    }
}
