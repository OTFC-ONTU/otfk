<?php

namespace Tests\Feature;

use App\Filament\Pages\TrustBoxSettings;
use App\Filament\Resources\SettingResource;
use App\Mail\TrustBoxMessage;
use App\Models\Setting;
use App\Models\TrustBoxStat;
use App\Models\User;
use App\Services\TrustBox\TrustBox;
use App\Services\TrustBox\TrustBoxMailer;
use App\Services\TrustBox\Turnstile;
use App\Support\Analytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * «Скринька довіри» (/skrynka-doviry): анонімне звернення листом на кілька адрес,
 * анонімність (текст ніде не зберігається, у листі немає IP/браузера), захист від
 * спаму (пастка, мітка часу, одноразовий токен, ліміти, фільтр, Turnstile fail-open),
 * сторінка налаштувань лише для адміністратора з зашифрованим паролем.
 */
class TrustBoxTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'abcdefghijklmnop';

    private const TEXT = 'Викладач регулярно затримує групу після дзвінка на двадцять хвилин, ми запізнюємося.';

    /** @var object{sent: array, failFor: array} */
    private object $mailer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailer = new class extends TrustBoxMailer
        {
            public array $sent = [];

            public array $failFor = [];

            public function send(string $sender, string $password, array $recipients, TrustBoxMessage $message): array
            {
                $results = [];
                foreach ($recipients as $email) {
                    if (in_array($email, $this->failFor, true)) {
                        $results[$email] = 'Gmail не прийняв адресу отримувача.';

                        continue;
                    }
                    $this->sent[] = ['to' => $email, 'sender' => $sender, 'password' => $password, 'message' => $message];
                    $results[$email] = null;
                }

                return $results;
            }
        };
        $this->app->instance(TrustBoxMailer::class, $this->mailer);
        Http::preventStrayRequests();
    }

    private function configure(array $recipients = ['director@example.com', 'psycholog@example.com'], bool $enabled = true): void
    {
        TrustBox::storeValue(TrustBox::SENDER, 'skrynka.otfk@gmail.com');
        TrustBox::storeSecret(TrustBox::PASSWORD, self::PASSWORD);
        TrustBox::storeValue(TrustBox::RECIPIENTS, json_encode(array_map(fn ($e) => ['email' => $e, 'label' => ''], $recipients)));
        TrustBox::markVerified(TrustBox::currentConfigHash());
        TrustBox::storeValue(TrustBox::ENABLED, $enabled ? '1' : '0');
    }

    /** Токен форми зі сторінки; час «пересувається», щоб людина встигла написати. */
    private function token(string $url = '/skrynka-doviry'): string
    {
        $html = $this->get($url)->assertOk()->getContent();
        if (! str_contains($html, 'name="issued"')) {
            $html = $this->get($url)->assertOk()->getContent(); // перший показ після успіху — екран «надіслано»
        }
        $this->assertSame(1, preg_match('/name="issued" value="([^"]+)"/', $html, $m));
        $this->travel(30)->seconds();

        return html_entity_decode($m[1]);
    }

    private function submit(array $overrides = [], ?string $token = null, string $url = '/skrynka-doviry')
    {
        return $this->post($url, array_merge([
            'issued' => $token ?? $this->token($url),
            'topic' => 'education',
            'message' => self::TEXT,
            'consent' => '1',
            'website' => '',
        ], $overrides), ['REMOTE_ADDR' => '203.0.113.7', 'HTTP_USER_AGENT' => 'UniqueAgent/9.9']);
    }

    public function test_hidden_and_unavailable_until_configured_and_verified(): void
    {
        $home = $this->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('skrynka-doviry', $home);
        $this->get('/skrynka-doviry')->assertOk()->assertSee('Скринька тимчасово недоступна')->assertDontSee('name="message"', false);
        $this->post('/skrynka-doviry', ['message' => self::TEXT, 'consent' => '1'])->assertRedirect();
        $this->assertSame([], $this->mailer->sent);

        // Налаштовано, але вимкнено — так само приховано.
        $this->configure(enabled: false);
        $this->assertStringNotContainsString('skrynka-doviry', $this->get('/')->getContent());

        // Зміна отримувачів після тесту знімає перевірку — скринька зникає з сайту.
        $this->configure();
        $this->assertTrue(TrustBox::available());
        TrustBox::storeValue(TrustBox::RECIPIENTS, json_encode([['email' => 'other@example.com', 'label' => '']]));
        $this->assertFalse(TrustBox::available());
    }

    public function test_links_page_and_headers_in_both_locales(): void
    {
        $this->configure();

        $home = $this->get('/')->assertOk()->getContent();
        $this->assertGreaterThanOrEqual(3, substr_count($home, 'href="'.url('/skrynka-doviry').'"'), 'Шапка, мобільне меню й підвал');
        $this->assertStringContainsString('href="'.url('/en/skrynka-doviry').'"', $this->get('/en')->getContent());

        $response = $this->get('/skrynka-doviry')->assertOk()
            ->assertSee('Скринька довіри')->assertSee('Лист отримує тільки директор')
            ->assertSee('name="website"', false)
            ->assertDontSee('director@example.com')->assertDontSee('psycholog@example.com')
            ->assertDontSee('challenges.cloudflare.com');
        $this->assertStringContainsString('noindex', $response->getContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));

        $this->get('/en/skrynka-doviry')->assertOk()
            ->assertSee('Trust box')->assertSee('Only the director receives the message')
            ->assertSee('Send anonymously')->assertSee('action="'.url('/en/skrynka-doviry').'"', false);

        $this->assertStringNotContainsString('skrynka-doviry', $this->get('/sitemap.xml')->getContent());
    }

    public function test_message_goes_to_every_recipient_separately_without_traces(): void
    {
        $this->configure();

        $this->submit()->assertRedirect(url('/skrynka-doviry'));
        $this->get('/skrynka-doviry')->assertOk()->assertSee('Звернення надіслано')->assertDontSee(self::TEXT);

        $this->assertSame(['director@example.com', 'psycholog@example.com'], array_column($this->mailer->sent, 'to'));
        $this->assertSame('skrynka.otfk@gmail.com', $this->mailer->sent[0]['sender']);
        $this->assertSame(self::PASSWORD, $this->mailer->sent[0]['password']);

        /** @var TrustBoxMessage $mail */
        $mail = $this->mailer->sent[0]['message'];
        $mail->assertHasSubject('Скринька довіри: нове звернення — Освітній процес');
        $mail->assertSeeInHtml(self::TEXT);
        $mail->assertSeeInText(self::TEXT);
        $mail->assertSeeInHtml('близько');
        foreach (['203.0.113.7', 'UniqueAgent'] as $trace) {
            $mail->assertDontSeeInHtml($trace);
            $mail->assertDontSeeInText($trace);
        }
        $this->assertSame([], $mail->replyTo);
        $this->assertSame(1, TrustBoxStat::forMonth()['sent']);

        // Текст і IP не потрапили в жодну таблицю, сесію чи кеш.
        foreach (Schema::getTableListing() as $table) {
            $table = str_contains($table, '.') ? substr($table, strrpos($table, '.') + 1) : $table;
            $dump = json_encode(DB::table($table)->get(), JSON_UNESCAPED_UNICODE);
            $this->assertStringNotContainsString('двадцять хвилин', $dump, $table);
            $this->assertStringNotContainsString('203.0.113.7', $dump, $table);
        }
        $this->assertStringNotContainsString('двадцять', json_encode(session()->all(), JSON_UNESCAPED_UNICODE));
    }

    public function test_english_page_sends_ukrainian_mail_with_language_note(): void
    {
        $this->configure(['director@example.com']);

        $this->submit(['topic' => 'bullying'], url: '/en/skrynka-doviry')->assertRedirect(url('/en/skrynka-doviry'));
        $this->get('/en/skrynka-doviry')->assertSee('Message sent');

        $mail = $this->mailer->sent[0]['message'];
        $mail->assertHasSubject('Скринька довіри: нове звернення — Булінг');
        $mail->assertSeeInText('Мова сторінки: англійська');
    }

    public function test_partial_and_total_delivery_failures(): void
    {
        $this->configure();
        $this->mailer->failFor = ['psycholog@example.com'];

        $this->submit()->assertRedirect();
        $this->assertSame(['director@example.com'], array_column($this->mailer->sent, 'to'));
        $this->assertSame(['sent' => 1, 'rejected' => 0, 'failed' => 1, 'captcha_unavailable' => 0], TrustBoxStat::forMonth());

        // Усі адреси відмовили — помилка, текст лишається у формі, ту саму форму можна надіслати ще раз.
        $this->mailer->sent = [];
        $this->mailer->failFor = ['director@example.com', 'psycholog@example.com'];
        $token = $this->token();
        $other = 'Інша ситуація: у кабінеті двісті шість не працює опалення вже другий тиждень.';
        $this->submit(['message' => $other], $token)->assertStatus(503)
            ->assertSee('поштовий сервер зараз недоступний')->assertSee($other);

        $this->mailer->failFor = [];
        $this->submit(['message' => $other], $token)->assertRedirect();
        $this->assertCount(2, $this->mailer->sent);
    }

    public function test_bots_are_rejected_silently(): void
    {
        $this->configure();

        // Пастка заповнена
        $this->submit(['website' => 'http://spam.example'])->assertRedirect();
        $this->get('/skrynka-doviry')->assertSee('Звернення надіслано');

        // Надто швидко після відкриття сторінки
        $html = $this->get('/skrynka-doviry')->getContent();
        preg_match('/name="issued" value="([^"]+)"/', $html, $m);
        $this->submit([], html_entity_decode($m[1]))->assertRedirect();

        // Підроблений токен — не тихо, а з проханням надіслати ще раз
        $this->submit([], 'forged')->assertStatus(422)->assertSee('Сторінка була відкрита задовго');

        $this->assertSame([], $this->mailer->sent);
        $this->assertSame(2, TrustBoxStat::forMonth()['rejected']);
    }

    public function test_same_form_and_same_text_are_sent_once(): void
    {
        $this->configure(['director@example.com']);

        $token = $this->token();
        $this->submit([], $token)->assertRedirect();
        $this->submit([], $token)->assertRedirect(); // подвійний клік
        $this->submit()->assertRedirect();           // той самий текст з нової форми
        $this->assertCount(1, $this->mailer->sent);
    }

    public function test_validation_keeps_text_without_session(): void
    {
        $this->configure();

        $this->submit(['message' => 'Коротко', 'consent' => ''])->assertStatus(422)
            ->assertSee('щонайменше 20 символів')->assertSee('Підтвердьте, що розумієте')
            ->assertSee('Коротко')->assertSee('aria-invalid="true"', false);
        $this->assertStringNotContainsString('Коротко', json_encode(session()->all(), JSON_UNESCAPED_UNICODE));

        $this->submit(['topic' => 'hacked'])->assertStatus(422)->assertSee('Виберіть тему');
        $this->submit(['message' => ['array']])->assertStatus(422)->assertSee('Напишіть повідомлення');
        $this->submit(['message' => str_repeat('а', TrustBox::MAX_LENGTH + 1)])->assertStatus(422)->assertSee('задовге');

        $links = 'Дивіться https://a.example https://b.example https://c.example https://d.example тут';
        $this->submit(['message' => $links])->assertStatus(422)->assertSee('схожий на спам');
        $this->submit(['message' => 'https://casino.example/bonus-for-you-now'])->assertStatus(422)->assertSee('схожий на спам');
        $this->submit(['message' => 'Аааааааааааааааааааааааааааааааа!'])->assertStatus(422)->assertSee('схожий на спам');

        $this->assertSame([], $this->mailer->sent);
        $this->assertSame(3, TrustBoxStat::forMonth()['rejected']);
    }

    public function test_device_limit_counts_only_successful_messages(): void
    {
        $this->configure(['director@example.com']);

        foreach (range(1, 3) as $i) {
            $this->submit(['message' => self::TEXT.' Випадок номер '.$i.'.'])->assertRedirect();
        }
        $this->submit(['message' => self::TEXT.' Випадок номер 4.'])->assertStatus(429)
            ->assertSee('Забагато звернень')->assertSee('Випадок номер 4.');
        $this->assertCount(3, $this->mailer->sent);

        // Через 10 хвилин можна знову
        $this->travel(11)->minutes();
        $this->submit(['message' => self::TEXT.' Випадок номер 5.'])->assertRedirect();
        $this->assertCount(4, $this->mailer->sent);
    }

    public function test_turnstile_rejects_failures_and_skips_when_unavailable(): void
    {
        $this->configure(['director@example.com']);
        TrustBox::storeValue(TrustBox::TURNSTILE_SITE_KEY, '1x00000000000000000000AA');
        TrustBox::storeSecret(TrustBox::TURNSTILE_SECRET, '1x0000000000000000000000000000000AA');

        $this->get('/skrynka-doviry')->assertSee('challenges.cloudflare.com/turnstile/v0/api.js', false)
            ->assertSee('data-sitekey="1x00000000000000000000AA"', false);

        Http::fake([Turnstile::VERIFY_URL => Http::sequence()
            ->push(['success' => false, 'error-codes' => ['invalid-input-response']])
            ->push(['success' => true, 'hostname' => 'evil.example'])
            ->push(['success' => true, 'hostname' => 'localhost'])
            ->push('Cloudflare down', 503),
        ]);

        $this->submit(['cf-turnstile-response' => 'bad'])->assertStatus(422)->assertSee('не робот')->assertSee(self::TEXT);
        $this->submit(['cf-turnstile-response' => 'other-site'])->assertStatus(422);
        $this->submit(['cf-turnstile-response' => ''])->assertStatus(422); // без токена — без запиту
        $this->assertCount(0, $this->mailer->sent);

        $this->submit(['cf-turnstile-response' => 'good'])->assertRedirect();
        $this->submit(['cf-turnstile-response' => 'any', 'message' => self::TEXT.' Ще раз.'])->assertRedirect();
        $this->assertCount(2, $this->mailer->sent, 'Cloudflare недоступний — перевірка пропускається');

        Http::assertSentCount(4);
        Http::assertSent(fn (HttpRequest $r) => $r['secret'] === '1x0000000000000000000000000000000AA' && ! isset($r['remoteip']));
        $this->assertSame(1, TrustBoxStat::forMonth()['captcha_unavailable']);
        $this->assertSame(3, TrustBoxStat::forMonth()['rejected']);
    }

    public function test_no_analytics_and_no_visit_tracking_on_trust_box(): void
    {
        $this->configure();
        Setting::updateOrCreate(['key' => Analytics::MEASUREMENT_ID_KEY], ['value' => 'G-TEST12345', 'group' => 'seo', 'type' => 'text']);

        $this->assertStringContainsString('data-ga4-id="G-TEST12345"', $this->get('/')->getContent());
        $page = $this->get('/skrynka-doviry')->getContent();
        $this->assertStringNotContainsString('data-ga4-id', $page);
        $this->assertStringContainsString('data-analytics-disabled', $page);
        $this->assertSame(0, DB::table('site_visits')->where('path', 'like', '%skrynka%')->count());
    }

    public function test_settings_page_is_admin_only_and_hides_secrets(): void
    {
        $this->configure();

        $this->actingAs(User::factory()->editor()->create());
        $this->get(TrustBoxSettings::getUrl())->assertForbidden();

        $this->actingAs(User::factory()->create());
        $stored = Setting::where('key', TrustBox::PASSWORD)->value('value');
        $this->assertSame(self::PASSWORD, Crypt::decryptString($stored));
        $this->get(TrustBoxSettings::getUrl())->assertOk()
            ->assertDontSee(self::PASSWORD)->assertDontSee($stored)
            ->assertSee('Пароль збережено');
        $this->get(SettingResource::getUrl())->assertOk()
            ->assertDontSee(TrustBox::PASSWORD)->assertDontSee(TrustBox::VERIFIED_HASH);
    }

    public function test_enabling_requires_successful_test_mail_with_current_settings(): void
    {
        $this->actingAs(User::factory()->create());

        $page = Livewire::test(TrustBoxSettings::class)
            ->set('data.'.TrustBox::SENDER, 'Skrynka.OTFK@gmail.com')
            ->set('data.app_password', 'abcd efgh ijkl mnop')
            ->set('data.'.TrustBox::RECIPIENTS, [
                ['email' => 'director@example.com', 'label' => 'Директор'],
                ['email' => 'deputy@example.com', 'label' => 'Заступник'],
            ])
            ->set('data.'.TrustBox::ENABLED, true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('data.app_password', null)
            ->assertSet('data.'.TrustBox::ENABLED, false);

        $this->assertFalse(TrustBox::enabled(), 'Без тестового листа скринька не вмикається');
        $this->assertSame(self::PASSWORD, TrustBox::password());
        $this->assertSame('skrynka.otfk@gmail.com', TrustBox::sender());
        $this->assertSame(['director@example.com', 'deputy@example.com'], array_column(TrustBox::recipients(), 'email'));

        $page->call('sendTest')->assertHasNoErrors();
        $this->assertSame(['director@example.com', 'deputy@example.com'], array_column($this->mailer->sent, 'to'));
        $this->mailer->sent[0]['message']->assertHasSubject('Скринька довіри: тестовий лист');
        $this->assertTrue(TrustBox::verified());

        $page->set('data.'.TrustBox::ENABLED, true)->call('save')->assertSet('data.'.TrustBox::ENABLED, true);
        $this->assertTrue(TrustBox::available());

        // Порожнє поле пароля не стирає збережений; хибний формат відхиляється.
        $page->call('save');
        $this->assertSame(self::PASSWORD, TrustBox::password());
        $page->set('data.app_password', 'short')->call('save')->assertHasErrors(['data.app_password']);
        $page->set('data.app_password', null)->set('data.'.TrustBox::SENDER, 'not-an-email')->call('save')
            ->assertHasErrors(['data.'.TrustBox::SENDER]);

        // Google Workspace з доменом коледжу — та сама SMTP-авторизація, адреса приймається.
        $page->set('data.'.TrustBox::SENDER, 'Skrinka@otfk.ukr.education')->call('save')->assertHasNoErrors();
        $this->assertSame('skrinka@otfk.ukr.education', TrustBox::sender());
        $this->assertFalse(TrustBox::available(), 'Новий відправник потребує нового тестового листа');
    }

    public function test_failed_test_mail_does_not_verify(): void
    {
        $this->actingAs(User::factory()->create());
        $this->mailer->failFor = ['deputy@example.com'];

        Livewire::test(TrustBoxSettings::class)
            ->set('data.'.TrustBox::SENDER, 'skrynka.otfk@gmail.com')
            ->set('data.app_password', self::PASSWORD)
            ->set('data.'.TrustBox::RECIPIENTS, [['email' => 'director@example.com', 'label' => ''], ['email' => 'deputy@example.com', 'label' => '']])
            ->call('save')
            ->call('sendTest');

        $this->assertFalse(TrustBox::verified());
    }
}
