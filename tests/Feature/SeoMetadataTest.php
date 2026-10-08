<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Department;
use App\Models\News;
use App\Models\Page;
use App\Models\Setting;
use App\Models\Specialty;
use App\Models\Staff;
use App\Support\MetaText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * SEO етап 2 (docs/seo-plan.md): унікальні title/description, бренд без повтору,
 * один H1 на сторінці, шлях «спеціальність → умови вступу → контакти».
 */
class SeoMetadataTest extends TestCase
{
    use RefreshDatabase;

    private function titleOf(TestResponse $response): string
    {
        preg_match('/<title>(.*?)<\/title>/s', $response->getContent(), $m);

        return html_entity_decode($m[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function descriptionOf(TestResponse $response): string
    {
        preg_match('/<meta name="description" content="([^"]*)"/', $response->getContent(), $m);

        return html_entity_decode($m[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function assertSingleH1(TestResponse $response, string $url): void
    {
        $this->assertSame(1, preg_match_all('/<h1\b/i', $response->getContent()), "Сторінка {$url} має містити рівно один <h1>");
    }

    private function specialty(array $attributes = []): Specialty
    {
        return Specialty::create(array_merge([
            'title' => 'Комп’ютерна інженерія',
            'slug' => 'kompyuterna-inzheneriya-seo',
            'code' => '123',
            'short_description' => 'Проєктування й обслуговування компʼютерних систем і мереж.',
            'description' => '<p>Розгорнутий опис спеціальності.</p>',
            'degree' => 'Фаховий молодший бакалавр',
            'study_form' => 'Денна',
            'duration' => '3 р. 10 міс.',
            'is_published' => true,
        ], $attributes));
    }

    public function test_meta_text_strips_html_comments_and_limits_by_word(): void
    {
        $html = '<!--imported-from:https://old/x--><h2>Заголовок</h2><p>Перший&nbsp;абзац</p><script>alert(1)</script><p>Другий</p>';
        $this->assertSame('Заголовок Перший абзац Другий', MetaText::from(null, '', $html));
        $this->assertNull(MetaText::from(null, '<p> </p>'));

        $long = MetaText::from('<p>'.str_repeat('слово ', 60).'</p>');
        $this->assertLessThanOrEqual(MetaText::LIMIT, mb_strlen($long));
        $this->assertStringEndsWith('слово…', $long);
    }

    public function test_title_appends_brand_once(): void
    {
        $this->assertSame('Новини — ОТФК ОНТУ', MetaText::title('Новини', 'Сайт', 'ОТФК ОНТУ'));
        $this->assertSame('Історія ОТФК ОНТУ', MetaText::title('Історія ОТФК ОНТУ', 'Сайт', 'ОТФК ОНТУ'));
        // Абревіатура з довшого бренду теж вважається повтором, а «ВСП» — ні
        $this->assertSame('Вступ до ОТФК', MetaText::title('Вступ до ОТФК', 'Сайт', 'ВСП ОТФК ОНТУ'));
        $this->assertSame('ВСП інформує — ВСП ОТФК ОНТУ', MetaText::title('ВСП інформує', 'Сайт', 'ВСП ОТФК ОНТУ'));
        $this->assertSame('Про нас | Одеський технічний фаховий коледж', MetaText::title('Про нас | Одеський технічний фаховий коледж', 'Сайт', 'ОТФК ОНТУ', ['Одеський технічний фаховий коледж']));
        $this->assertSame('Сайт', MetaText::title(null, 'Сайт', 'ОТФК ОНТУ'));
    }

    public function test_specialty_title_description_h1_and_next_step_links(): void
    {
        $specialty = $this->specialty();

        $response = $this->get(route('specialties.show', $specialty))->assertOk();
        $this->assertSame('Комп’ютерна інженерія в Одесі — ОТФК ОНТУ', $this->titleOf($response));
        $this->assertSame('Проєктування й обслуговування компʼютерних систем і мереж.', $this->descriptionOf($response));
        $this->assertSingleH1($response, 'specialty');

        // Наступний крок: умови вступу й контакти — звичайні HTML-посилання
        $response->assertSee('data-next-step', false)
            ->assertSee('Умови вступу та документи')
            ->assertSee('href="'.url('/abituriyentu').'"', false)
            ->assertSee('href="'.route('contacts').'"', false)
            // Фактичні відомості — текстом у HTML
            ->assertSee('Код спеціальності')->assertSee('123')
            ->assertSee('Фаховий молодший бакалавр')->assertSee('3 р. 10 міс.');

        // Без короткого опису — очищений розгорнутий опис
        $specialty->update(['short_description' => null]);
        $this->assertSame('Розгорнутий опис спеціальності.', $this->descriptionOf($this->get(route('specialties.show', $specialty))));
    }

    public function test_english_specialty_title_and_next_step(): void
    {
        $specialty = Specialty::create([
            'title' => 'Харчові технології', 'slug' => 'kharchovi-seo', 'code' => '181',
            'short_description' => 'Український опис.',
            'title_en' => 'Food Technology', 'short_description_en' => 'Food production technology studies.',
            'translation_published' => true, 'is_published' => true,
        ]);

        $response = $this->get('/en/spetsialnosti/'.$specialty->slug)->assertOk();
        $this->assertSame('Food Technology in Odesa — OTPC ONTU', $this->titleOf($response));
        $this->assertSame('Food production technology studies.', $this->descriptionOf($response));
        $response->assertSee('Admission requirements and documents')
            ->assertSee('href="'.url('/en/abituriyentu').'"', false)
            ->assertSee('href="'.url('/en/kontakty').'"', false);
        $this->assertSingleH1($response, 'en specialty');
    }

    public function test_news_description_falls_back_to_stripped_body(): void
    {
        $news = News::create([
            'title' => 'День відкритих дверей', 'slug' => 'den-vidkrytykh-dverei-seo',
            'body' => '<p>Запрошуємо <strong>вступників</strong> на день відкритих дверей.</p>',
            'published_at' => now()->subDay(), 'is_published' => true,
        ]);

        $response = $this->get(route('news.show', $news))->assertOk();
        $this->assertSame('День відкритих дверей — ОТФК ОНТУ', $this->titleOf($response));
        $this->assertSame('Запрошуємо вступників на день відкритих дверей.', $this->descriptionOf($response));
        $this->assertSingleH1($response, 'news');

        $news->update(['excerpt' => 'Короткий анонс події.']);
        $this->assertSame('Короткий анонс події.', $this->descriptionOf($this->get(route('news.show', $news))));
    }

    public function test_page_description_chain_and_meta_title_without_duplicate_brand(): void
    {
        $page = Page::create([
            'title' => 'Правила прийому', 'slug' => 'pravyla-pryiomu-seo',
            'body' => '<h2>Загальні положення</h2><p>Прийом здійснюється за правилами.</p>',
            'is_published' => true,
        ]);

        $response = $this->get('/'.$page->slug)->assertOk();
        $this->assertSame('Правила прийому — ОТФК ОНТУ', $this->titleOf($response));
        $this->assertSame('Загальні положення Прийом здійснюється за правилами.', $this->descriptionOf($response));
        $this->assertSingleH1($response, 'page');

        $page->update(['excerpt' => 'Анонс сторінки.']);
        $this->assertSame('Анонс сторінки.', $this->descriptionOf($this->get('/'.$page->slug)));

        $page->update(['meta_title' => 'Правила прийому до ОТФК ОНТУ', 'meta_description' => 'SEO-опис сторінки.']);
        $response = $this->get('/'.$page->slug);
        $this->assertSame('Правила прийому до ОТФК ОНТУ', $this->titleOf($response));
        $this->assertSame('SEO-опис сторінки.', $this->descriptionOf($response));
    }

    public function test_staff_description_uses_position_and_department(): void
    {
        $department = Department::create(['title' => 'Комісія інформатики', 'type' => 'tsyklova-komisiya', 'is_published' => true]);
        $staff = Staff::create([
            'full_name' => 'Петренко Ольга Іванівна', 'position' => 'викладач',
            'category' => 'teacher', 'department_id' => $department->id, 'is_published' => true,
        ]);

        $response = $this->get(route('staff.show', $staff))->assertOk();
        $this->assertSame('Петренко Ольга Іванівна — ОТФК ОНТУ', $this->titleOf($response));
        $this->assertStringContainsString('викладач, Комісія інформатики', $this->descriptionOf($response));
        $this->assertSingleH1($response, 'staff');

        $department = $this->get(route('structure.show', $department))->assertOk();
        $this->assertStringContainsString('Комісія інформатики', $this->descriptionOf($department));
        $this->assertSingleH1($department, 'department');
    }

    public function test_index_pages_have_specific_descriptions_in_both_locales(): void
    {
        $generic = [__('layout.description'), Setting::publicGet('site_description')];

        foreach (['/spetsialnosti', '/struktura', '/kviz', '/administratsiya', '/novyny'] as $path) {
            foreach (['', '/en'] as $prefix) {
                $response = $this->get($prefix.$path)->assertOk();
                $description = $this->descriptionOf($response);
                $this->assertNotSame('', $description, $prefix.$path);
                $this->assertNotContains($description, $generic, $prefix.$path);
                $this->assertSingleH1($response, $prefix.$path);
            }
        }

        $this->assertStringContainsString('Specialties of Odesa', $this->descriptionOf($this->get('/en/spetsialnosti')));
        $this->assertStringNotContainsString('Спеціальності', $this->descriptionOf($this->get('/en/spetsialnosti')));
    }

    public function test_home_has_brand_title_single_h1_and_admission_links(): void
    {
        foreach (['Вступ 2026', 'День відкритих дверей'] as $i => $title) {
            Banner::create(['title' => $title, 'image' => "banners/seo-{$i}.jpg", 'is_published' => true, 'sort_order' => $i]);
        }

        $response = $this->get('/')->assertOk();
        $this->assertStringNotContainsString(' — ', $this->titleOf($response));
        $this->assertSingleH1($response, 'home');
        $response->assertSee('href="'.url('/abituriyentu').'"', false)
            ->assertSee('href="'.route('specialties.index').'"', false);

        foreach (['/kontakty', '/neisnuyucha-storinka-seo'] as $path) {
            $this->assertSingleH1($this->get($path), $path);
        }
    }
}
