<?php

use App\Http\Controllers\ApiController;
use App\Http\Controllers\DeveloperApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Auth user route
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Public Widget API (rate limited: 60 requests per minute)
Route::middleware('throttle:60,1')->group(function () {
    Route::post('/chatbots/{chatbot:api_key}/chat', [ApiController::class, 'chat'])->name('api.chat');
    Route::get('/chatbots/{chatbot:api_key}/config', [ApiController::class, 'config'])->name('api.widget.config');
});

Route::post('/v1/chat', DeveloperApiController::class)
    ->middleware(['developer.token', 'throttle:developer-api'])
    ->name('api.developer.chat');

// CORS preflight
Route::options('/chatbots/{chatbot:api_key}/chat', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
})->name('api.chat.options');
