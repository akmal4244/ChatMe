<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'company',
        'website',
        'is_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function chatbots(): HasMany
    {
        return $this->hasMany(Chatbot::class);
    }

    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->latest()
            ->first();
    }

    public function currentPlan(): ?Plan
    {
        $sub = $this->activeSubscription();
        if ($sub) {
            return $sub->plan;
        }
        return Plan::where('slug', 'free')->first();
    }

    public function canCreateChatbot(): bool
    {
        $plan = $this->currentPlan();
        if (!$plan) {
            return false;
        }
        if ($plan->chatbot_limit === -1) {
            return true;
        }
        return $this->chatbots()->count() < $plan->chatbot_limit;
    }

    public function canSendChatMessage(): bool
    {
        $plan = $this->currentPlan();
        if (!$plan) {
            return false;
        }

        if ($plan->monthly_messages === -1) {
            return true;
        }

        $messagesThisMonth = ChatLog::query()
            ->where('role', 'user')
            ->whereHas('chatbot', fn ($query) => $query->where('user_id', $this->id))
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        return $messagesThisMonth < $plan->monthly_messages;
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

        if ($plan->knowledge_limit === -1) {
            return true;
        }

        return $chatbot->knowledgeItems()->count() + $count <= $plan->knowledge_limit;
    }
}
