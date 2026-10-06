<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\News;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Редизайн детальної новини: світла шапка, час читання, сусідні новини, блок абітурієнта. */
class NewsArticleLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function makeNews(string $title, string $slug, int $daysAgo, string $body = '<p>Текст новини.</p>'): News
    {
        return News::create([
            'title' => $title,
            'slug' => $slug,
            'body' => $body,
            'published_at' => now()->subDays($daysAgo),
            'is_published' => true,
        ]);
    }

    public function test_article_header_is_light_with_reading_time(): void
    {
        // ~300 слів → 2 хвилини читання при 150 сл/хв
        $news = $this->makeNews('Новина з часом читання', 'chas-chytannya', 1, '<p>' . str_repeat('слово ', 300) . '</p>');

        $this->get(route('news.show', $news))
            ->assertOk()
            ->assertSee('Час читання: 2 хв')
            ->assertDontSee('<section class="bg-brand-950">', escape: false);
    }

    public function test_neighbour_news_are_linked(): void
    {
        // Стрічка з демо-міграцій не має впливати на порядок сусідів
        News::query()->delete();

        $older = $this->makeNews('Стара новина', 'stara-novyna', 5);
        $current = $this->makeNews('Поточна новина', 'potochna-novyna', 3);
        $newer = $this->makeNews('Свіжа новина', 'svizha-novyna', 1);

        $this->get(route('news.show', $current))
            ->assertOk()
            ->assertSee('Попередня новина')
            ->assertSee('Наступна новина')
            ->assertSee(route('news.show', $older), escape: false)
            ->assertSee(route('news.show', $newer), escape: false);
    }

    public function test_single_news_has_no_neighbour_navigation(): void
    {
        News::query()->delete();

        $only = $this->makeNews('Єдина новина', 'yedyna-novyna', 2);

        $this->get(route('news.show', $only))
            ->assertOk()
            ->assertDontSee('Попередня новина')
            ->assertDontSee('Наступна новина');
    }

    public function test_applicant_links_come_from_menu(): void
    {
        // Меню з демо-міграцій замінюємо власною фікстурою
        MenuItem::query()->delete();
        Cache::forget('menu.navigation');

        $page = Page::firstOrCreate(['slug' => 'abituriyentu'], ['title' => 'Абітурієнту', 'body' => '<p>Вступ</p>', 'is_published' => true]);
        $root = MenuItem::create(['label' => 'Абітурієнту', 'link_type' => 'page', 'page_id' => $page->id, 'sort_order' => 1, 'is_visible' => true]);
        MenuItem::create(['parent_id' => $root->id, 'label' => 'Правила прийому', 'link_type' => 'url', 'url' => '/pravyla-pryyomu', 'sort_order' => 1, 'is_visible' => true]);
        MenuItem::create(['parent_id' => $root->id, 'label' => 'Прихований пункт', 'link_type' => 'url', 'url' => '/hidden', 'sort_order' => 2, 'is_visible' => false]);

        $news = $this->makeNews('Новина з блоком абітурієнта', 'blok-abituriyenta', 1);

        $this->get(route('news.show', $news))
            ->assertOk()
            ->assertSee('Корисно для абітурієнта')
            ->assertSee('Правила прийому')
            ->assertDontSee('Прихований пункт');
    }

    public function test_applicant_block_hidden_without_menu_item(): void
    {
        MenuItem::query()->delete();
        Cache::forget('menu.navigation');

        $news = $this->makeNews('Новина без меню', 'bez-menyu', 1);

        $this->get(route('news.show', $news))
            ->assertOk()
            ->assertDontSee('Корисно для абітурієнта');
    }
}
