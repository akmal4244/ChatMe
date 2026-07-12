<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatLog extends Model
{
    protected $fillable = [
        'chatbot_id',
        'session_id',
        'message',
        'role',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'role' => 'string',
        ];
    }

    /** @return BelongsTo<Chatbot, $this> */
    public function chatbot(): BelongsTo
    {
        return $this->belongsTo(Chatbot::class);
    }

    public function isFromUser(): bool
    {
        return $this->role === 'user';
    }

    public function isFromBot(): bool
    {
        return $this->role === 'bot';
    }
}
