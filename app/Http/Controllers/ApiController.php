<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidWidgetTicketException;
use App\Models\Chatbot;
use App\Services\ChatbotResponseService;
use App\Services\MessageQuotaService;
use App\Services\WidgetAbuseService;
use App\Services\WidgetOriginService;
use App\Services\WidgetTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ApiController extends Controller
{
    public function config(
        Request $request,
        $apiKey,
        WidgetOriginService $origins,
        WidgetTicketService $tickets,
    ): JsonResponse {
        $chatbot = Chatbot::where('api_key', $apiKey)->where('is_active', true)->firstOrFail();
        $origin = $this->allowedOrigin($request, $chatbot, $origins);

        if ($origin === null) {
            return response()->json(['error' => __('chatme.api.domain_forbidden')], 403);
        }

        $ticket = $tickets->issue($request, $chatbot, $origin);

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
            'widget_ticket' => $ticket['ticket'],
            'widget_session_id' => $ticket['session_id'],
            'ticket_expires_at' => $ticket['expires_at'],
        ])->header('Cache-Control', 'no-store, private');
    }

    public function chat(
        Request $request,
        $apiKey,
        ChatbotResponseService $responses,
        MessageQuotaService $quotas,
        WidgetAbuseService $abuse,
        WidgetOriginService $origins,
        WidgetTicketService $tickets,
    ): JsonResponse {
        $chatbot = Chatbot::where('api_key', $apiKey)->where('is_active', true)->firstOrFail();
        $origin = $this->allowedOrigin($request, $chatbot, $origins);

        if ($origin === null) {
            return response()->json(['error' => __('chatme.api.domain_forbidden')], 403);
        }

        $data = $request->validate([
            'message' => 'required|string|max:1000',
            'session_id' => 'required|string|max:100',
        ]);
        $rawTicket = $request->input('widget_ticket');

        try {
            if (! is_string($rawTicket) || strlen($rawTicket) > 4096) {
                throw new InvalidWidgetTicketException('missing_or_oversized');
            }

            $claims = $tickets->validate(
                $request,
                $chatbot,
                $origin,
                $rawTicket,
                $data['session_id'],
            );
        } catch (InvalidWidgetTicketException $exception) {
            Log::notice('Widget ticket rejected.', [
                'chatbot_id' => $chatbot->id,
                'reason' => $exception->reason,
            ]);

            return response()->json(['error' => __('chatme.api.widget_session_invalid')], 401);
        }

        if ($abuse->deniedBy($request, $chatbot, $claims) !== null) {
            return response()->json(['error' => __('chatme.api.too_many_requests')], 429);
        }

        $permit = $quotas->reserve($chatbot, 'widget');
        if ($permit === null) {
            $this->logQuotaExceeded($chatbot);

            return response()->json(['error' => __('chatme.api.monthly_limit')], 429);
        }

        $sessionId = $data['session_id'];
        $userMessage = trim($data['message']);
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

    private function allowedOrigin(
        Request $request,
        Chatbot $chatbot,
        WidgetOriginService $origins,
    ): ?string {
        $origin = $request->attributes->get('widget_origin');
        $origin = is_string($origin) ? $origin : $origins->fromRequest($request);

        return $origins->isAllowed($chatbot, $origin) ? $origin : null;
    }

    private function logQuotaExceeded(Chatbot $chatbot): void
    {
        Log::notice('Monthly message quota exceeded.', [
            'user_id' => $chatbot->user_id,
            'chatbot_id' => $chatbot->id,
            'channel' => 'widget',
        ]);
    }
}
