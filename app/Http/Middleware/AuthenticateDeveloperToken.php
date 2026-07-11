<?php

namespace App\Http\Middleware;

use App\Models\Chatbot;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDeveloperToken
{
    private const ERROR = 'Akses API tidak dibenarkan.';

    public function handle(Request $request, Closure $next): Response
    {
        $rateLimitKey = 'developer-api-auth:'.($request->ip() ?: 'unknown');
        if (RateLimiter::tooManyAttempts($rateLimitKey, 30)) {
            return response()->json([
                'error' => __('chatme.api.too_many_requests'),
            ], 429);
        }

        $token = $request->bearerToken();
        if (! is_string($token) || ! str_starts_with($token, 'cm_live_')) {
            return $this->denied($rateLimitKey, 401);
        }

        $chatbot = Chatbot::query()
            ->with('user')
            ->where('developer_api_token_hash', hash('sha256', $token))
            ->first();

        if (! $chatbot || ! $chatbot->is_active) {
            return $this->denied($rateLimitKey, 401);
        }

        if (! (bool) $chatbot->user->currentPlan()?->api_access) {
            return $this->denied($rateLimitKey, 403);
        }

        $request->attributes->set('developer_chatbot', $chatbot);

        return $next($request);
    }

    private function denied(string $rateLimitKey, int $status): JsonResponse
    {
        RateLimiter::hit($rateLimitKey, 60);

        return response()->json(['error' => self::ERROR], $status);
    }
}
