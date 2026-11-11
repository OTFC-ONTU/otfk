<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Лист «Скриньки довіри». Містить лише тему, текст і час надсилання
 * (Київ): без IP, браузера, Reply-To. Службовий лист українською для всіх версій сайту.
 */
class TrustBoxMessage extends Mailable
{
    use Queueable;

    public function __construct(
        public string $senderEmail,
        public string $appeal,
        public ?string $topic = null,
        public string $pageLanguage = 'uk',
        public bool $test = false,
    ) {
        $this->locale('uk');
    }

    public function envelope(): Envelope
    {
        $subject = $this->test
            ? 'Скринька довіри: тестовий лист'
            : 'Скринька довіри: нове звернення'.($this->topic ? ' — '.$this->topic : '');

        return new Envelope(
            from: new Address($this->senderEmail, 'Скринька довіри ОТФК'),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        // Точний час за Києвом: лист іде одразу після надсилання форми, тож поштовий
        // клієнт однаково показує цей час у заголовку Date — приховувати його немає сенсу.
        $sentAt = now('Europe/Kyiv');

        return new Content(
            view: 'mail.trust-box',
            text: 'mail.trust-box-text',
            with: [
                'topic' => $this->topic,
                'text' => $this->appeal,
                'sentAt' => $sentAt->format('d.m.Y H:i').' (Київ)',
                'language' => $this->pageLanguage === 'en' ? 'англійська' : 'українська',
                'test' => $this->test,
                'site' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'otfk.od.ua',
            ],
        );
    }
}
