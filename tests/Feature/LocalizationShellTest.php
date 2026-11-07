<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_shell_uses_request_language_and_localized_links(): void
    {
        $this->get('/en/faq')->assertOk()
            ->assertSee('lang="en"', false)
            ->assertSee('content="en_GB"', false)
            ->assertSee('Skip to main content')
            ->assertSee('Search the site...')
            ->assertSee('SSD &quot;OTPC ONTU&quot;', false)
            ->assertSee('href="'.url('/en/abituriyentu').'"', false)
            ->assertSee('action="'.url('/en/poshuk').'"', false)
            ->assertSee('href="'.url('/en/novyny').'"', false)
            // FAQ — розділ з перекладеним каркасом, на /en індексується.
            ->assertHeaderMissing('X-Robots-Tag');

        $this->get('/faq')->assertOk()
            ->assertSee('lang="uk"', false)
            ->assertSee('Перейти до основного вмісту')
            ->assertSee('ВСП &quot;ОТФК ОНТУ&quot;', false)
            ->assertSee('href="'.url('/abituriyentu').'"', false);
    }

    public function test_menu_translation_override_fallback_and_cache_invalidation(): void
    {
        $item = MenuItem::create([
            'label' => 'Особливий пункт', 'label_en' => 'Custom navigation',
            'link_type' => 'url', 'url' => '/faq', 'is_visible' => true,
        ]);

        $this->get('/en/faq')->assertOk()->assertSee('Custom navigation');
        $cached = MenuItem::navigation()->firstWhere('id', $item->id);
        $this->assertSame('Custom navigation', $cached->localized_label);
        $this->get('/faq')->assertOk()->assertSee('Особливий пункт');
        $this->assertSame('Особливий пункт', $cached->localized_label);

        $item->update(['label_en' => 'Updated navigation']);
        $this->get('/en/faq')->assertOk()->assertSee('Updated navigation')->assertDontSee('Custom navigation');
        $item->update(['label_en' => null]);
        $this->get('/en/faq')->assertOk()->assertSee('Особливий пункт')->assertSee('About the college');
        $item->update(['label' => 'Головна', 'label_en' => ' ']);
        $this->assertSame('Home', $item->localized_label);
    }

    public function test_news_sidebar_and_404_sections_use_translated_menu_labels(): void
    {
        $root = MenuItem::navigation()->first(fn (MenuItem $item) => $item->page?->slug === 'abituriyentu');
        $this->assertNotNull($root);
        MenuItem::create([
            'parent_id' => $root->id, 'label' => 'Правила прийому', 'label_en' => 'Admission rules',
            'link_type' => 'url', 'url' => '/faq', 'is_visible' => true, 'sort_order' => -100,
        ]);
        $root->update(['label_en' => 'For applicants']);

        $news = \App\Models\News::create([
            'title' => 'Новина для бічної панелі', 'slug' => 'novyna-bichna-panel',
            'body' => '<p>Текст</p>', 'is_published' => true, 'published_at' => now()->subHour(),
        ]);

        $this->get('/en/novyny/'.$news->slug)->assertOk()
            ->assertSee('Admission rules')->assertDontSee('Правила прийому');
        $this->get('/novyny/'.$news->slug)->assertOk()
            ->assertSee('Правила прийому');

        $this->get('/en/nemaye-takoyi-storinky')->assertNotFound()->assertSee('For applicants');
    }

    public function test_ukrainian_material_is_preserved_inside_english_shell(): void
    {
        $page = Page::where('slug', 'abituriyentu')->firstOrFail();
        $this->get('/en/'.$page->slug)->assertOk()
            ->assertSee($page->title)
            ->assertSee('Skip to main content');
    }

    public function test_search_suggestion_links_keep_the_selected_language(): void
    {
        $page = Page::create([
            'title' => 'Перевірка підказки', 'slug' => 'perevirka-pidkazky',
            'body' => '<p>Матеріал</p>', 'is_published' => true,
        ]);

        $this->getJson('/en/poshuk/pidkazky?q=Перевірка')->assertOk()
            ->assertJsonFragment(['url' => url('/en/'.$page->slug)]);
        $this->getJson('/poshuk/pidkazky?q=Перевірка')->assertOk()
            ->assertJsonFragment(['url' => url('/'.$page->slug)]);
    }
}
