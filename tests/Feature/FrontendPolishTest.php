<?php

namespace Tests\Feature;

use App\Models\News;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendPolishTest extends TestCase
{
    use RefreshDatabase;

    public function test_skip_link_present_on_home(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Перейти до основного вмісту')
            ->assertSee('id="main-content"', escape: false);
    }

    public function test_footer_copyright_ends_with_current_kyiv_year(): void
    {
        // 31 грудня 23:30 UTC у Києві вже новий рік
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-12-31 23:30:00', 'UTC'));

        $this->get('/')->assertOk()->assertSee('© 2014-2027');
    }

    public function test_home_uses_plain_summary_card(): void
    {
        // Без обкладинки сторінки — звичайна (не велика) Twitter-картка.
        $this->get('/')
            ->assertOk()
            ->assertSee('name="twitter:card" content="summary"', escape: false);
    }

    public function test_news_with_cover_uses_large_social_card(): void
    {
        $news = News::create([
            'title' => 'Новина з обкладинкою',
            'slug' => 'novyna-z-obkladynkoyu',
            'body' => '<p>Текст</p>',
            'cover_image' => 'news/cover.jpg',
            'published_at' => now()->subHour(),
            'is_published' => true,
        ]);

        $this->get(route('news.show', $news))
            ->assertOk()
            ->assertSee('summary_large_image')
            ->assertSee('storage/news/cover.jpg', escape: false);
    }

    public function test_site_is_light_only_without_theme_toggle(): void
    {
        $res = $this->get('/')->assertOk();

        // Перемикач теми й темну тему прибрано — лишається лише світла
        $res->assertDontSee("classList.toggle('dark'", escape: false);
        $res->assertDontSee("localStorage.getItem('theme')", escape: false);

        // Старого тeплого оверлея теж немає
        $res->assertDontSee('nightshade', escape: false);
        $res->assertDontSee("localStorage.getItem('night')", escape: false);
    }

    public function test_default_icons_use_shield_for_tab_and_full_emblem_for_phones(): void
    {
        $res = $this->get('/')->assertOk();

        // Вкладка — щит герба 32px (браузер обирає найближчий розмір, а не повний герб 192px)
        $res->assertSee('sizes="32x32" href="'.asset('icon-32.png').'"', escape: false);
        $res->assertSee('sizes="192x192" href="'.asset('icon-192.png').'"', escape: false);
        $res->assertSee('rel="apple-touch-icon" href="'.asset('apple-touch-icon.png').'"', escape: false);

        foreach (['favicon.ico', 'icon-32.png', 'icon-192.png', 'apple-touch-icon.png'] as $file) {
            $this->assertFileExists(public_path($file));
        }
    }

    public function test_sitemap_has_priority_changefreq_and_lastmod(): void
    {
        News::create([
            'title' => 'Новина для мапи',
            'slug' => 'novyna-dlya-mapy',
            'body' => '<p>Текст</p>',
            'published_at' => now()->subDay(),
            'is_published' => true,
        ]);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('<priority>', escape: false)
            ->assertSee('<changefreq>', escape: false)
            ->assertSee('<lastmod>', escape: false);
    }
}
