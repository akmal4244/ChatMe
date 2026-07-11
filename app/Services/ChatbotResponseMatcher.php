<?php

namespace App\Services;

use App\Models\Chatbot;

class ChatbotResponseMatcher
{
    public function respond(Chatbot $chatbot, string $message): string
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
                    if (count($questionWords) <= 5) {
                        $score += 5;
                    }
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
            if ($answerLen < 300) {
                $score += 3;
            }
            if ($answerLen < 150) {
                $score += 2;
            }

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

    private function fallbackResponse(): string
    {
        $responses = [
            'Maaf, saya belum pasti jawapannya. Cuba tanya dengan cara lain atau berikan maklumat yang lebih khusus.',
            'Soalan yang bagus! Boleh berikan sedikit lagi maklumat supaya saya dapat membantu?',
            'Saya sedia membantu. Boleh jelaskan dengan lebih lanjut perkara yang anda ingin tahu?',
            'Maaf, saya belum menemui jawapan yang tepat. Cuba gunakan perkataan lain.',
        ];

        return $responses[array_rand($responses)];
    }
}
