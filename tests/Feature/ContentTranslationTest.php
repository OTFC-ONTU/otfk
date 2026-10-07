<?php

namespace Tests\Feature;

use App\Filament\Resources\NewsResource\Pages\EditNews;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Models\News;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ContentTranslationTest extends TestCase
{
    use RefreshDatabase;

    private function material(string $model, array $overrides = []): Page|News
    {
        return $model::create(array_merge([
            'title' => 'Оригінальний заголовок', 'slug' => 'translation-contract',
            'excerpt' => 'Оригінальний опис', 'body' => '<p>Оригінальний текст</p>',
            'is_published' => true,
        ], $overrides));
    }

    public function test_published_translation_is_used_only_on_english_urls_and_original_stays_intact(): void
    {
        foreach ([Page::class => '/translation-contract', News::class => '/novyny/translation-contract'] as $model => $path) {
            $material = $this->material($model, [
                'title_en' => 'Translated headline', 'excerpt_en' => 'Translated excerpt',
                'body_en' => '<p>Translated body <a href="/kontakty#map">Contact</a><img src="/storage/test.jpg"></p>',
                'translation_published' => true,
            ]);
            $this->get('/en'.$path)->assertOk()->assertSee('Translated headline')
                ->assertSee('Translated body')->assertSee('Translated excerpt')
                ->assertSee('/en/kontakty#map', false)->assertSee('/storage/test.jpg', false)
                ->assertDontSee('Оригінальний текст')->assertDontSee('Оригінальний опис');
            $this->get($path)->assertOk()->assertSee('Оригінальний текст')->assertDontSee('Translated body');
            $this->assertSame('<p>Оригінальний текст</p>', $material->fresh()->body);
            $this->assertStringContainsString('href="/kontakty#map"', $material->fresh()->body_en);
        }
    }

    public function test_drafts_and_absent_translations_fall_back_as_a_whole(): void
    {
        foreach ([Page::class => '/translation-contract', News::class => '/novyny/translation-contract'] as $model => $path) {
            $material = $this->material($model);
            $this->get('/en'.$path)->assertOk()->assertSee('Оригінальний заголовок')->assertSee('Оригінальний текст');
            $material->update(['title_en' => 'Hidden draft', 'excerpt_en' => 'Hidden excerpt']);
            $this->get('/en'.$path)->assertOk()->assertSee('Оригінальний заголовок')
                ->assertSee('Оригінальний опис')->assertSee('Оригінальний текст')->assertDontSee('Hidden draft');
        }
    }

    public function test_optional_english_fields_do_not_mix_with_ukrainian_fields(): void
    {
        $this->material(Page::class, [
            'title_en' => 'English title only', 'body_en' => '<p>English body</p>',
            'translation_published' => true, 'meta_title' => 'Український SEO заголовок',
            'meta_description' => 'Український SEO опис',
        ]);
        $this->get('/en/translation-contract')->assertOk()->assertSee('English title only')
            ->assertDontSee('Оригінальний опис')->assertDontSee('Український SEO заголовок')->assertDontSee('Український SEO опис');
    }

    public function test_source_changes_mark_translation_stale_without_unpublishing_or_rewriting_it(): void
    {
        foreach ([Page::class, News::class] as $model) {
            $material = $this->material($model, [
                'title_en' => 'Published title', 'body_en' => '<p>Published body</p>', 'translation_published' => true,
            ]);
            $hash = $material->translation_source_hash;
            $this->assertFalse($material->translationIsStale());
            $material->update(['title' => 'Змінений оригінал', 'body' => '<p>Новий оригінал</p>']);
            $material->refresh();
            $this->assertTrue($material->translationIsStale());
            $this->assertTrue($material->translation_published);
            $this->assertSame($hash, $material->translation_source_hash);
            app()->setLocale('en');
            $this->assertSame('<p>Published body</p>', $material->localized('body'));
            $material->update($model === News::class ? ['views' => 1] : ['sort_order' => 1]);
            $this->assertSame($hash, $material->translation_source_hash);
            $material->update(['body_en' => '<p>Updated translation</p>']);
            $this->assertFalse($material->fresh()->translationIsStale());
            $this->assertNotSame($hash, $material->translation_source_hash);
        }
    }

    public function test_partial_translations_cannot_be_published_but_section_pages_need_no_body(): void
    {
        foreach ([Page::class, News::class] as $model) {
            $material = $this->material($model, ['title_en' => 'Partial draft']);
            try {
                $material->update(['translation_published' => true]);
                $this->fail('Incomplete translation was published');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('translation_published', $exception->errors());
            }
            $this->assertFalse($material->fresh()->translation_published);
        }
        $section = Page::create(['title' => 'Розділ', 'slug' => 'section', 'title_en' => 'Section', 'translation_published' => true]);
        $this->assertTrue($section->hasPublishedEnglishTranslation());
    }

    public function test_cards_parent_child_breadcrumbs_and_feed_use_published_translations(): void
    {
        $parent = $this->material(Page::class, ['title_en' => 'English parent', 'body_en' => 'Parent body', 'translation_published' => true]);
        $child = Page::create(['parent_id' => $parent->id, 'title' => 'Дочірня сторінка', 'slug' => 'translated-child',
            'title_en' => 'English child', 'is_published' => true, 'translation_published' => true]);
        $news = $this->material(News::class, ['title_en' => 'English news card', 'excerpt_en' => 'English card excerpt',
            'body_en' => 'English article', 'translation_published' => true, 'published_at' => now()->subMinute()]);
        $this->get('/en/'.$parent->slug)->assertOk()->assertSee('English child');
        $this->get('/en/'.$child->slug)->assertOk()->assertSee('English parent');
        foreach (['/en', '/en/novyny', '/en/novyny/feed.xml'] as $path) {
            $this->get($path)->assertOk()->assertSee('English news card')->assertSee('English card excerpt');
        }
        $news->update(['translation_published' => false]);
        $this->get('/en/novyny/feed.xml')->assertOk()->assertDontSee('English news card')->assertSee('Оригінальний заголовок');
    }

    public function test_filament_saves_sanitized_html_and_validates_publication_for_both_resources(): void
    {
        $this->actingAs(User::firstOrFail());
        foreach ([Page::class => EditPage::class, News::class => EditNews::class] as $model => $component) {
            $material = $this->material($model);
            $html = '<div class="original-layout"><table><tr><td>English</td></tr></table><img src="/storage/photo.jpg"><a href="/kontakty">Contact</a></div>';
            Livewire::test($component, ['record' => $material->slug])
                ->fillForm(['translation_published' => true, 'title_en' => 'Admin translation', 'body_en' => ''])
                ->call('save')->assertHasFormErrors(['body_en' => 'required']);
            Livewire::test($component, ['record' => $material->slug])
                ->fillForm(['translation_published' => true, 'title_en' => 'Admin translation', 'body_en' => $html])
                ->call('save')->assertHasNoFormErrors();
            // Збережений HTML — після SafeHtml: структура, класи, посилання й файли ті самі, розмітка нормалізована.
            $this->assertSame(
                str_replace(['<table><tr>', '</tr></table>', '<img src="/storage/photo.jpg">'], ['<table><tbody><tr>', '</tr></tbody></table>', '<img src="/storage/photo.jpg" />'], $html),
                $material->fresh()->body_en,
            );
            $this->assertTrue($material->fresh()->translation_published);
        }
    }
}
