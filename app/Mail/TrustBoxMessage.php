<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Лист «Скриньки довіри». Містить лише тему, текст і час із точністю до години
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
        $sentAt = now('Europe/Kyiv');

        return new Content(
            view: 'mail.trust-box',
            text: 'mail.trust-box-text',
            with: [
                'topic' => $this->topic,
                'text' => $this->appeal,
                // Лише година: точний час зайве звужує коло можливих авторів.
                'sentAt' => $sentAt->format('d.m.Y').', близько '.$sentAt->format('H').':00 (Київ)',
                'language' => $this->pageLanguage === 'en' ? 'англійська' : 'українська',
                'test' => $this->test,
                'site' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'otfk.od.ua',
            ],
        );
    }
}
