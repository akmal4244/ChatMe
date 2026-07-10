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
                $chatbot->slug = Str::slug($chatbot->name).'-'.Str::random(6);
            }
            if (empty($chatbot->api_key)) {
                $chatbot->api_key = self::newApiKey();
            }
        });
    }

    public static function newApiKey(): string
    {
        return 'cm_'.Str::random(32);
    }

    public function regenerateApiKey(): void
    {
        do {
            $apiKey = self::newApiKey();
        } while (self::query()
            ->where('api_key', $apiKey)
            ->where('id', '!=', $this->getKey())
            ->exists());

        $this->forceFill(['api_key' => $apiKey])->save();
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
        return '<script src="'.config('app.url').'/widget/'.$this->api_key.'.js"></script>';
    }

    public function resolvedAvatarUrl(): string
    {
        $avatar = trim((string) $this->avatar_url);

        if ($avatar === '') {
            return asset('akmal3d.png');
        }

        if (filter_var($avatar, FILTER_VALIDATE_URL)) {
            return $avatar;
        }

        $path = ltrim(str_replace('\\', '/', $avatar), '/');

        if ($path === '' || str_contains($path, '..')) {
            return asset('akmal3d.png');
        }

        if (str_starts_with($path, 'storage/') || is_file(public_path($path))) {
            return asset($path);
        }

        return asset('storage/'.$path);
    }
}
