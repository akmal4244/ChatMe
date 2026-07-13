<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\AuthenticatedPasswordSetupLinkController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\ChatbotTestController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeveloperTokenController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\KnowledgeController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\ToyyibPayCallbackController;
use App\Http\Controllers\WidgetController;
use Illuminate\Support\Facades\Route;

// ── Public / Landing Routes ──
Route::get('/', [LandingController::class, 'index'])->name('landing');
Route::get('/pricing', [LandingController::class, 'pricing'])->name('landing.pricing');
Route::view('/privacy', 'privacy')->name('privacy');
Route::view('/terms', 'terms')->name('terms');
Route::get('/health', HealthController::class)
    ->middleware('throttle:30,1')
    ->name('health');
Route::post('/payments/toyyibpay/callback', ToyyibPayCallbackController::class)
    ->middleware('throttle:120,1')
    ->name('payments.toyyibpay.callback');

// ── Guest Auth Routes ──
Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:registration');
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])
        ->middleware('throttle:google-auth')
        ->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
        ->middleware('throttle:google-auth')
        ->name('auth.google.callback');
    Route::get('/lupa-kata-laluan', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/lupa-kata-laluan', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.email');
});

// Reset completion also supports an authenticated owner setting their first local password.
Route::get('/tetap-semula-kata-laluan/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
Route::post('/tetap-semula-kata-laluan', [NewPasswordController::class, 'store'])
    ->middleware('throttle:password-reset')
    ->name('password.update');

// ── Authenticated Routes ──
Route::middleware(['auth', 'auth.session', 'session.deadline'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/sahkan-e-mel', EmailVerificationPromptController::class)->name('verification.notice');
    Route::post('/sahkan-e-mel/hantar', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:verification')
        ->name('verification.send');
    Route::get('/sahkan-e-mel/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:verification'])
        ->name('verification.verify');
    Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profil', [ProfileController::class, 'update'])
        ->middleware('throttle:profile-update')
        ->name('profile.update');
    Route::put('/profil/kata-laluan', [ProfileController::class, 'updatePassword'])
        ->middleware('throttle:sensitive-account')
        ->name('profile.password.update');
    Route::post('/profil/kata-laluan/pautan-tetapan', AuthenticatedPasswordSetupLinkController::class)
        ->middleware('throttle:google-password-setup')
        ->name('profile.password.setup-link');

    Route::middleware('verified')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::view('/onboarding', 'onboarding')->name('onboarding');

        // Chatbots
        Route::resource('chatbots', ChatbotController::class)
            ->middlewareFor('store', 'throttle:chatbot-creation');
        Route::post('/chatbots/{chatbot}/toggle', [ChatbotController::class, 'toggle'])->name('chatbots.toggle');
        Route::post('/chatbots/{chatbot}/test-message', ChatbotTestController::class)
            ->middleware('throttle:chatbot-tester')
            ->name('chatbots.test-message');
        Route::get('/chatbots/{chatbot}/embed', [ChatbotController::class, 'embed'])->name('chatbots.embed');
        Route::post('/chatbots/{chatbot}/regenerate-key', [ChatbotController::class, 'regenerateKey'])
            ->middleware('throttle:sensitive-account')
            ->name('chatbots.regenerate-key');
        Route::post('/chatbots/{chatbot}/developer-token', DeveloperTokenController::class)
            ->middleware('throttle:sensitive-account')
            ->name('chatbots.developer-token');

        // Knowledge Base
        Route::prefix('chatbots/{chatbot}/knowledge')->name('knowledge.')->group(function () {
            Route::get('/', [KnowledgeController::class, 'index'])->name('index');
            Route::post('/', [KnowledgeController::class, 'store'])->name('store');
            Route::put('/{item}', [KnowledgeController::class, 'update'])->name('update');
            Route::delete('/{item}', [KnowledgeController::class, 'destroy'])->name('destroy');
            Route::post('/import', [KnowledgeController::class, 'import'])
                ->middleware('throttle:10,1')
                ->name('import');
        });

        // Subscription
        Route::get('/subscription/plans', [SubscriptionController::class, 'plans'])->name('subscription.plans');
        Route::post('/subscription/{plan}/checkout', [SubscriptionController::class, 'checkout'])
            ->middleware('throttle:5,1')
            ->name('subscription.checkout');
        Route::get('/subscription/orders/{paymentOrder}/return', [SubscriptionController::class, 'result'])->name('subscription.return');
        Route::post('/subscription/orders/{paymentOrder}/reconcile', [SubscriptionController::class, 'reconcile'])
            ->middleware('throttle:10,1')
            ->name('subscription.reconcile');
    });
});

// ── Admin Routes ──
Route::middleware(['auth', 'auth.session', 'session.deadline', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/users', [AdminController::class, 'users'])->name('users');
    Route::get('/chatbots', [AdminController::class, 'chatbots'])->name('chatbots');
    Route::post('/users/{user}/toggle-admin', [AdminController::class, 'toggleAdmin'])->name('users.toggle-admin');
});

// Widget Script (public)
Route::get('/widget/{chatbot:api_key}.js', [WidgetController::class, 'script'])
    ->middleware('throttle:120,1')
    ->name('widget.script');
