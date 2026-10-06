<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaqSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_faq_page_renders_with_rich_results_markup(): void
    {
        $this->get('/faq')
            ->assertOk()
            ->assertSee('Питання та відповіді')
            ->assertSee('Де подивитися розклад дзвінків?')
            ->assertSee('"@type":"FAQPage"', escape: false);
    }

    public function test_seed_faq_no_longer_mentions_online_application(): void
    {
        // Форми онлайн-заявки більше немає — сид-FAQ про неї видалено міграцією.
        $this->get('/faq')
            ->assertOk()
            ->assertDontSee('Як подати заявку на вступ онлайн?');
    }

    public function test_abituriyentu_section_has_faq_tile(): void
    {
        $this->get('/abituriyentu')->assertOk()->assertSee('Питання та відповіді');
    }
}
