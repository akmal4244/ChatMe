<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 */
class Subscription extends Model
{
    protected $fillable = [
        'user_id',
        'plan_id',
        'unit_price_cents',
        'provider',
        'provider_reference',
        'status',
        'stripe_id',
        'stripe_status',
        'trial_ends_at',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'plan_id' => 'integer',
            'unit_price_cents' => 'integer',
            'trial_ends_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isActive(): bool
    {
        $now = now();

        if ($this->status !== 'active') {
            return false;
        }

        if (! $this->starts_at || $this->starts_at->gt($now)) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->lte($now)) {
            return false;
        }

        if (! $this->ends_at
            && $this->provider !== 'system'
            && (! $this->plan || $this->plan->priceInCents() > 0)) {
            return false;
        }

        return true;
    }

    public function onTrial(): bool
    {
        return $this->trial_ends_at && now()->lt($this->trial_ends_at);
    }
}
