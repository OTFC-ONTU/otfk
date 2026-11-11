<?php

namespace App\Services\TrustBox;

use Illuminate\Support\Facades\Http;

/**
 * Перевірка токена Cloudflare Turnstile для «Скриньки довіри».
 * IP відвідувача (remoteip) свідомо не передається. Якщо Cloudflare недоступний
 * (таймаут, мережа, 5xx), перевірка пропускається (fail-open): лишаються пастка,
 * мітка часу, ліміти й фільтр тексту. Явна відмова (success: false) — відхиляє.
 */
class Turnstile
{
    public const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public const SCRIPT_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js';

    public const OK = 'ok';

    public const FAILED = 'failed';

    public const UNAVAILABLE = 'unavailable';

    public function verify(string $secret, ?string $token, string $host): string
    {
        $token = trim((string) $token);
        if ($token === '' || strlen($token) > 2048) {
            return self::FAILED;
        }

        try {
            $response = Http::timeout(5)->connectTimeout(3)->asForm()->post(self::VERIFY_URL, [
                'secret' => $secret,
                'response' => $token,
            ]);
        } catch (\Throwable) {
            return self::UNAVAILABLE;
        }

        if ($response->serverError()) {
            return self::UNAVAILABLE;
        }

        $data = $response->json();
        if (! is_array($data) || ! array_key_exists('success', $data)) {
            return self::UNAVAILABLE;
        }

        if ($data['success'] !== true) {
            return self::FAILED;
        }

        // Токен, виданий для іншого сайту, не приймаємо (www ігнорується).
        $issued = strtolower(preg_replace('/^www\./', '', (string) ($data['hostname'] ?? '')));
        $expected = strtolower(preg_replace('/^www\./', '', $host));

        return $issued === '' || $issued === $expected ? self::OK : self::FAILED;
    }
}
