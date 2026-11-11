<?php

namespace App\Services\TrustBox;

use App\Mail\TrustBoxMessage;
use Illuminate\Support\Facades\Mail;

/**
 * Надсилання листів «Скриньки довіри» через Gmail SMTP з паролем застосунку.
 * Mailer збирається на льоту (Mail::build) і не чіпає глобальні MAIL_* сайту.
 * Кожен отримувач отримує окремий лист, де в «Кому» лише він.
 * Тести підміняють клас через контейнер (реальний SMTP у тестах не викликається).
 */
class TrustBoxMailer
{
    public const HOST = 'smtp.gmail.com';

    public const PORT = 587;

    public const TIMEOUT = 15;

    /**
     * @param  list<string>  $recipients
     * @return array<string, ?string> адреса => null (надіслано) або текст помилки
     */
    public function send(string $sender, string $password, array $recipients, TrustBoxMessage $message): array
    {
        $results = [];
        $mailer = null;

        foreach ($recipients as $email) {
            try {
                $mailer ??= $this->mailer($sender, $password);
                $mailer->to($email)->send(clone $message);
                $results[$email] = null;
            } catch (\Throwable $e) {
                $results[$email] = static::describe($e);
            }
        }

        return $results;
    }

    protected function mailer(string $sender, string $password): \Illuminate\Contracts\Mail\Mailer
    {
        return Mail::build([
            'transport' => 'smtp',
            'scheme' => 'smtp', // 587 + STARTTLS
            'host' => self::HOST,
            'port' => self::PORT,
            'username' => $sender,
            'password' => $password,
            'timeout' => self::TIMEOUT,
            'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost',
        ]);
    }

    /** Зрозуміла адміністратору причина помилки SMTP (без тексту звернення). */
    public static function describe(\Throwable $e): string
    {
        $text = $e->getMessage();

        return match (true) {
            str_contains($text, '535') || str_contains($text, 'Username and Password not accepted') || str_contains($text, 'BadCredentials')
                => 'Gmail відхилив адресу або пароль застосунку. Перевірте, що пароль створено саме для цієї скриньки і двоетапна перевірка увімкнена.',
            str_contains($text, 'Connection could not be established') || str_contains($text, 'timed out') || str_contains($text, 'Connection refused')
                => 'Немає з\'єднання з smtp.gmail.com:587. Можливо, хостинг блокує вихідну пошту.',
            str_contains($text, '550') || str_contains($text, '553')
                => 'Gmail не прийняв адресу отримувача.',
            str_contains($text, '421') || str_contains($text, '454') || str_contains($text, 'Daily user sending limit')
                => 'Gmail тимчасово обмежив надсилання. Спробуйте пізніше.',
            default => 'Помилка SMTP: '.mb_substr(preg_replace('/\s+/', ' ', $text), 0, 200),
        };
    }

    /** Код SMTP для журналу (без тексту листа й адрес). */
    public static function code(\Throwable|string $error): string
    {
        $text = is_string($error) ? $error : $error->getMessage();

        return preg_match('/\b([45]\d\d)\b/', $text, $m) ? $m[1] : 'connection';
    }
}
