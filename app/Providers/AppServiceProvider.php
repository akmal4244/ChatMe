<?php

namespace App\Providers;

use App\Contracts\AiAnswerProvider;
use App\Services\Ai\CloudflareWorkersAiProvider;
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
        //
    }
}
