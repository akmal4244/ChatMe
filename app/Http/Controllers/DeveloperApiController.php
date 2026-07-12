<?php

namespace App\Http\Controllers;

use App\Models\Chatbot;
use App\Services\ChatbotResponseService;
use App\Services\MessageQuotaService;
use App\Services\OwnerMessagingLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class DeveloperApiController extends Controller
{
    public function __invoke(
        Request $request,
        ChatbotResponseService $responses,
        MessageQuotaService $quotas,
        OwnerMessagingLimiter $ownerLimits,
    ): JsonResponse {
        $chatbot = $request->attributes->get('developer_chatbot');
        abort_unless($chatbot instanceof Chatbot, 401);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'session_id' => ['nullable', 'string', 'max:100'],
        ]);

        if ($ownerLimits->denied($chatbot)) {
            return response()->json(['error' => __('chatme.api.too_many_requests')], 429);
        }

        $permit = $quotas->reserve($chatbot, 'developer_api');
        if ($permit === null) {
            return $this->monthlyLimitResponse($chatbot);
        }

        $sessionId = $validated['session_id'] ?? 'session_'.Str::uuid();
        $userMessage = trim($validated['message']);
        $ipAddress = is_string($request->ip()) ? Str::substr($request->ip(), 0, 255) : null;
        $userAgent = is_string($request->userAgent()) ? Str::substr($request->userAgent(), 0, 255) : null;

        try {
            $response = $responses->respond($chatbot, $userMessage)->answer;
            $quotas->complete(
                $permit,
                sessionId: $sessionId,
                userMessage: $userMessage,
                botMessage: $response,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );
        } catch (Throwable $exception) {
            $quotas->release($permit);

            throw $exception;
        }

        return response()->json([
            'response' => $response,
            'session_id' => $sessionId,
        ]);
    }

    private function monthlyLimitResponse(Chatbot $chatbot): JsonResponse
    {
        Log::notice('Monthly message quota exceeded.', [
            'user_id' => $chatbot->user_id,
            'chatbot_id' => $chatbot->id,
            'channel' => 'developer_api',
        ]);

        return response()->json(['error' => __('chatme.api.monthly_limit')], 429);
    }
}
