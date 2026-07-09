<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function isFree(): bool
    {
        return $this->price <= 0;
    }
}
