<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PaymentOrder extends Model
{
    public const STATUS_CREATING = 'creating';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'user_id',
        'plan_id',
        'subscription_id',
        'checkout_key',
        'bill_code',
        'provider',
        'amount_cents',
        'status',
        'transaction_reference',
        'failure_reason',
        'paid_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (PaymentOrder $order): void {
            $order->external_reference = (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'external_reference';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
