<?php

namespace Tests\Feature;

use App\Filament\Resources\PageLinkResource\Pages\CreatePageLink;
use App\Filament\Resources\PageLinkResource\Pages\ListPageLinks;
use App\Filament\Resources\QuickLinkResource\Pages\ListQuickLinks;
use App\Filament\Support\SafeDeleteAction;
use App\Models\Page;
use App\Models\QuickLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Кнопки-посилання (quick_links, location=page_link): плитка на сторінці розділу, що веде
 * за вказаною адресою і не створює окремої сторінки сайту.
 */
class PageSectionLinksTest extends TestCase
{
    use RefreshDatabase;

    private function page(string $slug, array $attributes = []): Page
    {
        return Page::create($attributes + ['title' => 'Сторінка '.$slug, 'slug' => $slug, 'body' => '<p>Текст сторінки.</p>', 'is_published' => true]);
    }

    private function link(Page $page, array $attributes = []): QuickLink
    {
        return QuickLink::create($attributes + [
            'location' => QuickLink::LOCATION_PAGE_LINK,
            'page_id' => $page->id,
            'title' => 'Подати заявку',
            'url' => 'https://forms.example.com/zayavka',
            'open_new_tab' => true,
        ]);
    }

    public function test_hub_shows_link_card_after_child_pages_and_opens_it_in_a_new_tab(): void
    {
        $hub = $this->page('tsentr');
        $this->page('normatyvna-baza', ['parent_id' => $hub->id, 'title' => 'Нормативна база']);
        $this->link($hub);

        $response = $this->get('/tsentr')->assertOk()
            ->assertSeeInOrder(['Усі сторінки розділу', 'Нормативна база', '02', 'Подати заявку'])
            ->assertSee('href="https://forms.example.com/zayavka"', escape: false)
            ->assertSee('target="_blank"', escape: false)
            ->assertSee('rel="nofollow noopener"', escape: false)
            // Лічильник рахує лише сторінки
            ->assertSee('1 сторінка');

        // Живий пошук по розділу знає і про кнопку
        $this->assertStringContainsString('x-show="match(1)"', $response->getContent());
    }

    public function test_featured_link_goes_to_key_actions_above_the_list(): void
    {
        $hub = $this->page('tsentr');
        $this->page('normatyvna-baza', ['parent_id' => $hub->id, 'title' => 'Нормативна база']);
        $this->link($hub, ['title' => 'Вакансія вгорі', 'is_featured' => true]);
        $this->link($hub, ['title' => 'Звичайна кнопка']);

        $html = $this->get('/tsentr')->assertOk()
            ->assertSeeInOrder(['Ключові дії', 'Вакансія вгорі', 'Усі сторінки розділу', 'Нормативна база', 'Звичайна кнопка'])
            ->assertSee('1 ключова дія')
            ->getContent();

        // Кнопка вгорі показується один раз і не бере участі в пошуку по списку
        $this->assertSame(1, substr_count($html, 'Вакансія вгорі'));
        $this->assertStringContainsString('x-show="match(1)"', $html);
        $this->assertStringNotContainsString('x-show="match(2)"', $html);
    }

    public function test_featured_link_on_ordinary_page_goes_above_the_text(): void
    {
        $page = $this->page('zvychaina');
        $this->link($page, ['title' => 'Кнопка вгорі', 'is_featured' => true]);
        $this->link($page, ['title' => 'Кнопка внизу']);

        $this->get('/zvychaina')->assertOk()
            ->assertSeeInOrder(['Кнопка вгорі', 'Текст сторінки.', 'Посилання', 'Кнопка внизу']);

        // Лише кнопка вгорі — без заголовка «Посилання» під текстом
        QuickLink::where('title', 'Кнопка внизу')->delete();
        $html = $this->get('/zvychaina')->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('~<h2[^>]*>\s*Посилання\s*</h2>~u', $html);
    }

    public function test_link_counts_towards_hub_search_threshold(): void
    {
        $hub = $this->page('tsentr');
        foreach (range(1, 5) as $i) {
            $this->page('dochirnya-'.$i, ['parent_id' => $hub->id]);
        }

        $this->get('/tsentr')->assertDontSee('id="hub-search"', escape: false);

        $this->link($hub);

        $this->get('/tsentr')->assertSee('id="hub-search"', escape: false);
    }

    public function test_hidden_links_and_links_of_other_pages_are_not_shown(): void
    {
        $hub = $this->page('tsentr');
        $this->page('dochirnya', ['parent_id' => $hub->id]);
        $other = $this->page('insha');
        $this->link($hub, ['title' => 'Прихована кнопка', 'is_visible' => false]);
        $this->link($other, ['title' => 'Чужа кнопка']);

        $this->get('/tsentr')->assertOk()->assertDontSee('Прихована кнопка')->assertDontSee('Чужа кнопка');
    }

    public function test_link_on_ordinary_page_appears_below_text_and_keeps_the_page_template(): void
    {
        $page = $this->page('zvychaina');
        $this->link($page);

        $this->get('/zvychaina')->assertOk()
            ->assertSeeInOrder(['Текст сторінки.', 'Посилання', 'Подати заявку'])
            ->assertDontSee('Усі сторінки розділу')
            ->assertSee('Не знайшли потрібну інформацію?');
    }

