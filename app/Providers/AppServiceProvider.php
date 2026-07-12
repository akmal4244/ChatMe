<?php

namespace App\Providers;

use App\Contracts\AiAnswerProvider;
use App\Models\Chatbot;
use App\Services\Ai\CloudflareWorkersAiProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        $appUrl = rtrim((string) config('app.url'), '/');
        $scheme = parse_url($appUrl, PHP_URL_SCHEME);
        if (filter_var($appUrl, FILTER_VALIDATE_URL)
            && in_array($scheme, ['http', 'https'], true)) {
            URL::forceRootUrl($appUrl);
            URL::forceScheme((string) $scheme);
        }

        ResetPassword::createUrlUsing(fn ($notifiable, string $token): string => route('password.reset', [
            'token' => $token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]));
        ResetPassword::toMailUsing(function ($notifiable, string $token): MailMessage {
            $url = route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
            $expiryMinutes = (int) config('auth.passwords.users.expire', 60);

            return (new MailMessage)
                ->subject('Tetapkan semula kata laluan ChatMe')
                ->greeting('Salam,')
                ->line('Kami menerima permintaan untuk menetapkan semula kata laluan akaun ChatMe anda.')
                ->action('Tetapkan semula kata laluan', $url)
                ->line("Pautan ini akan tamat dalam {$expiryMinutes} minit.")
                ->line('Jika anda tidak membuat permintaan ini, anda boleh mengabaikan e-mel ini.');
        });
        VerifyEmail::toMailUsing(function ($notifiable, string $url): MailMessage {
            $expiryMinutes = (int) config('auth.verification.expire', 60);

            return (new MailMessage)
                ->subject('Sahkan e-mel ChatMe anda')
                ->greeting('Salam,')
                ->line('Sahkan alamat e-mel anda untuk menggunakan semua fungsi ChatMe.')
                ->action('Sahkan e-mel', $url)
                ->line("Pautan ini akan tamat dalam {$expiryMinutes} minit.")
                ->line('Jika anda tidak mencipta akaun ini, anda boleh mengabaikan e-mel ini.');
        });

        Gate::after(function ($user, string $ability, ?bool $result): void {
            if ($result !== false) {
                return;
            }

            Log::warning('Authorization denied.', [
                'user_id' => $user->getAuthIdentifier(),
                'route' => request()->route()?->getName(),
                'ip_address' => request()->ip() ?: 'unknown',
            ]);
        });

        RateLimiter::for('developer-api', function (Request $request): Limit {
            $chatbot = $request->attributes->get('developer_chatbot');
            $tokenKey = $chatbot instanceof Chatbot
                ? $chatbot->developer_api_token_hash
                : hash('sha256', (string) $request->bearerToken());

            return Limit::perMinute(60)->by($tokenKey.'|'.($request->ip() ?: 'unknown'));
        });

        RateLimiter::for('chatbot-tester', function (Request $request): Limit {
            $chatbot = $request->route('chatbot');
            $chatbotKey = $chatbot instanceof Chatbot
                ? $chatbot->getKey()
                : (string) $chatbot;
            $userKey = $request->user()?->getAuthIdentifier() ?? 'guest';

            return Limit::perMinute(20)->by($userKey.'|'.$chatbotKey);
        });

        RateLimiter::for('widget-bootstrap', function (Request $request): Limit {
            $chatbot = $request->route('chatbot');
            $chatbotKey = $chatbot instanceof Chatbot ? $chatbot->getKey() : (string) $chatbot;

            return Limit::perMinute(max(1, (int) config('chatme.widget.limits.bootstrap_per_minute', 30)))
                ->by($chatbotKey.'|'.($request->ip() ?: 'unknown'))
                ->response(fn () => response()->json([
                    'error' => __('chatme.api.too_many_requests'),
                ], 429));
        });

        RateLimiter::for('widget-chat-ingress', function (Request $request): array {
            $chatbot = $request->route('chatbot');
            $chatbotKey = $chatbot instanceof Chatbot ? $chatbot->getKey() : (string) $chatbot;
            $response = fn () => response()->json([
                'error' => __('chatme.api.too_many_requests'),
            ], 429);

            return [
                Limit::perMinute(max(1, (int) config('chatme.widget.limits.ingress_ip_per_minute', 60)))
                    ->by($chatbotKey.'|'.($request->ip() ?: 'unknown'))
                    ->response($response),
                Limit::perMinute(max(1, (int) config('chatme.widget.limits.ingress_bot_per_minute', 600)))
                    ->by($chatbotKey)
                    ->response($response),
            ];
        });

        RateLimiter::for('registration', fn (Request $request): Limit => Limit::perHour(3)
            ->by($request->ip() ?: 'unknown')
            ->response(fn (Request $request, array $headers) => back()
                ->withErrors(['email' => 'Terlalu banyak percubaan pendaftaran. Sila cuba semula kemudian.'])
                ->withInput($request->except('password', 'password_confirmation'))
                ->withHeaders($headers)));

        RateLimiter::for('password-reset', function (Request $request): array {
            $emailHash = hash('sha256', Str::lower(trim((string) $request->input('email'))));
            $routeKey = $request->route()?->getName() ?? 'password-reset';
            $ipAddress = $request->ip() ?: 'unknown';
            $response = fn (Request $request, array $headers) => back()
                ->with('error', 'Terlalu banyak permintaan pautan. Sila cuba semula kemudian.')
                ->withInput($request->only('email'))
                ->withHeaders($headers);
            $limits = [
                Limit::perMinute(5)
                    ->by($routeKey.'|'.$emailHash.'|'.($request->ip() ?: 'unknown'))
                    ->response($response),
            ];

            if ($routeKey === 'password.email') {
                $limits[] = Limit::perHour(20)
                    ->by('password-link-ip|'.$ipAddress)
                    ->response($response);
            }

            return $limits;
        });

        RateLimiter::for('verification', function (Request $request): Limit {
            $routeKey = $request->route()?->getName() ?? 'verification';
            $userKey = $request->user()?->getAuthIdentifier() ?? 'guest';

            return Limit::perMinute(6)
                ->by($routeKey.'|'.$userKey.'|'.($request->ip() ?: 'unknown'))
                ->response(fn (Request $request, array $headers) => back()
                    ->with('error', 'Terlalu banyak permintaan pengesahan. Sila cuba semula dalam satu minit.')
                    ->withHeaders($headers));
        });

        RateLimiter::for('profile-update', function (Request $request): array {
            $currentEmail = Str::lower(trim((string) $request->user()?->email));
            $requestedEmail = Str::lower(trim((string) $request->input('email')));
            $emailChanged = $currentEmail !== $requestedEmail;
            $userKey = $request->user()?->getAuthIdentifier() ?? 'guest';
            $response = fn (Request $request, array $headers) => back()
                ->with('error', 'Terlalu banyak perubahan profil. Sila cuba semula kemudian.')
                ->withInput($request->except('current_password'))
                ->withHeaders($headers);
            $limits = [
                Limit::perHour($emailChanged ? 5 : 60)
                    ->by($userKey.'|'.($request->ip() ?: 'unknown'))
                    ->response($response),
            ];

            if ($emailChanged) {
                $limits[] = Limit::perHour(10)
                    ->by('profile-email-user|'.$userKey)
                    ->response($response);
            }

            return $limits;
        });

        RateLimiter::for('sensitive-account', function (Request $request): array {
            $routeKey = $request->route()?->getName() ?? 'sensitive-account';
            $userKey = $request->user()?->getAuthIdentifier() ?? 'guest';
            $response = fn (Request $request, array $headers) => back()
                ->with('error', 'Terlalu banyak percubaan tindakan sensitif. Sila cuba semula kemudian.')
                ->withHeaders($headers);

            return [
                Limit::perMinute(5)
                    ->by($routeKey.'|'.$userKey.'|'.($request->ip() ?: 'unknown'))
                    ->response($response),
                Limit::perHour(20)
                    ->by('sensitive-user|'.$userKey)
                    ->response($response),
            ];
        });
    }
}
