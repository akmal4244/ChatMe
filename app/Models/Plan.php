<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use UnexpectedValueException;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'price',
        'chatbot_limit',
        'knowledge_limit',
        'monthly_messages',
        'custom_domain',
        'remove_branding',
        'api_access',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'chatbot_limit' => 'integer',
            'knowledge_limit' => 'integer',
            'monthly_messages' => 'integer',
            'custom_domain' => 'boolean',
            'remove_branding' => 'boolean',
            'api_access' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function scopeVisibleForSale(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function (Builder $query): void {
                $query
                    ->where(function (Builder $query): void {
                        $query->where('slug', 'free')->where('price', 0);
                    })
                    ->orWhere(function (Builder $query): void {
                        $query->whereIn('slug', ['pro', 'enterprise'])->where('price', '>', 0);
                    });
            })
            ->orderBy('price');
    }

    public function isFree(): bool
    {
        return $this->price <= 0;
    }

    public function priceInCents(): int
    {
        $price = (string) $this->price;

        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $price, $matches)) {
            throw new UnexpectedValueException('Plan price must be a non-negative decimal with at most two places.');
        }

        $whole = (int) $matches[1];
        $fraction = str_pad($matches[2] ?? '', 2, '0');

        return ($whole * 100) + (int) $fraction;
    }
}
