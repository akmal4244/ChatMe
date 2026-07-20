<?php

namespace App\Services;

use App\Models\Chatbot;
use Illuminate\Http\Request;

class WidgetOriginService
{
    public function fromRequest(Request $request): ?string
    {
        $value = $request->header('Origin');
        if (! is_string($value) || trim($value) === '' || trim($value) === 'null') {
            $value = $request->header('Referer');
        }

        if (! is_string($value)) {
            return null;
        }

        $parts = parse_url(trim($value));
        if (! is_array($parts)
            || ! isset($parts['scheme'], $parts['host'])
            || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || isset($parts['user'])
            || isset($parts['pass'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        $host = rtrim(strtolower($parts['host']), '.');
        if ($host === '') {
            return null;
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        $includePort = $port !== null
            && ! (($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80));

        return $scheme.'://'.$host.($includePort ? ':'.$port : '');
    }

    public function isAllowed(Chatbot $chatbot, ?string $origin): bool
    {
        if ($origin === null) {
            return false;
        }

        $originHost = parse_url($origin, PHP_URL_HOST);
        if (! is_string($originHost) || $originHost === '') {
            return false;
        }

        // Nota keselamatan: apabila senarai putih domain KOSONG, widget dibenarkan
        // pada semua origin (backward-compat — kebanyakan chatbot sedia ada tidak
        // menetapkan domain_whitelist). Untuk mengetatkan origin, isi
        // domain_whitelist dengan domain yang dibenarkan (cth "example.com").
        // JANGAN tukar default ini kepada "false" tanpa memastikan setiap chatbot
        // sedia ada mempunyai domain_whitelist — ia akan mematikan widget mereka.
        if (blank($chatbot->domain_whitelist)) {
            return true;
        }

        foreach (explode(',', (string) $chatbot->domain_whitelist) as $entry) {
            $entry = strtolower(trim($entry));
            if ($entry === '*') {
                return true;
            }

            $allowedHost = parse_url(
                str_contains($entry, '://') ? $entry : 'https://'.$entry,
                PHP_URL_HOST,
            );
            if (! is_string($allowedHost) || $allowedHost === '') {
                continue;
            }

            $allowedHost = rtrim(strtolower($allowedHost), '.');
            if ($originHost === $allowedHost || str_ends_with($originHost, '.'.$allowedHost)) {
                return true;
            }
        }

        return false;
    }
}
