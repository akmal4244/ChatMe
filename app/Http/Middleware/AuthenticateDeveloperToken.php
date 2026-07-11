<?php

namespace App\Http\Middleware;

use App\Models\Chatbot;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDeveloperToken
{
    private const ERROR = 'Akses API tidak dibenarkan.';

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if (! is_string($token) || ! str_starts_with($token, 'cm_live_')) {
            return $this->denied(401);
        }

        $chatbot = Chatbot::query()
            ->with('user')
            ->where('developer_api_token_hash', hash('sha256', $token))
            ->first();

        if (! $chatbot || ! $chatbot->is_active) {
            return $this->denied(401);
        }

        if (! (bool) $chatbot->user->currentPlan()?->api_access) {
            return $this->denied(403);
        }

        $request->attributes->set('developer_chatbot', $chatbot);

        return $next($request);
    }

    private function denied(int $status): JsonResponse
    {
        return response()->json(['error' => self::ERROR], $status);
    }
}
