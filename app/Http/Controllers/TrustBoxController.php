<?php

namespace App\Http\Controllers;

use App\Mail\TrustBoxMessage;
use App\Models\TrustBoxStat;
use App\Services\TrustBox\TrustBox;
use App\Services\TrustBox\TrustBoxMailer;
use App\Services\TrustBox\Turnstile;
use App\Support\LocalizedUrl;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

/**
 * «Скринька довіри» (/skrynka-doviry, /en/skrynka-doviry): анонімне звернення
 * листом на адреси з налаштувань. Текст не пишеться в БД, логи, кеш чи сесію:
 * помилку форми показуємо одразу у відповіді на POST (без redirect+old input),
 * після успіху — Post/Redirect/Get із прапорцем у сесії.
 *
 * Захист: пастка website (тихий «успіх»), підписана мітка часу з одноразовим
 * nonce (швидше 3 с — тихий «успіх», старше 2 год — повторити), фільтр тексту,
 * Turnstile (fail-open), ліміти за HMAC(IP) без збереження IP і добовий ліміт
 * на всю скриньку, дублікат тексту за добу не надсилається повторно.
 */
class TrustBoxController extends Controller
{
    public const MIN_SECONDS = 3;

    public const MAX_SECONDS = 7200;

    /** [ключ, спроб, секунд] — лише успішні надсилання. */
    private const DEVICE_LIMITS = [['10m', 3, 600], ['day', 10, 86400]];

    public const GLOBAL_DAILY_LIMIT = 100;

    public function show(Request $request): Response
    {
        return $this->page($request, sent: (bool) $request->session()->get('trust_box_sent'));
    }

    public function send(Request $request, TrustBoxMailer $mailer, Turnstile $turnstile): Response|RedirectResponse
    {
        if (! TrustBox::available()) {
            return $this->toPage();
        }

        $input = [
            'topic' => $this->string($request->input('topic'), 40),
            'message' => $this->cleanText($request->input('message')),
            'consent' => $request->boolean('consent'),
        ];
        $token = $this->string($request->input('issued'), 1000);

        // Пастка: людина поле не бачить, тож будь-яке значення — бот.
        if ($this->string($request->input('website'), 500) !== '') {
            return $this->silentlyReject();
        }

        $issued = $this->readToken($token);
        if ($issued === null || now()->timestamp - $issued['t'] > self::MAX_SECONDS) {
            return $this->page($request, input: $input, errors: ['form' => __('trust_box.errors.expired')], status: 422);
        }
        if (now()->timestamp - $issued['t'] < self::MIN_SECONDS) {
            return $this->silentlyReject();
        }
        $nonceKey = 'trust-box:nonce:'.$issued['n'];
        if (Cache::has($nonceKey)) {
            // Повторне надсилання тієї самої форми (подвійний клік, «Назад» + «Надіслати»).
            return $this->succeeded();
        }

        $errors = $this->validate($input);
        if ($errors !== [] && isset($errors['spam'])) {
            TrustBoxStat::bump('rejected');
        }
        if ($errors !== []) {
            return $this->page($request, input: $input, token: $token, errors: $errors, status: 422);
        }

        if (TrustBox::turnstileEnabled()) {
            $verdict = $turnstile->verify((string) TrustBox::turnstileSecret(), $this->string($request->input('cf-turnstile-response'), 2048), $request->getHost());
            if ($verdict === Turnstile::FAILED) {
                TrustBoxStat::bump('rejected');

                return $this->page($request, input: $input, errors: ['captcha' => __('trust_box.errors.captcha')], status: 422);
            }
            if ($verdict === Turnstile::UNAVAILABLE) {
                TrustBoxStat::bump('captcha_unavailable');
                Log::channel('security')->warning('trust_box.turnstile_unavailable');
            }
        }

        $device = TrustBox::fingerprint((string) $request->ip());
        foreach (self::DEVICE_LIMITS as [$name, $max]) {
            if (RateLimiter::tooManyAttempts("trust-box:{$name}:{$device}", $max)) {
                return $this->tooMany($request, $input, $token, RateLimiter::availableIn("trust-box:{$name}:{$device}"));
            }
        }
        $globalKey = 'trust-box:global:'.now('Europe/Kyiv')->toDateString();
        if (RateLimiter::tooManyAttempts($globalKey, self::GLOBAL_DAILY_LIMIT)) {
            return $this->tooMany($request, $input, $token, RateLimiter::availableIn($globalKey));
        }

        // Той самий текст за добу вдруге не надсилаємо (у кеші — лише HMAC, не текст).
        $duplicateKey = 'trust-box:text:'.TrustBox::fingerprint(mb_strtolower(preg_replace('/\s+/u', ' ', $input['message'])));
        if (Cache::has($duplicateKey)) {
            return $this->silentlyReject();
        }

        if (! Cache::add($nonceKey, true, self::MAX_SECONDS + 600)) {
            return $this->succeeded();
        }

        @set_time_limit(120);
        $topic = $input['topic'] !== '' ? __('trust_box.topics.'.$input['topic'], [], 'uk') : null;
        $results = $mailer->send(
            (string) TrustBox::sender(),
            (string) TrustBox::password(),
            array_column(TrustBox::recipients(), 'email'),
            new TrustBoxMessage((string) TrustBox::sender(), $input['message'], $topic, Seo::isEnglish($request) ? 'en' : 'uk'),
        );

        $failed = array_filter($results);
        foreach ($failed as $email => $error) {
            Log::channel('security')->error('trust_box.send_failed', ['recipient' => $email, 'code' => TrustBoxMailer::code($error)]);
        }
        if ($failed !== []) {
            TrustBoxStat::bump('failed', count($failed));
        }

        if (count($failed) === count($results)) {
            Cache::forget($nonceKey); // та сама форма може спробувати ще раз

            return $this->page($request, input: $input, token: $token, errors: ['form' => __('trust_box.errors.send_failed')], status: 503);
        }

        TrustBoxStat::bump('sent');
        foreach (self::DEVICE_LIMITS as [$name, , $seconds]) {
            RateLimiter::hit("trust-box:{$name}:{$device}", $seconds);
        }
        RateLimiter::hit($globalKey, 86400);
        Cache::put($duplicateKey, true, 86400);

        return $this->succeeded();
    }

