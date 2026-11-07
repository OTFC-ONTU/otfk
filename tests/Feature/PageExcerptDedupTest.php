<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PageExcerptDedupTest extends TestCase
{
    use RefreshDatabase;

    /** Видимий текст сторінки: у <head> опис законно стоїть у метатегах. */
    private function bodyRegion(Page $page): string
    {
        return Str::after($this->get('/'.$page->slug)->assertOk()->getContent(), '</head>');
    }

    private function page(array $attributes): Page
    {
        return Page::create($attributes + ['title' => 'Сторінка', 'slug' => 'excerpt-dedup-test', 'is_published' => true]);
    }

    public function test_distinct_excerpt_is_shown_once_in_the_header(): void
    {
        $page = $this->page([
            'excerpt' => 'СЕНТИНЕЛ короткий опис сторінки.',
            'body' => '<p>Основний текст зовсім про інше.</p>',
        ]);

        $html = $this->bodyRegion($page);

        $this->assertSame(1, substr_count($html, 'СЕНТИНЕЛ'));
        $this->assertLessThan(strpos($html, 'Основний текст'), strpos($html, 'СЕНТИНЕЛ'));
    }

    public function test_excerpt_copied_from_the_body_start_is_shown_only_in_the_body(): void
    {
        $page = $this->page([
            'excerpt' => "СЕНТИНЕЛ об'єднання коледжу.",
            'body' => "<p>СЕНТИНЕЛ об'єднання коледжу. Далі йде основний текст.</p>",
        ]);

        $this->assertSame(1, substr_count($this->bodyRegion($page), 'СЕНТИНЕЛ'));
    }

    public function test_excerpt_spanning_two_blocks_of_the_body_is_recognized(): void
    {
        $page = $this->page([
            'excerpt' => 'СЕНТИНЕЛ заголовок Перше речення.',
            'body' => '<h2>СЕНТИНЕЛ заголовок</h2><p>Перше речення. Далі текст.</p>',
        ]);

        $this->assertSame(1, substr_count($this->bodyRegion($page), 'СЕНТИНЕЛ'));
    }

    public function test_hub_keeps_the_excerpt_when_its_paragraph_is_hidden_from_the_intro(): void
    {
        $page = $this->page([
            'excerpt' => 'СЕНТИНЕЛ перехід до підрозділу',
            'body' => '<p><a href="/excerpt-dedup-child">СЕНТИНЕЛ перехід до підрозділу</a></p>',
        ]);
        Page::create(['title' => 'Дочірня', 'slug' => 'excerpt-dedup-child', 'parent_id' => $page->id, 'is_published' => true]);

        $this->assertStringContainsString('text-slate-500">СЕНТИНЕЛ перехід до підрозділу</p>', $this->bodyRegion($page));
    }

    public function test_markup_only_excerpt_is_not_printed_as_a_lead(): void
    {
        $page = $this->page([
            'is_heritage' => true,
            'excerpt' => '<br>',
            'body' => '<p>Основний текст.</p>',
        ]);

        $this->assertStringNotContainsString('&lt;br&gt;', $this->bodyRegion($page));
    }

    public function test_heritage_page_keeps_the_lead_above_the_text(): void
    {
        $page = $this->page([
            'is_heritage' => true,
            'excerpt' => 'СЕНТИНЕЛ короткий опис сторінки.',
            'body' => '<p>Основний текст зовсім про інше.</p>',
        ]);

        $this->assertSame(1, substr_count($this->bodyRegion($page), 'СЕНТИНЕЛ'));
    }
}
