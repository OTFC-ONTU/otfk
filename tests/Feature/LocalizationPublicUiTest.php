<?php

namespace Tests\Feature;

use App\Models\News;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class LocalizationPublicUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_page_headings_and_controls_use_the_request_language(): void
    {
        foreach ([
            '/novyny' => ['College news', 'Новини коледжу'],
            '/zayavka' => ['Submit application', 'Надіслати заявку'],
            '/kontakty' => ['Contact form', 'Форма зворотного звʼязку'],
            '/poshuk' => ['Search the site', 'Пошук по сайту'],
            '/spetsialnosti' => ['Our specialties', 'Наші спеціальності'],
            '/struktura' => ['College structure', 'Структура коледжу'],
            '/administratsiya' => ['College administration', 'Адміністрація коледжу'],
            '/dokumenty' => ['Public information', 'Публічна інформація'],
            '/halereya' => ['Photo gallery', 'Фотогалерея'],
            '/video' => ['College videos', 'Відеоматеріали'],
            '/podiyi' => ['College events', 'Події коледжу'],
            '/faq' => ['Frequently asked questions', 'Питання та відповіді'],
            '/kviz' => ['Start quiz', 'Почати тест'],
            '/rozklad-dzvinkiv' => ['Class duration:', 'Тривалість пари'],
        ] as $path => [$english, $ukrainian]) {
            $this->get('/en'.$path)->assertOk()->assertSee($english)->assertDontSee($ukrainian);
            $this->get($path)->assertOk()->assertSee($ukrainian);
        }
        $news = News::published()->firstOrFail();
        $this->get('/en/novyny/'.$news->slug)->assertOk()->assertSee('Copy link')->assertSee('Share on Telegram')
            ->assertDontSee('Копіювати посилання')->assertDontSee('Поділитися');
        $this->get('/en/no-such-page')->assertNotFound()->assertSee('Page not found')->assertSee('Back to home');
    }

    public function test_form_success_and_validation_messages_follow_the_form_language(): void
    {
        $this->post('/en/kontakty', ['name' => 'Test', 'message' => 'Question'])->assertRedirect('/en/kontakty')
            ->assertSessionHas('status', 'Thank you! Your message has been sent.');
        $this->post('/en/zayavka', ['name' => 'Test', 'phone' => '+380000000000'])->assertRedirect('/en/zayavka')
            ->assertSessionHas('status', 'Thank you! Your application has been received. We will contact you soon.');
        $this->post('/en/kontakty', ['website' => 'spam'])->assertSessionHas('status', 'Thank you! Your message has been sent.');
        $this->post('/en/zayavka', ['website' => 'spam'])->assertSessionHas('status', 'Thank you! Your application has been received. We will contact you.');
        $this->postJson('/en/kontakty', [])->assertUnprocessable()->assertJsonPath('errors.name.0', 'The name field is required.');
    }

    public function test_error_pages_work_without_database_or_locale_middleware(): void
    {
        app()->setLocale('uk');
        $this->app['request'] = Request::create('/en/unavailable');
        foreach (['500' => 'Something went wrong', '503' => 'Maintenance'] as $code => $text) {
            $html = view('errors.'.$code)->render();
            $this->assertStringContainsString('lang="en"', $html);
            $this->assertStringContainsString($text, $html);
        }
    }
}
