<?php

namespace Tests\Feature;

use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\SpecialtyResource\Pages\EditSpecialty;
use App\Filament\Resources\StaffResource\Pages\EditStaff;
use App\Models\News;
use App\Models\Page;
use App\Models\Setting;
use App\Models\Specialty;
use App\Models\Staff;
use App\Models\User;
use App\Support\LocalizedHtml;
use App\Support\LocalizedUrl;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TranslationReviewRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_filament_saves_new_original_field_and_keeps_publication_hash_and_whole_fallback(): void
    {
        $this->actingAs(User::firstOrFail());
        $specialty = Specialty::create([
            'title' => 'Спеціальність оригіналу', 'title_en' => 'English reviewed specialty',
            'is_published' => true, 'translation_published' => true,
        ]);
        $hash = $specialty->translation_source_hash;
        $component = Livewire::test(EditSpecialty::class, ['record' => $specialty->slug]);
        $component->fillForm(['duration' => 'Новий термін'])->call('save')->assertHasNoFormErrors();
        $specialty->refresh();
        $this->assertSame('Новий термін', $specialty->duration);
        $this->assertTrue($specialty->translation_published);
        $this->assertSame($hash, $specialty->translation_source_hash);
        $this->assertTrue($specialty->translationIsStale());
        $this->get('/en/spetsialnosti/'.$specialty->slug)->assertOk()->assertSee('Новий термін')->assertDontSee('English reviewed specialty');
        Livewire::test(EditSpecialty::class, ['record' => $specialty->slug])
            ->fillForm(['sort_order' => 5])->call('save')->assertHasNoFormErrors();
        $this->assertSame($hash, $specialty->fresh()->translation_source_hash);
        Livewire::test(EditSpecialty::class, ['record' => $specialty->slug])
            ->fillForm(['title_en' => 'Edited English title'])->call('save')->assertHasFormErrors(['duration_en' => 'required']);
        Livewire::test(EditSpecialty::class, ['record' => $specialty->slug])
            ->fillForm(['duration_en' => 'New duration'])->call('save')->assertHasNoFormErrors();
        $this->assertFalse($specialty->fresh()->translationIsStale());
        $this->get('/en/spetsialnosti/'.$specialty->slug)->assertSee('English reviewed specialty')->assertSee('New duration');
    }

    public function test_filament_still_requires_complete_translation_when_enabling_publication(): void
    {
        $this->actingAs(User::firstOrFail());
        $specialty = Specialty::create(['title' => 'Оригінал', 'title_en' => 'Draft title', 'duration' => 'Новий термін']);
        Livewire::test(EditSpecialty::class, ['record' => $specialty->slug])
            ->fillForm(['translation_published' => true])->call('save')->assertHasFormErrors(['duration_en' => 'required']);
        $this->assertFalse($specialty->fresh()->translation_published);
        $this->assertNull($specialty->fresh()->translation_source_hash);
    }

    public function test_filament_saves_new_original_body_but_validates_english_and_seo_edits(): void
    {
        $this->actingAs(User::firstOrFail());
        $page = Page::create([
            'title' => 'Сторінка розділу', 'title_en' => 'English section title', 'slug' => 'review-section',
            'is_published' => true, 'translation_published' => true,
        ]);
        $hash = $page->translation_source_hash;
        Livewire::test(EditPage::class, ['record' => $page->slug])
            ->fillForm(['body' => '<p>Новий основний текст</p>'])->call('save')->assertHasNoFormErrors();
        $this->assertSame($hash, $page->fresh()->translation_source_hash);
        $this->assertTrue($page->fresh()->translation_published);
        $this->get('/en/'.$page->slug)->assertSee('Новий основний текст')->assertDontSee('English section title');
        Livewire::test(EditPage::class, ['record' => $page->slug])
            ->fillForm(['meta_title_en' => 'Edited SEO title'])->call('save')->assertHasFormErrors(['body_en' => 'required']);
        Livewire::test(EditPage::class, ['record' => $page->slug])
            ->fillForm(['body_en' => '<p>New English body</p>'])->call('save')->assertHasNoFormErrors();
        $this->assertFalse($page->fresh()->translationIsStale());
    }

    public function test_filament_saves_new_staff_biography_without_changing_the_english_translation(): void
    {
        $this->actingAs(User::firstOrFail());
        $staff = Staff::create(['full_name' => 'Українське Ім’я', 'full_name_en' => 'English Name', 'translation_published' => true]);
        $hash = $staff->translation_source_hash;
        Livewire::test(EditStaff::class, ['record' => $staff->id])
            ->fillForm(['bio' => 'Нова біографія'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Нова біографія', $staff->fresh()->bio);
        $this->assertSame($hash, $staff->fresh()->translation_source_hash);
        $this->assertFalse($staff->fresh()->hasPublishedEnglishTranslation());
    }

    public function test_heritage_date_and_signoff_use_public_locale_and_translated_brand(): void
    {
        config(['app.name' => 'Український бренд конфігурації']);
        $brand = Setting::where('key', 'brand_name')->firstOrFail();
        $brand->update(['value_en' => 'English College Brand', 'translation_published' => true]);
        $news = News::create([
            'title' => 'Архівна новина оригіналу', 'title_en' => 'English heritage news',
            'body' => '<p>Український архівний текст</p>', 'body_en' => '<p>English heritage text</p>',
            'published_at' => '2020-03-15', 'is_published' => true, 'is_heritage' => true, 'translation_published' => true,
        ]);
        $response = $this->get('/en/novyny/'.$news->slug)->assertOk()->assertSee('Odesa · 15 March 2020')->assertDontSee('15 березня 2020');
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $signoff = (new DOMXPath($dom))->query('//p[@class="heritage-signoff"]')->item(0)->textContent;
        $this->assertStringContainsString('English College Brand', $signoff);
        $this->assertStringNotContainsString('Український бренд конфігурації', $signoff);
        $this->get('/novyny/'.$news->slug)->assertOk()->assertSee('Одеса · 15 березня 2020')->assertSee('Український бренд конфігурації');
        $brand->update(['translation_published' => false]);
        $this->get('/en/novyny/'.$news->slug)->assertSee(__('layout.brand_name', [], 'en'))->assertDontSee('English College Brand');
        $this->assertSame('2020-03-15', $news->fresh()->published_at->toDateString());
    }

    public function test_absolute_home_links_preserve_query_fragment_and_distinguish_external_origins(): void
    {
        $this->get('/en/faq')->assertOk();
        $root = url('/');
        foreach (['?utm_source=test', '#section', '?x=1&y=2#section'] as $suffix) {
            $this->assertSame($root.'/en'.$suffix, LocalizedUrl::to($root.$suffix, 'en'));
            $this->assertSame($root.'/en'.$suffix, LocalizedUrl::to($root.'/'.$suffix, 'en'));
            $this->assertSame($root.$suffix, LocalizedUrl::to($root.'/en'.$suffix, 'uk'));
        }
        $this->assertSame($root.'/en?x=1', LocalizedUrl::to('http://LOCALHOST:80?x=1', 'en'));
        foreach (['https://localhost?x=1', 'http://localhost:81?x=1', 'http://localhost.example.test?x=1', 'http://user@localhost?x=1', 'http://localhost/storage/manual.pdf?x=1#file'] as $url) {
            $this->assertSame($url, LocalizedUrl::to($url, 'en'));
        }
        $html = '<a href="'.$root.'?x=1&amp;y=2#section">Home</a>';
        $this->assertSame('<a href="'.$root.'/en?x=1&amp;y=2#section">Home</a>', LocalizedHtml::links($html));
    }
}
