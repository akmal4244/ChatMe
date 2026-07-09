<?php

namespace App\Policies;

use App\Models\Chatbot;
use App\Models\User;

class ChatbotPolicy
{
    public function before(User $user): ?bool
    {
        return $user->is_admin ? true : null;
    }

    public function view(User $user, Chatbot $chatbot): bool
    {
        return $chatbot->user_id === $user->id;
    }

    public function update(User $user, Chatbot $chatbot): bool
    {
        return $chatbot->user_id === $user->id;
    }

    public function delete(User $user, Chatbot $chatbot): bool
    {
        return $chatbot->user_id === $user->id;
    }
}
