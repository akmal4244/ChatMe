<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\AuthenticateDeveloperToken;
use App\Http\Middleware\Cors;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'developer.token' => AuthenticateDeveloperToken::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'payments/toyyibpay/callback',
        ]);
        $middleware->api(prepend: [
            Cors::class,
        ]);
        $middleware->web(append: [
            SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request) {
            return $request->is('api/*') || $request->expectsJson();
        });
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (config('app.debug')
                || (! $request->is('api/*') && ! $request->expectsJson())) {
                return null;
            }

            if ($exception instanceof ValidationException
                || $exception instanceof AuthenticationException
                || $exception instanceof AuthorizationException) {
                return null;
            }

            $status = match (true) {
                $exception instanceof ModelNotFoundException => 404,
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                default => 500,
            };

            $message = match ($status) {
                404 => __('chatme.api.not_found'),
                419 => __('chatme.api.session_expired'),
                429 => __('chatme.api.too_many_requests'),
                500 => __('chatme.api.server_error'),
                default => null,
            };

            return $message === null
                ? null
                : response()->json(['error' => $message], $status);
        });
    })->create();
