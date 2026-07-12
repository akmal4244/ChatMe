<?php

namespace App\Http\Middleware;

use App\Models\Chatbot;
use App\Services\WidgetOriginService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Cors
{
    public function __construct(private readonly WidgetOriginService $origins) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('api.chat', 'api.widget.config', 'api.chat.options')) {
            return $next($request);
        }

        $chatbot = $this->resolveChatbot($request);
        $origin = $this->origins->fromRequest($request);
        $allowed = $chatbot !== null && $this->origins->isAllowed($chatbot, $origin);
        if ($allowed) {
            $request->attributes->set('widget_origin', $origin);
        }

        if ($request->isMethod('OPTIONS')) {
            if (! $allowed) {
                return response()->json(['error' => __('chatme.api.domain_forbidden')], 403);
            }

            $response = $this->withCors(response('', 200), $origin);
            $response->headers->set('Access-Control-Allow-Methods', 'POST, OPTIONS');

            return $response;
        }

        $response = $next($request);

        return $allowed ? $this->withCors($response, $origin) : $response;
    }

    private function resolveChatbot(Request $request): ?Chatbot
    {
        $routeValue = $request->route('chatbot');
        if ($routeValue instanceof Chatbot) {
            return $routeValue;
        }

        if (! is_string($routeValue) || $routeValue === '') {
            return null;
        }

        return Chatbot::query()
            ->where('api_key', $routeValue)
            ->where('is_active', true)
            ->first();
    }

    private function withCors(Response $response, ?string $origin): Response
    {
        if ($origin === null) {
            return $response;
        }

        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type');
        $response->headers->set('Access-Control-Max-Age', '600');
        $response->headers->set('Vary', 'Origin');

        return $response;
    }
}
