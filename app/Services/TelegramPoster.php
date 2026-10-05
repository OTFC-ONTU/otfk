<?php

namespace App\Services;

use App\Models\News;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelegramPoster
{
    /**
     * Чи увімкнено автопостинг і чи заповнені токен та канал.
     */
    public static function enabled(): bool
    {
        return Setting::get('telegram_autopost') === '1'
            && trim((string) Setting::get('telegram_bot_token')) !== ''
            && trim((string) Setting::get('telegram_channel')) !== '';
    }

    /**
     * Публікує новину в Telegram-канал коледжу (якщо автопостинг увімкнено).
     * Повертає true при успішній відправці.
     */
    public static function post(News $news): bool
    {
        if (! static::enabled()) {
            return false;
        }

        $token = trim((string) Setting::get('telegram_bot_token'));
        $channel = trim((string) Setting::get('telegram_channel'));

        $url = route('news.show', $news);
        $excerpt = filled($news->excerpt) ? Str::limit(strip_tags($news->excerpt), 200) : '';

        // HTML-розмітка Telegram: <b>назва</b> + анотація + посилання
        $caption = '<b>'.e($news->title).'</b>'
            .($excerpt !== '' ? "\n\n".e($excerpt) : '')
            ."\n\n".'<a href="'.$url.'">Читати на сайті →</a>';

        try {
            if ($news->cover_image) {
                $resp = Http::timeout(20)->post("https://api.telegram.org/bot{$token}/sendPhoto", [
                    'chat_id' => $channel,
                    'photo' => asset('storage/'.$news->cover_image),
                    'caption' => $caption,
                    'parse_mode' => 'HTML',
                ]);
            } else {
                $resp = Http::timeout(20)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $channel,
                    'text' => $caption,
                    'parse_mode' => 'HTML',
                ]);
            }

            $ok = $resp->successful() && ($resp->json('ok') === true);

            // Позначку telegram_posted_at вже виставлено до відправки (захист від дублів),
            // тож тиху невдачу логуємо — інакше новина «вважається опублікованою» дарма.
            if (! $ok) {
                Log::warning('Не вдалося запостити новину в Telegram', [
                    'news_id' => $news->id,
                    'status' => $resp->status(),
                    'response' => $resp->json(),
                ]);
            }

            return $ok;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * Тестове повідомлення з довільних (навіть незбережених) токена й каналу.
     * Повертає null при успіху або текст помилки від Telegram.
     */
    public static function sendTest(string $token, string $channel): ?string
    {
        try {
            $resp = Http::timeout(20)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $channel,
                'text' => 'Тестове повідомлення з сайту коледжу — налаштування Telegram працюють.',
            ]);

            if ($resp->successful() && $resp->json('ok') === true) {
                return null;
            }

            return (string) ($resp->json('description') ?? ('HTTP '.$resp->status()));
        } catch (\Throwable $e) {
            report($e);

            return $e->getMessage();
        }
    }
}
