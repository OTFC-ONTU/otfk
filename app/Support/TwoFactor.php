<?php

namespace App\Support;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP (RFC 6238) для адмінки: сумісно з Google Authenticator, Aegis, Authy.
 *
 * Життєвий цикл: pending-секрет генерується на сторінці підключення й стає
 * активним лише після першого правильного коду (інакше користувач не може
 * заблокувати себе недопідключеним застосунком); разом з підтвердженням
 * видаються одноразові коди відновлення. Прийнятий код запам'ятовується за
 * часовим кроком (two_factor_last_used), тому той самий код удруге не
 * проходить. Скинути фактор може адміністратор (UserResource), консольна
 * команда otfk:two-factor або аварійний вимикач TWO_FACTOR_ENFORCE.
 */
class TwoFactor
{
    public const SESSION_KEY = 'two_factor.passed_user';

    private Google2FA $engine;

    public function __construct()
    {
        $this->engine = new Google2FA;
    }

    public static function enforced(): bool
    {
        return (bool) config('otfk.two_factor.enforce', true);
    }

    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey(32);
    }

    public function qrCodeSvg(User $user, string $secret): string
    {
        $url = $this->engine->getQRCodeUrl((string) config('otfk.two_factor.issuer'), (string) $user->email, $secret);

        $svg = (new Writer(new ImageRenderer(new RendererStyle(220, 1), new SvgImageBackEnd)))->writeString($url);

        return (string) preg_replace('/^<\?xml[^>]*>\s*/', '', $svg);
    }

    /**
     * Перевірити одноразовий код проти секрету; повертає часовий крок
     * прийнятого коду або null. Крок має бути новішим за останній прийнятий.
     */
    public function verifyCode(string $secret, string $code, ?int $lastUsed): ?int
    {
        $code = preg_replace('/\D+/', '', $code) ?? '';
        if (strlen($code) !== 6) {
            return null;
        }

        // Без «старого» кроку бібліотека повертає bool замість кроку — передаємо 0,
        // щоб завжди отримати номер кроку і зберегти його для захисту від повтору.
        $timestamp = $this->engine->verifyKeyNewer($secret, $code, $lastUsed ?? 0, 1);

        return $timestamp === false ? null : (int) $timestamp;
    }

    /** @return list<string> */
    public function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0, $n = (int) config('otfk.two_factor.recovery_codes', 10); $i < $n; $i++) {
            $codes[] = strtoupper(Str::random(5).'-'.Str::random(5));
        }

        return $codes;
    }

    /** Підтвердити код під час входу: TOTP або код відновлення (одноразовий). */
    public function challenge(User $user, string $input): bool
    {
        $input = trim($input);

        if ($user->two_factor_secret && ($step = $this->verifyCode($user->two_factor_secret, $input, $user->two_factor_last_used)) !== null) {
            $user->forceFill(['two_factor_last_used' => $step])->saveQuietly();
            $this->pass($user, 'totp');

            return true;
        }

        $codes = $user->two_factor_recovery_codes ?? [];
        $normalized = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $input) ?? '');
        foreach ($codes as $index => $code) {
            if ($normalized !== '' && hash_equals(str_replace('-', '', $code), $normalized)) {
                unset($codes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->saveQuietly();
                $this->pass($user, 'recovery');
                Log::channel('security')->warning('2fa.recovery_used', $this->context($user) + ['remaining' => count($codes)]);

                return true;
            }
        }

        Log::channel('security')->warning('2fa.failed', $this->context($user));

        return false;
    }

    /** Увімкнути фактор після першого правильного коду; повертає коди відновлення. */
    public function confirm(User $user, string $secret, string $code): ?array
    {
        $step = $this->verifyCode($secret, $code, null);
        if ($step === null) {
            return null;
        }

        $codes = $this->generateRecoveryCodes();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => now(),
            'two_factor_last_used' => $step,
        ])->saveQuietly();

        $this->pass($user, 'enrolled');
        Log::channel('security')->info('2fa.enabled', $this->context($user));

        return $codes;
    }

    /** Скинути фактор (адміністратор або консоль): користувач підключить застосунок знову при наступному вході. */
    public function reset(User $user, string $by): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_used' => null,
        ])->saveQuietly();

        Log::channel('security')->warning('2fa.reset', $this->context($user) + ['by' => $by]);
    }

    public function passed(User $user): bool
    {
        return session(self::SESSION_KEY) === $user->getKey();
    }

    public function pass(User $user, string $method): void
    {
        session([self::SESSION_KEY => $user->getKey()]);
        Log::channel('security')->info('2fa.passed', $this->context($user) + ['method' => $method]);
    }

    /** @return array<string, mixed> */
    private function context(User $user): array
    {
        return [
            'ip' => request()?->ip(),
            'user_agent' => mb_substr((string) request()?->userAgent(), 0, 255),
            'email' => $user->email,
            'user_id' => $user->getKey(),
        ];
    }
}
