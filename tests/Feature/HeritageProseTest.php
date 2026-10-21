<?php

namespace Tests\Feature;

use App\Models\News;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeritageProseTest extends TestCase
{
    use RefreshDatabase;

    public function test_standard_news_uses_warm_prose_without_heritage_frame(): void
    {
        $news = News::create([
            'title' => 'Звичайна новина',
            'slug' => 'zvychayna-novyna',
            'body' => '<p>Перший абзац тестової новини.</p>',
            'is_published' => true,
            'published_at' => now(),
            'is_heritage' => false,
        ]);

        $this->get(route('news.show', $news))
            ->assertOk()
            ->assertSee('prose-site', escape: false)
            ->assertSee('prose-site--no-dropcap', escape: false)
            ->assertDontSee('heritage-frame', escape: false)
            ->assertDontSee('prose-heritage', escape: false);
    }

    public function test_heritage_news_uses_letter_style(): void
    {
        $news = News::create([
            'title' => 'Ювілей коледжу',
            'slug' => 'yuviley-koledzhu',
            'body' => '<p>Урочистий текст з архіву.</p>',
            'is_published' => true,
            'published_at' => now()->setDate(2020, 3, 15),
            'is_heritage' => true,
        ]);

        $this->get(route('news.show', $news))
            ->assertOk()
            ->assertSee('heritage-frame', escape: false)
            ->assertSee('prose-heritage', escape: false)
            ->assertSee('prose-site--no-dropcap', escape: false)
            ->assertSee('Особлива публікація')
            ->assertSee('З повагою', escape: false)
            ->assertSee('Одеса · 15 березня 2020');
    }

    public function test_heritage_page_uses_letter_style(): void
    {
        $page = Page::create([
            'title' => 'Історія коледжу',
            'slug' => 'istoriya-koledzhu',
            'body' => '<p>Хроніка заснування.</p>',
            'is_published' => true,
            'is_heritage' => true,
        ]);

        $this->get('/'.$page->slug)
            ->assertOk()
            ->assertSee('heritage-frame', escape: false)
            ->assertSee('prose-heritage', escape: false)
            ->assertSee('prose-site--no-dropcap', escape: false);
    }

    public function test_istoriya_page_keeps_drop_cap(): void
    {
        $this->get('/istoriya')
            ->assertOk()
            ->assertSee('prose-site', escape: false)
            ->assertDontSee('prose-site--no-dropcap', escape: false);
    }

    public function test_other_pages_disable_drop_cap(): void
    {
        $this->get('/abituriyentu')
            ->assertOk()
            ->assertSee('prose-site--no-dropcap', escape: false);
    }

    /** Текст сторінки без бічної колонки займає всю ширину контейнера, а не вузьку смугу 65ch зліва. */
    public function test_prose_card_fills_available_width(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression('/\.prose-site \{\s*@apply prose prose-slate prose-lg max-w-none/', $css);
        $this->assertDoesNotMatchRegularExpression('/\.heritage-frame \{[^}]*max-w-\[65ch\]/', $css);
    }

    /** Фото в абзаці, вирівняному в редакторі по центру/праворуч, вирівнюється так само і на сайті. */
    public function test_aligned_paragraph_aligns_its_images(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression('/\.prose-site :is\(p, h2, h3, h4\):is\(\[style\*="text-align: center"\][^)]*\[style\*="text-align: right"\][^)]*\) img \{ display: inline-block; \}/', $css);
        // Звичайний чи justify-абзац фото не зачіпає: воно лишається окремим блоком
        $this->assertDoesNotMatchRegularExpression('/\[style\*="text-align"\] img/', $css);
    }

    /** Широкий імпортований вміст (inline min-width/width, iframe, довгі посилання) не розширює сторінку на телефоні. */
    public function test_wide_content_does_not_widen_page_on_mobile(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        // break-word (не anywhere): таблиці зберігають min-content і прокручуються, а не стискаються
        $this->assertStringContainsString('.prose-site { overflow-wrap: break-word; }', $css);
        $this->assertStringNotContainsString('.prose-site { overflow-wrap: anywhere; }', $css);
        $this->assertStringContainsString('.prose-site [style*="width"]:not(table, table *) { min-width: 0 !important; }', $css);
        // Власний менший inline max-width не розтягується до 100%, а max-width: none — обмежується
        $this->assertStringContainsString('.prose-site [style*="width"]:not([style*="max-width"], table, table *),', $css);
        $this->assertStringContainsString('.prose-site :is([style*="max-width:none"], [style*="max-width: none"]):not(table, table *) { min-width: 0 !important; max-width: 100% !important; }', $css);
        $this->assertMatchesRegularExpression('/\.prose-site :where\(img, video, iframe, embed, object\),/', $css);

        // Колонка grid із текстом не росте до min-content вмісту: без min-w-0 відсоткова max-width не діє
        foreach ([
            'pages/partials/content.blade.php' => 'class="min-w-0 lg:col-span-2"',
            'pages/partials/hub.blade.php' => 'class="min-w-0 lg:col-span-3"',
            'structure/show.blade.php' => 'class="min-w-0 lg:col-span-2"',
            'news/show.blade.php' => 'class="min-w-0 lg:col-span-8"',
            'documents/category.blade.php' => 'min-w-0 lg:order-2 lg:col-span-9',
        ] as $view => $class) {
            $this->assertStringContainsString($class, file_get_contents(resource_path('views/'.$view)), $view);
        }
    }
}
