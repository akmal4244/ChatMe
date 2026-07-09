<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Chatbot extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'avatar_url',
        'primary_color',
        'secondary_color',
        'position',
        'welcome_message',
        'placeholder_text',
        'bot_name',
        'system_prompt',
        'is_active',
        'api_key',
        'domain_whitelist',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Chatbot $chatbot) {
            if (empty($chatbot->slug)) {
                $chatbot->slug = Str::slug($chatbot->name) . '-' . Str::random(6);
            }
            if (empty($chatbot->api_key)) {
                $chatbot->api_key = 'cm_' . Str::random(32);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function knowledgeItems(): HasMany
    {
        return $this->hasMany(KnowledgeItem::class);
    }

    public function chatLogs(): HasMany
    {
        return $this->hasMany(ChatLog::class);
    }

    public function getEmbedCode(): string
    {
        return '<script src="' . config('app.url') . '/widget/' . $this->api_key . '.js"></script>';
    }
}
