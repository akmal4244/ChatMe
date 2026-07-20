<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, MustVerifyEmailTrait, Notifiable;

    // Nota keselamatan: 'is_admin' SENGAJA tidak disenaraikan di sini untuk
    // elak mass-assignment privilege escalation. Peranan admin hanya ditetapkan
    // melalui laluan eksplisit (forceFill dalam seeder/GoogleAccountService, atau
    // AdminController) — bukan melalui input pengguna.
    protected $fillable = [
        'name',
        'email',
        'password',
        'company',
        'website',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'google_sub',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'google_linked_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    public function hasLocalPassword(): bool
    {
        return filled($this->getRawOriginal('password'));
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify((new ResetPassword($token))->locale('ms'));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify((new VerifyEmail)->locale('ms'));
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** @return HasMany<Chatbot, $this> */
    public function chatbots(): HasMany
    {
        return $this->hasMany(Chatbot::class);
    }

    /** @return HasMany<PaymentOrder, $this> */
    public function paymentOrders(): HasMany
    {
        return $this->hasMany(PaymentOrder::class);
    }

    public function activeSubscription(): ?Subscription
    {
        $now = now();

        return $this->subscriptions()
            ->select('subscriptions.*')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->where('subscriptions.status', 'active')
            ->whereNotNull('subscriptions.starts_at')
            ->where('subscriptions.starts_at', '<=', $now)
            ->where(function ($query) use ($now) {
                $query->where('subscriptions.ends_at', '>', $now)
                    ->orWhere(function ($query): void {
                        $query->whereNull('subscriptions.ends_at')
                            ->where(function ($query): void {
                                $query->where('subscriptions.provider', 'system')
                                    ->orWhere('plans.price', '<=', 0);
                            });
                    });
            })
            ->orderByRaw('CASE WHEN plans.price > 0 THEN 1 ELSE 0 END DESC')
            ->orderByDesc('subscriptions.starts_at')
            ->orderByDesc('subscriptions.id')
            ->first();
    }

    public function currentPlan(): ?Plan
    {
        $sub = $this->activeSubscription();
        if ($sub) {
            return $sub->plan;
        }

        return Plan::query()
            ->where('slug', 'free')
            ->where('is_active', true)
            ->where('price', 0)
            ->first();
    }

    public function canCreateChatbot(): bool
    {
        $plan = $this->currentPlan();
        if (! $plan) {
            return false;
        }

        $chatbotCount = $this->chatbots()->count();
        $absoluteLimit = max(1, (int) config('chatme.chatbots.absolute_limit', 50));
        if ($chatbotCount >= $absoluteLimit) {
            return false;
        }

        if ($plan->chatbot_limit === -1) {
            return true;
        }

        return $chatbotCount < $plan->chatbot_limit;
    }

    public function canSendChatMessage(): bool
    {
        $plan = $this->currentPlan();
        if (! $plan) {
            return false;
        }

        if ($plan->monthly_messages === -1) {
            return true;
        }

        $businessNow = now((string) config('chatme.timezone'));
        $monthStartsAt = $businessNow->copy()->startOfMonth()->utc();
        $monthEndsAt = $businessNow->copy()->endOfMonth()->utc();

        $messagesThisMonth = ChatLog::query()
            ->where('role', 'user')
            ->whereHas('chatbot', fn ($query) => $query->where('user_id', $this->id))
            ->whereBetween('created_at', [$monthStartsAt, $monthEndsAt])
            ->count();
        $durableUsage = MessageUsage::query()
            ->where('user_id', $this->id)
            ->where('usage_month', $businessNow->copy()->startOfMonth()->toDateString())
            ->value('message_count');

        return max($messagesThisMonth, (int) ($durableUsage ?? 0)) < $plan->monthly_messages;
    }

    public function canAddKnowledgeItems(Chatbot $chatbot, int $count = 1): bool
    {
        if ($count < 0 || $chatbot->user_id !== $this->id) {
            return false;
        }

        $plan = $this->currentPlan();
        if (! $plan) {
            return false;
        }

        $currentCount = $chatbot->knowledgeItems()->count();
        $absoluteLimit = max(1, (int) config('chatme.knowledge.absolute_limit', 5000));
        if ($currentCount + $count > $absoluteLimit) {
            return false;
        }

        if ($plan->knowledge_limit === -1) {
            return true;
        }

        return $currentCount + $count <= $plan->knowledge_limit;
    }
}
