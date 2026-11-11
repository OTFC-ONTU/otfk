<?php

namespace App\Services\TrustBox;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * «Скринька довіри» (/skrynka-doviry): анонімні звернення студентів, які
 * надсилаються листом зі службової скриньки Gmail на одну або кілька адрес
 * з налаштувань (на сайті завжди пишемо «директор»).
 *
 * Пароль застосунку Gmail і секретний ключ Turnstile зберігаються в settings
 * зашифрованими (Crypt, APP_KEY), у форму не повертаються і приховані з
 * «Розширених налаштувань». Скриньку можна ввімкнути лише після успішного
 * тестового листа з тими самими налаштуваннями (verifiedHash).
 */
class TrustBox
{
    public const ENABLED = 'trust_box_enabled';

    public const SENDER = 'trust_box_sender_email';

    public const PASSWORD = 'trust_box_app_password';

    public const RECIPIENTS = 'trust_box_recipients';

    public const TURNSTILE_SITE_KEY = 'trust_box_turnstile_site_key';

    public const TURNSTILE_SECRET = 'trust_box_turnstile_secret';

    public const VERIFIED_HASH = 'trust_box_verified_hash';

    public const VERIFIED_AT = 'trust_box_verified_at';

    /** Ключі, які не можна показувати чи редагувати як сирі рядки. */
    public const SECRET_KEYS = [self::PASSWORD, self::TURNSTILE_SECRET, self::VERIFIED_HASH];

    public const MAX_RECIPIENTS = 5;

    public const MIN_LENGTH = 20;

    public const MAX_LENGTH = 5000;

    /** Теми звернення: ключ => ключ перекладу (public.trust_box.topics.*). */
    public const TOPICS = ['education', 'staff', 'bullying', 'corruption', 'conditions', 'other'];

    public static function enabled(): bool
    {
        return Setting::get(self::ENABLED) === '1';
    }

    /** Скринька працює на сайті: увімкнена, налаштована і саме ці налаштування пройшли тест. */
    public static function available(): bool
    {
        return static::enabled() && static::configured() && static::verified();
    }

    public static function configured(): bool
    {
        return static::sender() !== null && static::password() !== null && static::recipients() !== [];
    }

    public static function sender(): ?string
    {
        $email = trim((string) Setting::get(self::SENDER));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    public static function password(): ?string
    {
        return static::decrypt(self::PASSWORD);
    }

    public static function hasPassword(): bool
    {
        return static::password() !== null;
    }

    public static function hasUnreadablePassword(): bool
    {
        return filled(Setting::get(self::PASSWORD)) && static::password() === null;
    }

    /**
     * Отримувачі з налаштувань (JSON [{email, label}]), лише коректні адреси без дублів.
     *
     * @return list<array{email: string, label: string}>
     */
    public static function recipients(?string $json = null): array
    {
        $items = json_decode($json ?? (string) Setting::get(self::RECIPIENTS), true);

        return static::normalizeRecipients(is_array($items) ? $items : []);
    }

    /**
     * @param  array<mixed>  $items
     * @return list<array{email: string, label: string}>
     */
    public static function normalizeRecipients(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            $email = mb_strtolower(trim((string) (is_array($item) ? ($item['email'] ?? '') : $item)));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL) || isset($result[$email])) {
                continue;
            }
            $result[$email] = ['email' => $email, 'label' => trim((string) (is_array($item) ? ($item['label'] ?? '') : ''))];
        }

        return array_slice(array_values($result), 0, self::MAX_RECIPIENTS);
    }

    public static function turnstileSiteKey(): ?string
    {
        $key = trim((string) Setting::get(self::TURNSTILE_SITE_KEY));

        return $key !== '' ? $key : null;
    }

    public static function turnstileSecret(): ?string
    {
        return static::decrypt(self::TURNSTILE_SECRET);
    }

    /** Turnstile працює, лише коли задано обидва ключі. */
    public static function turnstileEnabled(): bool
    {
        return static::turnstileSiteKey() !== null && static::turnstileSecret() !== null;
    }

    /** Пароль застосунку Gmail: 16 латинських літер, пробіли ігноруються. */
    public static function normalizePassword(?string $password): string
    {
        return strtolower(preg_replace('/\s+/', '', (string) $password));
    }

    public static function isValidPassword(string $password): bool
    {
        return preg_match('/^[a-z]{16}$/', static::normalizePassword($password)) === 1;
    }

    public static function storeSecret(string $key, ?string $value): void
    {
        $value = trim((string) $value);
        $setting = Setting::firstOrNew(['key' => $key]);
        if (! $setting->exists) {
            $setting->group = 'trust_box';
            $setting->type = 'text';
        }
        $setting->value = $value === '' ? '' : Crypt::encryptString($value);
        $setting->save();
    }

    public static function storeValue(string $key, string $value): void
    {
        $setting = Setting::firstOrNew(['key' => $key]);
        if (! $setting->exists) {
            $setting->group = 'trust_box';
            $setting->type = 'text';
        }
        $setting->value = $value;
        $setting->save();
    }

    /**
     * Відбиток налаштувань надсилання (відправник, пароль, отримувачі): тест
     * зараховується лише для того самого набору.
     *
     * @param  list<array{email: string, label: string}>  $recipients
     */
    public static function configHash(?string $sender, ?string $password, array $recipients): string
    {
        $emails = array_column($recipients, 'email');
        sort($emails);

        return hash_hmac('sha256', json_encode([mb_strtolower(trim((string) $sender)), (string) $password, $emails]), (string) config('app.key'));
    }

    public static function currentConfigHash(): string
    {
        return static::configHash(static::sender(), static::password(), static::recipients());
    }

    public static function verified(): bool
    {
        $stored = (string) Setting::get(self::VERIFIED_HASH);

        return $stored !== '' && hash_equals($stored, static::currentConfigHash());
    }

    public static function markVerified(string $hash): void
    {
        static::storeValue(self::VERIFIED_HASH, $hash);
        static::storeValue(self::VERIFIED_AT, now()->toIso8601String());
    }

    public static function verifiedAt(): ?\Carbon\CarbonInterface
    {
        $at = Setting::get(self::VERIFIED_AT);

        return filled($at) ? \Illuminate\Support\Carbon::parse($at) : null;
    }

    /** HMAC від значення з APP_KEY: для лімітів і дублікатів без збереження IP чи тексту. */
    public static function fingerprint(string $value): string
    {
        return hash_hmac('sha256', $value, 'trust-box|'.config('app.key'));
    }

    private static function decrypt(string $key): ?string
    {
        $stored = Setting::get($key);
        if (blank($stored)) {
            return null;
        }

        try {
            $value = trim(Crypt::decryptString($stored));
        } catch (DecryptException) {
            return null;
        }

        return $value !== '' ? $value : null;
    }
}
