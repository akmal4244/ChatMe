<?php

namespace App\Contracts;

use App\Models\Chatbot;
use App\Models\KnowledgeItem;
use App\ValueObjects\AiProviderResult;
use Illuminate\Support\Collection;

interface AiAnswerProvider
{
    /** @param Collection<int, KnowledgeItem> $candidates */
    public function answer(Chatbot $chatbot, string $message, Collection $candidates): ?AiProviderResult;
}
