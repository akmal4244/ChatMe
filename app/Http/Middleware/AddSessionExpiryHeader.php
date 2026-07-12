<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSessionExpiryHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user() !== null) {
            $response->headers->set(
                'X-Session-Expires-At',
                (string) now()->addMinutes((int) config('session.lifetime'))->timestamp,
            );
        }

        return $response;
    }
}
