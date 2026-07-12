<?php

use App\Http\Controllers\ApiController;
use App\Http\Controllers\DeveloperApiController;
use Illuminate\Support\Facades\Route;

Route::post('/chatbots/{chatbot:api_key}/chat', [ApiController::class, 'chat'])
    ->middleware('throttle:widget-chat-ingress')
    ->name('api.chat');
Route::get('/chatbots/{chatbot:api_key}/config', [ApiController::class, 'config'])
    ->middleware('throttle:widget-bootstrap')
    ->name('api.widget.config');

Route::post('/v1/chat', DeveloperApiController::class)
    ->middleware(['developer.token', 'throttle:developer-api'])
    ->name('api.developer.chat');

// CORS preflight
Route::options('/chatbots/{chatbot:api_key}/chat', function () {
    return response('', 200);
})->middleware('throttle:widget-bootstrap')->name('api.chat.options');
