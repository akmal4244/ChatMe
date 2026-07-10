<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('chatbots')
            ->whereIn('welcome_message', [
                'Hello! How can I help you today?',
                'Hello! How can I help you?',
            ])
            ->update(['welcome_message' => 'Helo! Bagaimana saya boleh membantu anda?']);

        DB::table('chatbots')
            ->where('placeholder_text', 'Type your message...')
            ->update(['placeholder_text' => 'Taip mesej anda...']);
    }

    public function down(): void
    {
        // Forward-only data cleanup: translated values may have been edited by users.
    }
};
