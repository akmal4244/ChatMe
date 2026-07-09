<?php
namespace App\Http\Controllers;

use App\Models\Chatbot;
use App\Models\ChatLog;
use Illuminate\Http\Request;

class ApiController extends Controller
{
    public function config(Request $request, $apiKey)
    {
        $chatbot = Chatbot::where('api_key', $apiKey)->where('is_active', true)->firstOrFail();

        if (!$this->isOriginAllowed($request, $chatbot)) {
            return response()->json(['error' => 'Domain not allowed'], 403);
        }

        return response()->json([
            'id' => $chatbot->id,
            'name' => $chatbot->name,
            'bot_name' => $chatbot->bot_name,
            'avatar_url' => $chatbot->avatar_url ? secure_asset('storage/' . $chatbot->avatar_url) : secure_asset('akmal3d.png'),
            'primary_color' => $chatbot->primary_color,
            'secondary_color' => $chatbot->secondary_color,
            'position' => $chatbot->position,
            'welcome_message' => $chatbot->welcome_message,
            'placeholder_text' => $chatbot->placeholder_text,
        ])->header('Access-Control-Allow-Origin', '*');
    }

    public function chat(Request $request, $apiKey)
    {
        $chatbot = Chatbot::where('api_key', $apiKey)->where('is_active', true)->firstOrFail();

        if (!$this->isOriginAllowed($request, $chatbot)) {
            return response()->json(['error' => 'Domain not allowed'], 403);
        }

        if (!$chatbot->user->canSendChatMessage()) {
            return response()->json(['error' => 'Monthly message limit reached'], 429);
        }

        $data = $request->validate([
            'message' => 'required|string|max:1000',
            'session_id' => 'nullable|string|max:100',
        ]);

        $sessionId = $data['session_id'] ?? 'session_' . uniqid();
        $userMessage = trim($data['message']);

        ChatLog::create([
            'chatbot_id' => $chatbot->id,
            'session_id' => $sessionId,
            'message' => $userMessage,
            'role' => 'user',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $response = $this->findBestMatch($chatbot, $userMessage);

        ChatLog::create([
            'chatbot_id' => $chatbot->id,
            'session_id' => $sessionId,
            'message' => $response,
            'role' => 'bot',
        ]);

        return response()->json([
            'response' => $response,
            'session_id' => $sessionId,
        ])->header('Access-Control-Allow-Origin', '*');
    }

    private function findBestMatch($chatbot, $message)
    {
        $message = mb_strtolower($message);
        $knowledgeItems = $chatbot->knowledgeItems()->where('is_active', true)->get();

        if ($knowledgeItems->isEmpty()) {
            return $this->fallbackResponse();
        }

        $bestMatch = null;
        $bestScore = 0;
        $messageWords = array_filter(explode(' ', $message));
        $stopWords = ['nak', 'macam', 'mana', 'apa', 'itu', 'ini', 'yang', 'dan', 'atau', 'ke', 'kah', 'lah', 'pun', 'juga', 'saya', 'awak', 'the', 'is', 'are', 'a', 'an', 'to', 'for', 'of', 'in', 'on', 'how', 'do', 'does', 'can', 'i', 'me', 'my', 'what'];
        $messageWords = array_values(array_diff($messageWords, $stopWords));

        foreach ($knowledgeItems as $item) {
            $question = mb_strtolower($item->question);
            $score = 0;

            if ($message === $question) {
                return $item->answer;
            }

            if (str_contains($message, $question) || str_contains($question, $message)) {
                $score = 85;
            } else {
                $questionWords = array_filter(explode(' ', $question));
                $questionWords = array_values(array_diff($questionWords, $stopWords));
                $overlap = array_intersect($messageWords, $questionWords);
                if (count($questionWords) > 0) {
                    $score = count($overlap) / count($questionWords) * 60;
                    if (count($questionWords) <= 5) $score += 5;
                }
            }

            if ($item->tags) {
                $tags = array_map('trim', explode(',', mb_strtolower($item->tags)));
                foreach ($tags as $tag) {
                    if (str_contains($message, $tag)) {
                        $score += 25;
                    }
                }
            }

            $answerLen = mb_strlen($item->answer);
            if ($answerLen < 300) $score += 3;
            if ($answerLen < 150) $score += 2;

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $item;
            }
        }

        if ($bestMatch && $bestScore > 12) {
            return $bestMatch->answer;
        }

        return $this->fallbackResponse();
    }

    private function fallbackResponse()
    {
        $responses = [
            "Maaf, saya tak pasti tentang soalan itu. Boleh cuba tanya dengan cara lain atau lebih spesifik?",
            "Soalan yang bagus! Tapi saya perlukan lebih spesifik. Boleh cerita lebih lanjut?",
            "Saya nak bantu! Tapi boleh jelaskan lebih detail apa yang awak nak tahu?",
            "Hmm, saya tak jumpa jawapan tepat untuk soalan itu. Cuba tanya dengan perkataan berbeza?",
        ];
        return $responses[array_rand($responses)];
    }

    private function isOriginAllowed(Request $request, Chatbot $chatbot): bool
    {
        if (blank($chatbot->domain_whitelist)) {
            return true;
        }

        $origin = $request->header('Origin') ?? $request->header('Referer');
        $originHost = $origin ? strtolower((string) parse_url($origin, PHP_URL_HOST)) : '';

        if ($originHost === '') {
            return false;
        }

        foreach (explode(',', $chatbot->domain_whitelist) as $entry) {
            $entry = strtolower(trim($entry));
            if ($entry === '*') {
                return true;
            }

            $allowedHost = (string) parse_url(
                str_contains($entry, '://') ? $entry : "https://{$entry}",
                PHP_URL_HOST
            );

            if ($allowedHost !== '' &&
                ($originHost === $allowedHost || str_ends_with($originHost, ".{$allowedHost}"))) {
                return true;
            }
        }

        return false;
    }
}
