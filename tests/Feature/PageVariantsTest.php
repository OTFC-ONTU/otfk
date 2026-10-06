<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Три варіанти одного шаблону pages/show: хаб із дочірніми сторінками,
 * звичайна контентна сторінка та урочиста heritage-сторінка.
 */
class PageVariantsTest extends TestCase
{
    use RefreshDatabase;

    private function hubWithChildren(int $count = 8): Page
    {
        $hub = Page::create([
            'title' => 'Тестовий розділ',
            'slug' => 'testovyy-rozdil',
            'body' => '<p>Опис розділу.</p>',
            'is_published' => true,
        ]);

        foreach (range(1, $count) as $i) {
            Page::create([
                'parent_id' => $hub->id,
                'title' => 'Дочірня сторінка ' . $i,
                'slug' => 'dochirnya-storinka-' . $i,
                'body' => '<p>Текст ' . $i . '</p>',
                'is_published' => true,
                'is_featured' => $i === 1,
                'sort_order' => $i,
            ]);
        }

        return $hub;
    }

    public function test_hub_page_lists_children_with_search_and_key_actions(): void
    {
        $this->hubWithChildren();

        $this->get('/testovyy-rozdil')
            ->assertOk()
            ->assertSee('Ключові дії')
            ->assertSee('Усі сторінки розділу')
            ->assertSee('Пошук по сторінках розділу…', escape: false)
            ->assertSee('8 сторінок')
            ->assertSee('Дочірня сторінка 1')
            ->assertSee('Дочірня сторінка 8')
            ->assertSee('Не знайшли потрібну сторінку?');
    }

    public function test_hub_hides_key_actions_when_nothing_is_featured(): void
    {
        $hub = $this->hubWithChildren();
        Page::where('parent_id', $hub->id)->update(['is_featured' => false]);

        $this->get('/testovyy-rozdil')
            ->assertOk()
            ->assertDontSee('Ключові дії')
            ->assertSee('Усі сторінки розділу');
    }

    public function test_hub_search_is_hidden_for_short_lists(): void
    {
        $this->hubWithChildren(3);

        $this->get('/testovyy-rozdil')
            ->assertOk()
            ->assertDontSee('Пошук по сторінках розділу…', escape: false);
    }

    public function test_hub_keeps_short_body_in_sidebar_and_moves_long_body_to_main_column(): void
    {
        $hub = $this->hubWithChildren(3);

        // Короткий опис — у сайдбарі, без блока «Про розділ» в основній колонці
        $this->get('/testovyy-rozdil')
            ->assertOk()
            ->assertSee('Про розділ')
            ->assertDontSee('id="pro-rozdil"', escape: false);

        // Довгий текст не читається у вузькому сайдбарі — показується статтею в основній колонці
        $hub->update(['body' => '<p>' . str_repeat('Виховна робота в коледжі. ', 60) . '</p>']);

        $this->get('/testovyy-rozdil')
            ->assertOk()
            ->assertSee('id="pro-rozdil"', escape: false)
            ->assertSee('Виховна робота в коледжі.');
    }

    public function test_content_page_builds_table_of_contents_from_headings(): void
    {
        Page::create([
            'title' => 'Довга сторінка',
            'slug' => 'dovha-storinka',
            'body' => '<h4>Перший розділ</h4><p>а</p><h4>Другий розділ</h4><p>б</p><h4>Третій розділ</h4><p>в</p>',
            'is_published' => true,
        ]);

        $this->get('/dovha-storinka')
            ->assertOk()
            ->assertSee('Навігація по сторінці')
            ->assertSee('id="rozdil-1-persii-rozdil"', escape: false)
            ->assertSee('href="#rozdil-3-tretii-rozdil"', escape: false);
    }

    public function test_content_page_without_enough_headings_has_no_table_of_contents(): void
    {
        Page::create([
            'title' => 'Коротка сторінка',
            'slug' => 'korotka-storinka',
            'body' => '<h4>Єдиний розділ</h4><p>текст</p>',
            'is_published' => true,
        ]);

        $this->get('/korotka-storinka')
            ->assertOk()
            ->assertDontSee('Навігація по сторінці');
    }

    public function test_content_page_links_to_neighbouring_pages_of_the_section(): void
    {
        $hub = $this->hubWithChildren(3);

        $this->get('/dochirnya-storinka-2')
            ->assertOk()
            ->assertSee('Сусідні сторінки розділу')
            ->assertSee('Попередня сторінка')
            ->assertSee('Наступна сторінка')
            ->assertSee('Увесь розділ «' . $hub->title . '»');
    }

    public function test_heritage_page_keeps_letter_and_gets_neighbours_block(): void
    {
        $this->hubWithChildren(3);
        Page::where('slug', 'dochirnya-storinka-2')->update(['is_heritage' => true]);

        $this->get('/dochirnya-storinka-2')
            ->assertOk()
            ->assertSee('heritage-frame', escape: false)
            ->assertSee('Інші сторінки розділу')
            ->assertDontSee('Навігація по сторінці');
    }

    public function test_every_variant_ends_with_the_same_call_to_action(): void
    {
        $this->hubWithChildren(3);

        foreach (['/testovyy-rozdil', '/dochirnya-storinka-1'] as $uri) {
            $this->get($uri)
                ->assertOk()
                ->assertSee('Не знайшли потрібну інформацію?');
        }
    }
}
