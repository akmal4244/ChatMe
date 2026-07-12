<?php

namespace App\Http\Controllers;

use App\Models\Chatbot;

class WidgetController extends Controller
{
    public function script($apiKey)
    {
        $chatbot = Chatbot::where('api_key', $apiKey)->where('is_active', true)->firstOrFail();

        $config = [
            'apiKey' => $chatbot->api_key,
            'apiUrl' => secure_url('/api/chatbots/'.$chatbot->api_key),
            'primaryColor' => $chatbot->primary_color,
            'secondaryColor' => $chatbot->secondary_color,
            'position' => $chatbot->position,
            'botName' => $chatbot->bot_name,
            'welcomeMessage' => $chatbot->welcome_message,
            'placeholderText' => $chatbot->placeholder_text,
            'avatarUrl' => $chatbot->resolvedAvatarUrl(),
            'showBranding' => ! (bool) $chatbot->user->currentPlan()?->remove_branding,
        ];

        $js = file_get_contents(public_path('widget.js'));
        $configJson = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response = "window.ChatMeConfig = {$configJson};
{$js}";

        return response($response)->header('Content-Type', 'application/javascript')
            ->header('Cache-Control', 'no-store, private');
    }
}
