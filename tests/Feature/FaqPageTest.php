<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Редизайн /faq: світла шапка з лічильником, живий пошук, акордеон, бічний блок приймальної комісії. */
class FaqPageTest extends TestCase
{
    use RefreshDatabase;

    /** Стартові питання з міграції прибираємо — перевіряємо на власному наборі. */
    protected function setUp(): void
    {
        parent::setUp();

        Faq::query()->delete();
    }

    private function makeFaq(string $question, string $answer = 'Відповідь на питання.', int $sort = 0): Faq
    {
        return Faq::create([
            'question' => $question,
            'answer' => $answer,
            'sort_order' => $sort,
            'is_active' => true,
        ]);
    }

    public function test_page_has_light_header_with_counter_instead_of_navy_hero(): void
    {
        $this->makeFaq('Як подати заявку на вступ онлайн?');
        $this->makeFaq('Які документи потрібні для вступу?', 'Заява, атестат, паспорт.', 1);

        $this->get('/faq')
            ->assertOk()
            // Світла шапка розділу — як на новинах, відео, галереї та спеціальностях
            ->assertSee('bg-slate-50/80', false)
            ->assertSee('text-brand-950 sm:text-4xl lg:text-[2.75rem]">Питання та відповіді</h1>', false)
            // Лічильник питань
            ->assertSee('2 питання');
    }

    /** Лічильник відмінюється: 1 питання / 2–4 питання / 5+ питань. */
    public function test_counter_uses_ukrainian_plural_forms(): void
    {
        $this->makeFaq('Єдине питання?');
        $this->get('/faq')->assertSee('1 питання');

        for ($i = 2; $i <= 5; $i++) {
            $this->makeFaq("Питання номер {$i}?", 'Відповідь.', $i);
        }

        $this->get('/faq')->assertSee('5 питань');
    }

    public function test_page_has_client_side_search_over_questions(): void
    {
        $this->makeFaq('Де подивитися розклад дзвінків?', 'Розклад у розділі «Студенту».');

        $this->get('/faq')
            ->assertOk()
            ->assertSee('id="faq-search"', false)
            ->assertSee('Пошук по питаннях', false)
            // Пошуковий індекс віддається в Alpine — фільтрація без запитів на сервер
            ->assertSee('x-model="q"', false)
            ->assertSee('За вашим запитом нічого не знайдено');
    }

    public function test_accordion_items_are_numbered_and_accessible(): void
    {
        $this->makeFaq('Перше питання?', 'Перша відповідь.');
        $this->makeFaq('Друге питання?', 'Друга відповідь.', 1);

        $this->get('/faq')
            ->assertOk()
            ->assertSee('aria-controls="faq-answer-0"', false)
            ->assertSee('id="faq-answer-1"', false)
            ->assertSee(':aria-expanded="open === 1', false)
            ->assertSee('Перша відповідь.')
            ->assertSee('Друга відповідь.');
    }

    /** Розмітка FAQPage для Google має пережити редизайн. */
    public function test_page_keeps_faqpage_rich_results_markup(): void
    {
        $this->makeFaq('Як вступити?', 'Подайте заявку онлайн.');

        $this->get('/faq')
            ->assertOk()
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('"name":"Як вступити?"', false);
    }

    public function test_sidebar_shows_admission_contacts_from_settings(): void
    {
        $this->makeFaq('Питання?');

        Setting::updateOrCreate(['key' => 'contact_phone'], ['value' => '(048) 753-16-51']);
        Setting::updateOrCreate(['key' => 'contact_email'], ['value' => 'otkua@ukr.net']);
        Setting::updateOrCreate(['key' => 'work_hours'], ['value' => 'Пн–Пт: 9:00–17:00']);

        $this->get('/faq')
            ->assertOk()
            ->assertSee('Не знайшли відповідь?')
            ->assertSee('(048) 753-16-51')
            ->assertSee('otkua@ukr.net')
            ->assertSee('Пн–Пт: 9:00–17:00');
    }

    /** Порожні налаштування не залишають порожній блок контактів. */
    public function test_sidebar_hides_contacts_block_when_settings_are_empty(): void
    {
        $this->makeFaq('Питання?');

        foreach (['contact_phone', 'contact_email', 'contact_address', 'work_hours'] as $key) {
            Setting::updateOrCreate(['key' => $key], ['value' => '']);
        }

        $this->get('/faq')
            ->assertOk()
            ->assertSee('Не знайшли відповідь?')
            ->assertDontSee('дзвінки у робочий час');
    }

    public function test_page_leads_to_contacts(): void
    {
        $this->makeFaq('Питання?');

        $this->get('/faq')
            ->assertOk()
            ->assertSee('Є питання щодо вступу?')
            ->assertSee(route('contacts'), false);
    }

    public function test_page_shows_empty_state_without_questions(): void
    {
        $this->get('/faq')
            ->assertOk()
            ->assertSee('Питання та відповіді скоро зʼявляться.')
            ->assertDontSee('id="faq-search"', false);
    }
}
