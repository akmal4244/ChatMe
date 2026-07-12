<?php

namespace App\Services;

use App\Exceptions\InvalidWidgetTicketException;
use App\Models\Chatbot;
use App\ValueObjects\WidgetTicketClaims;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use JsonException;

class WidgetTicketService
{
    /** @return array{ticket: string, session_id: string, expires_at: string} */
    public function issue(Request $request, Chatbot $chatbot, string $origin): array
    {
        $expiresAt = now()->addSeconds($this->ttlSeconds());
        $sessionId = (string) Str::uuid();
        $payload = json_encode([
            'version' => 1,
            'chatbot_id' => $chatbot->id,
            'origin' => $origin,
            'session_id' => $sessionId,
            'ip_hash' => $this->ipHash($request),
            'expires_at' => $expiresAt->getTimestamp(),
        ], JSON_THROW_ON_ERROR);

        return [
            'ticket' => Crypt::encryptString($payload),
            'session_id' => $sessionId,
            'expires_at' => $expiresAt->toISOString(),
        ];
    }

    public function validate(
        Request $request,
        Chatbot $chatbot,
        string $origin,
        string $ticket,
        string $sessionId,
    ): WidgetTicketClaims {
        try {
            $payload = json_decode(Crypt::decryptString($ticket), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            throw new InvalidWidgetTicketException('decrypt_failed');
        }

        if (! is_array($payload) || ($payload['version'] ?? null) !== 1) {
            throw new InvalidWidgetTicketException('version_mismatch');
        }

        $checks = [
            'chatbot_mismatch' => (int) ($payload['chatbot_id'] ?? 0) === $chatbot->id,
            'origin_mismatch' => is_string($payload['origin'] ?? null)
                && hash_equals($payload['origin'], $origin),
            'session_mismatch' => is_string($payload['session_id'] ?? null)
                && hash_equals($payload['session_id'], $sessionId),
            'ip_mismatch' => is_string($payload['ip_hash'] ?? null)
                && hash_equals($payload['ip_hash'], $this->ipHash($request)),
            'expired' => is_int($payload['expires_at'] ?? null)
                && $payload['expires_at'] > now()->getTimestamp(),
        ];

        foreach ($checks as $reason => $valid) {
            if (! $valid) {
                throw new InvalidWidgetTicketException($reason);
            }
        }

        return new WidgetTicketClaims(
            sessionId: $sessionId,
            fingerprint: hash('sha256', $ticket),
        );
    }

    private function ttlSeconds(): int
    {
        return max(60, min(900, (int) config('chatme.widget.ticket_ttl_seconds', 600)));
    }

    private function ipHash(Request $request): string
    {
        return hash_hmac(
            'sha256',
            (string) ($request->ip() ?: 'unknown'),
            (string) config('app.key'),
        );
    }
}
