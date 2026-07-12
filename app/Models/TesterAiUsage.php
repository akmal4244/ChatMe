<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TesterAiUsage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'attempts' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
