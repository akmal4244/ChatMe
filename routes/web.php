<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\ChatbotTestController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeveloperTokenController;
use App\Http\Controllers\KnowledgeController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\ToyyibPayCallbackController;
use App\Http\Controllers\WidgetController;
use Illuminate\Support\Facades\Route;

// ── Public / Landing Routes ──
Route::get('/', [LandingController::class, 'index'])->name('landing');
Route::get('/pricing', [LandingController::class, 'pricing'])->name('landing.pricing');
Route::view('/privacy', 'privacy')->name('privacy');
Route::view('/terms', 'terms')->name('terms');
Route::post('/payments/toyyibpay/callback', ToyyibPayCallbackController::class)
    ->middleware('throttle:120,1')
    ->name('payments.toyyibpay.callback');

// ── Guest Auth Routes ──
Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// ── Authenticated Routes ──
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::view('/onboarding', 'onboarding')->name('onboarding');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Chatbots
    Route::resource('chatbots', ChatbotController::class);
    Route::post('/chatbots/{chatbot}/toggle', [ChatbotController::class, 'toggle'])->name('chatbots.toggle');
    Route::post('/chatbots/{chatbot}/test-message', ChatbotTestController::class)->name('chatbots.test-message');
    Route::get('/chatbots/{chatbot}/embed', [ChatbotController::class, 'embed'])->name('chatbots.embed');
    Route::post('/chatbots/{chatbot}/regenerate-key', [ChatbotController::class, 'regenerateKey'])->name('chatbots.regenerate-key');
    Route::post('/chatbots/{chatbot}/developer-token', DeveloperTokenController::class)->name('chatbots.developer-token');

    // Knowledge Base
    Route::prefix('chatbots/{chatbot}/knowledge')->name('knowledge.')->group(function () {
        Route::get('/', [KnowledgeController::class, 'index'])->name('index');
        Route::post('/', [KnowledgeController::class, 'store'])->name('store');
        Route::put('/{item}', [KnowledgeController::class, 'update'])->name('update');
        Route::delete('/{item}', [KnowledgeController::class, 'destroy'])->name('destroy');
        Route::post('/import', [KnowledgeController::class, 'import'])->name('import');
    });

    // Subscription
    Route::get('/subscription/plans', [SubscriptionController::class, 'plans'])->name('subscription.plans');
    Route::post('/subscription/{plan}/checkout', [SubscriptionController::class, 'checkout'])->name('subscription.checkout');
    Route::get('/subscription/orders/{paymentOrder}/return', [SubscriptionController::class, 'result'])->name('subscription.return');
    Route::post('/subscription/orders/{paymentOrder}/reconcile', [SubscriptionController::class, 'reconcile'])
        ->middleware('throttle:10,1')
        ->name('subscription.reconcile');
});

// ── Admin Routes ──
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/users', [AdminController::class, 'users'])->name('users');
    Route::get('/chatbots', [AdminController::class, 'chatbots'])->name('chatbots');
    Route::post('/users/{user}/toggle-admin', [AdminController::class, 'toggleAdmin'])->name('users.toggle-admin');
});

// Widget Script (public)
Route::get('/widget/{chatbot:api_key}.js', [WidgetController::class, 'script'])->name('widget.script');
