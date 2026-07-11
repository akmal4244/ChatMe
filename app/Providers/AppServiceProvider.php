<?php

namespace App\Providers;

use App\Contracts\AiAnswerProvider;
use App\Models\Chatbot;
use App\Services\Ai\CloudflareWorkersAiProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AiAnswerProvider::class, CloudflareWorkersAiProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('developer-api', function (Request $request): Limit {
            $chatbot = $request->attributes->get('developer_chatbot');
            $tokenKey = $chatbot instanceof Chatbot
                ? $chatbot->developer_api_token_hash
                : hash('sha256', (string) $request->bearerToken());

            return Limit::perMinute(60)->by($tokenKey.'|'.($request->ip() ?: 'unknown'));
        });

        RateLimiter::for('registration', fn (Request $request): Limit => Limit::perHour(3)
            ->by($request->ip() ?: 'unknown')
            ->response(fn (Request $request, array $headers) => back()
                ->withErrors(['email' => 'Terlalu banyak percubaan pendaftaran. Sila cuba semula kemudian.'])
                ->withInput($request->except('password', 'password_confirmation'))
                ->withHeaders($headers)));
    }
}