    public function test_page_with_only_links_has_no_empty_state(): void
    {
        $page = $this->page('bez-tekstu', ['body' => null]);

        $this->get('/bez-tekstu')->assertSee('Матеріали цієї сторінки');

        $this->link($page);

        $this->get('/bez-tekstu')->assertOk()->assertSee('Подати заявку')->assertDontSee('Матеріали цієї сторінки');
    }

    public function test_heritage_page_shows_links(): void
    {
        $page = $this->page('spadshchyna', ['is_heritage' => true]);
        $this->link($page);

        $this->get('/spadshchyna')->assertOk()->assertSee('Подати заявку');
    }

    public function test_internal_link_stays_in_the_same_tab_without_rel(): void
    {
        $page = $this->page('zvychaina');
        $this->link($page, ['url' => '/novyny', 'open_new_tab' => false]);

        $html = $this->get('/zvychaina')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('~<a href="[^"]*/novyny"\s+class="card card-interactive~', $html);

        $this->get('/en/zvychaina')->assertOk()->assertSee('/en/novyny', escape: false);
    }

    public function test_english_version_uses_published_translation_or_falls_back(): void
    {
        $page = $this->page('zvychaina');
        $link = $this->link($page);

        $this->get('/en/zvychaina')->assertOk()->assertSee('Подати заявку')->assertSee('opens in a new tab');

        $link->update(['title_en' => 'Submit an application', 'translation_published' => true]);

        $this->get('/en/zvychaina')->assertOk()->assertSee('Submit an application')->assertDontSee('Подати заявку');
    }

    public function test_links_do_not_leak_into_home_tiles_or_their_admin_list(): void
    {
        $this->actingAs(User::firstOrFail());
        $link = $this->link($this->page('tsentr'));

        $this->get('/')->assertOk()->assertDontSee('Подати заявку');
        Livewire::test(ListQuickLinks::class)->assertCanNotSeeTableRecords([$link]);
        Livewire::test(ListPageLinks::class)->assertCanSeeTableRecords([$link]);
    }

    public function test_editor_creates_link_with_correct_location_and_long_url(): void
    {
        $this->actingAs(User::factory()->editor()->create());
        $page = $this->page('tsentr');
        $url = 'https://docs.google.com/forms/d/e/'.str_repeat('a', 300).'/viewform';

        Livewire::test(CreatePageLink::class)
            ->fillForm(['title' => 'Вакансія', 'page_id' => $page->id, 'url' => '  '.$url.' ', 'is_featured' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $link = QuickLink::where('title', 'Вакансія')->firstOrFail();
        $this->assertSame(QuickLink::LOCATION_PAGE_LINK, $link->location);
        $this->assertSame($page->id, $link->page_id);
        $this->assertSame($url, $link->url);
        $this->assertTrue($link->open_new_tab);
        $this->assertTrue($link->is_visible);
        $this->assertTrue($link->is_featured);
    }

    public function test_form_rejects_unsafe_addresses_and_addresses_without_scheme(): void
    {
        $this->actingAs(User::firstOrFail());
        $page = $this->page('tsentr');

        foreach (['javascript:alert(1)', 'forms.gle/abc', 'data:text/html,x'] as $url) {
            Livewire::test(CreatePageLink::class)
                ->fillForm(['title' => 'Кнопка', 'page_id' => $page->id, 'url' => $url])
                ->call('create')
                ->assertHasFormErrors(['url']);
        }

        Livewire::test(CreatePageLink::class)
            ->fillForm(['title' => 'Кнопка', 'url' => 'https://example.com'])
            ->call('create')
            ->assertHasFormErrors(['page_id']);

        $this->assertSame(0, QuickLink::where('title', 'Кнопка')->count());
    }

    public function test_links_follow_a_deleted_page_to_its_parent(): void
    {
        $parent = $this->page('rozdil');
        $child = $this->page('pidrozdil', ['parent_id' => $parent->id]);
        $top = $this->page('verkhnia');
        $moved = $this->link($child);
        $orphan = $this->link($top);

        // Вікно видалення попереджає, куди перейдуть кнопки
        $this->assertStringContainsString('перейдуть до розділу', implode(' ', array_filter(SafeDeleteAction::consequences($child), fn ($line) => str_contains($line, 'Кнопок-посилань'))));
        $this->assertStringContainsString('без сторінки', implode(' ', SafeDeleteAction::consequences($top)));

        $child->delete();
        $top->delete();

        $this->assertSame($parent->id, $moved->fresh()->page_id);
        $this->assertNull($orphan->fresh()->page_id);
    }

    public function test_reorder_changes_order_of_links_on_the_page(): void
    {
        $this->actingAs(User::firstOrFail());
        $page = $this->page('zvychaina');
        $first = $this->link($page, ['title' => 'Перша кнопка']);
        $second = $this->link($page, ['title' => 'Друга кнопка']);

        Livewire::test(ListPageLinks::class)->call('reorderTable', [(string) $second->id, (string) $first->id]);

        $this->get('/zvychaina')->assertSeeInOrder(['Друга кнопка', 'Перша кнопка']);
    }
}
