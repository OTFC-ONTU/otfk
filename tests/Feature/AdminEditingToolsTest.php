<?php

namespace Tests\Feature;

use App\Filament\Pages\BrokenLinks;
use App\Filament\Resources\NewsResource\Pages\ListNews;
use App\Filament\Resources\PageResource\Pages\ListPages;
use App\Filament\Widgets\Drafts;
use App\Models\News;
use App\Models\Page;
use App\Models\User;
use App\Support\LinkChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Етап 3 ADMIN-UX-PLAN: дія «Дублювати» для сторінок/новин, перевірка битих
 * внутрішніх посилань (LinkChecker + otfk:check-links + сторінка адмінки),
 * віджет «Чернетки» на дашборді.
 */
class AdminEditingToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::firstOrFail()); // створюється сидером
    }

    public function test_page_can_be_duplicated_as_draft(): void
    {
        $page = Page::create([
            'title' => 'Оригінальна сторінка',
            'slug' => 'oryhinalna-storinka',
            'body' => '<p>Тіло сторінки.</p>',
            'is_published' => true,
        ]);

        Livewire::test(ListPages::class)->callTableAction('replicate', $page);

        $copy = Page::where('slug', 'oryhinalna-storinka-kopiya')->first();

        $this->assertNotNull($copy);
        $this->assertFalse($copy->is_published);
        $this->assertSame('Оригінальна сторінка (копія)', $copy->title);
        $this->assertSame('<p>Тіло сторінки.</p>', $copy->body);
    }

    public function test_duplicated_slug_gets_counter_when_taken(): void
    {
        $page = Page::create(['title' => 'A', 'slug' => 'dubl-test', 'is_published' => true]);
        Page::create(['title' => 'B', 'slug' => 'dubl-test-kopiya', 'is_published' => true]);

        Livewire::test(ListPages::class)->callTableAction('replicate', $page);

        $this->assertNotNull(Page::where('slug', 'dubl-test-kopiya-2')->first());
    }

    public function test_news_duplicate_resets_counters_and_telegram(): void
    {
        $news = News::create([
            'title' => 'Оригінальна новина',
            'slug' => 'oryhinalna-novyna',
            'body' => '<p>Текст.</p>',
            'is_published' => true,
            'published_at' => now()->subDay(),
            'views' => 100,
            'likes' => 5,
            'telegram_posted_at' => now()->subDay(),
        ]);

        Livewire::test(ListNews::class)->callTableAction('replicate', $news);

        $copy = News::where('slug', 'oryhinalna-novyna-kopiya')->first();

        $this->assertNotNull($copy);
        $this->assertFalse($copy->is_published);
        $this->assertSame(0, (int) $copy->views);
        $this->assertSame(0, (int) $copy->likes);
        $this->assertNull($copy->telegram_posted_at);
    }

    public function test_link_checker_flags_broken_targets(): void
    {
        Page::create(['title' => 'Жива', 'slug' => 'zhyva-storinka', 'is_published' => true]);
        Page::create(['title' => 'Чернетка', 'slug' => 'chernetka-storinka', 'is_published' => false]);
        Page::create([
            'title' => 'Сторінка з посиланнями',
            'slug' => 'storinka-z-posylannyamy',
            'is_published' => true,
            'body' => '<p><a href="/zhyva-storinka">ок</a>'
                . '<a href="/neisnuyucha-storinka">битий</a>'
                . '<a href="/chernetka-storinka">чернетка</a>'
                . '<a href="/storage/documents/nemaye-takogo.pdf">файл</a>'
                . '<a href="https://otfk.od.ua/stara">старий сайт</a>'
                . '<a href="https://example.com/x">зовнішній</a>'
                . '<a href="#yakir">якір</a>'
                . '<a href="mailto:a@b.c">пошта</a></p>',
        ]);

        $reasons = collect((new LinkChecker)->scan())
            ->where('title', 'Сторінка з посиланнями')
            ->pluck('reason', 'url');

        $this->assertArrayNotHasKey('/zhyva-storinka', $reasons->all());
        $this->assertArrayNotHasKey('https://example.com/x', $reasons->all());
        $this->assertArrayNotHasKey('#yakir', $reasons->all());
        $this->assertArrayNotHasKey('mailto:a@b.c', $reasons->all());
        $this->assertArrayHasKey('/neisnuyucha-storinka', $reasons->all());
        $this->assertStringContainsString('чернетка', $reasons['/chernetka-storinka']);
        $this->assertStringContainsString('сховищ', $reasons['/storage/documents/nemaye-takogo.pdf']);
        $this->assertStringContainsString('старий сайт', $reasons['https://otfk.od.ua/stara']);
    }

    public function test_check_links_command_reports_broken(): void
    {
        Page::create([
            'title' => 'Проблемна',
            'slug' => 'problemna',
            'is_published' => true,
            'body' => '<a href="/tochno-nemaye-takoyi">x</a>',
        ]);

        $this->artisan('otfk:check-links')
            ->expectsOutputToContain('/tochno-nemaye-takoyi')
            ->assertExitCode(1);
    }

    public function test_broken_links_admin_page_renders(): void
    {
        $this->get(BrokenLinks::getUrl())->assertOk()
            ->assertSee('Биті внутрішні посилання');
    }

    public function test_drafts_widget_lists_unpublished_content(): void
    {
        Page::create(['title' => 'Чернетка сторінки', 'slug' => 'chernetka-vidzhet-1', 'is_published' => false]);
        News::create(['title' => 'Чернетка новини', 'slug' => 'chernetka-vidzhet-2', 'is_published' => false, 'published_at' => now()]);
        \App\Models\Specialty::create(['title' => 'Чернетка спеціальності', 'slug' => 'chernetka-vidzhet-3', 'is_published' => false]);
        \App\Models\Department::create(['title' => 'Чернетка підрозділу', 'slug' => 'chernetka-vidzhet-4', 'type' => 'kafedra', 'is_published' => false]);

        $this->assertTrue(Drafts::canView());

        $titles = collect((new Drafts)->drafts())->pluck('title');

        $this->assertContains('Чернетка сторінки', $titles);
        $this->assertContains('Чернетка новини', $titles);
        $this->assertContains('Чернетка спеціальності', $titles);
        $this->assertContains('Чернетка підрозділу', $titles);
    }
}