    /** @return array<string, string> */
    private function validate(array $input): array
    {
        $errors = [];
        $text = $input['message'];
        $length = mb_strlen($text);

        if ($input['topic'] !== '' && ! in_array($input['topic'], TrustBox::TOPICS, true)) {
            $errors['topic'] = __('trust_box.errors.topic');
        }

        if ($length === 0) {
            $errors['message'] = __('trust_box.errors.message_required');
        } elseif ($length < TrustBox::MIN_LENGTH) {
            $errors['message'] = __('trust_box.errors.message_short', ['min' => TrustBox::MIN_LENGTH]);
        } elseif ($length > TrustBox::MAX_LENGTH) {
            $errors['message'] = __('trust_box.errors.message_long', ['max' => TrustBox::MAX_LENGTH]);
        } elseif ($this->looksLikeSpam($text)) {
            $errors['message'] = __('trust_box.errors.spam');
            $errors['spam'] = '1';
        }

        if (! $input['consent']) {
            $errors['consent'] = __('trust_box.errors.consent');
        }

        return $errors;
    }

    private function looksLikeSpam(string $text): bool
    {
        $urls = preg_match_all('~(?:https?://|www\.)\S+~iu', $text);
        $withoutUrls = trim(preg_replace('~(?:https?://|www\.)\S+~iu', ' ', $text));

        return $urls > 3
            || ($urls > 0 && mb_strlen(preg_replace('/\s+/u', '', $withoutUrls)) < TrustBox::MIN_LENGTH)
            || preg_match('/(.)\1{19,}/u', $text) === 1          // 20 однакових символів поспіль
            || preg_match('/\b(\w{2,})(?:\W+\1\b){9,}/iu', $text) === 1; // слово 10 разів поспіль
    }

    private function page(Request $request, array $input = [], ?string $token = null, array $errors = [], int $status = 200, bool $sent = false): Response
    {
        $bag = new ViewErrorBag;
        $bag->put('default', new MessageBag(array_diff_key($errors, ['spam' => true])));

        $response = response()->view('trust-box.index', [
            'available' => TrustBox::available(),
            'sent' => $sent,
            'input' => $input + ['topic' => '', 'message' => '', 'consent' => false],
            'token' => $token !== null && $this->readToken($token) !== null ? $token : $this->issueToken(),
            'errors' => $bag,
            'turnstileKey' => TrustBox::turnstileEnabled() ? TrustBox::turnstileSiteKey() : null,
        ], $status);

        return $this->private($response);
    }

    private function tooMany(Request $request, array $input, string $token, int $seconds): Response
    {
        return $this->page($request, input: $input, token: $token,
            errors: ['form' => __('trust_box.errors.too_many', ['minutes' => max(1, (int) ceil($seconds / 60))])], status: 429);
    }

    private function silentlyReject(): RedirectResponse
    {
        TrustBoxStat::bump('rejected');

        return $this->succeeded();
    }

    private function succeeded(): RedirectResponse
    {
        return $this->private($this->toPage()->with('trust_box_sent', true));
    }

    private function toPage(): RedirectResponse
    {
        return redirect()->to(LocalizedUrl::route('trust-box'), 303);
    }

    /** Без кешу й Referer: сторінку не зберігають проксі, а зовнішні сайти не бачать, звідки прийшли. */
    private function private(Response|RedirectResponse $response): Response|RedirectResponse
    {
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    private function issueToken(): string
    {
        return Crypt::encryptString(json_encode(['t' => now()->timestamp, 'n' => bin2hex(random_bytes(16))]));
    }

    /** @return array{t: int, n: string}|null */
    private function readToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        try {
            $data = json_decode(Crypt::decryptString($token), true);
        } catch (\Throwable) {
            return null;
        }

        return is_array($data) && is_int($data['t'] ?? null) && is_string($data['n'] ?? null) && preg_match('/^[0-9a-f]{32}$/', $data['n'])
            ? $data : null;
    }

    private function string(mixed $value, int $max): string
    {
        return is_string($value) ? mb_substr(trim($value), 0, $max) : '';
    }

    /** Текст звернення: лише рядок, коректний UTF-8, без керівних символів (крім переносів і табуляції). */
    private function cleanText(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }
        $text = mb_scrub(str_replace(["\r\n", "\r"], "\n", $value), 'UTF-8');
        $text = preg_replace('/[^\P{C}\n\t]/u', '', $text) ?? '';
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? '';

        return trim(mb_substr($text, 0, TrustBox::MAX_LENGTH + 1000));
    }
}
