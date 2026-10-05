<?php

namespace Tests\Feature;

use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Filament\Resources\FaqResource\Pages\EditFaq;
use App\Filament\Resources\NewsCategoryResource\Pages\EditNewsCategory;
use App\Models\Event;
use App\Models\Faq;
use App\Models\News;
use App\Models\NewsCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SupportingContentTranslationTest extends TestCase
{
    use RefreshDatabase;

    private function faq(): Faq
    {
        return Faq::create([
            'question' => 'Українське питання', 'answer' => 'Українська відповідь', 'is_active' => true,
            'question_en' => 'English question', 'answer_en' => "English answer\n<script>plain text</script>",
            'translation_published' => true,
        ]);
    }

    private function event(): Event
    {
        return Event::create([
            'title' => 'Українська подія', 'description' => 'Український опис події', 'location' => 'Українська зала',
            'title_en' => 'English event', 'description_en' => 'English description', 'location_en' => 'English hall',
            'starts_at' => now()->addDays(2)->setTime(12, 0), 'ends_at' => now()->addDays(2)->setTime(14, 0),
            'is_published' => true, 'translation_published' => true,
        ]);
    }

    public function test_faq_translates_visible_text_and_structured_data_and_keeps_plain_text_escaped(): void
    {
        $faq = $this->faq();
        $this->get('/en/faq')->assertOk()->assertSee('English question')->assertSee('English answer')
            ->assertSee('"name":"English question"', false)->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>plain text</script>', false)->assertDontSee('Українське питання');
        $this->get('/faq')->assertOk()->assertSee('Українське питання')->assertDontSee('English question');
        $faq->update(['translation_published' => false]);
        $this->get('/en/faq')->assertSee('Українське питання')->assertSee('Українська відповідь')->assertDontSee('English answer');
        $faq->update(['is_active' => false]);
        $this->get('/en/faq')->assertDontSee('Українське питання')->assertDontSee('English question');
    }

    public function test_events_translate_home_lists_metadata_and_both_calendar_formats(): void
    {
        $event = $this->event();
        foreach (['/en', '/en/podiyi'] as $path) {
            $this->get($path)->assertOk()->assertSee('English event')->assertSee('English description')
                ->assertSee('English hall')->assertDontSee('Українська подія');
        }
        $this->get('/en/podiyi')->assertSee('"name":"English event"', false);
        $english = $this->get('/en/podiyi/'.$event->id.'/ics')->assertOk()
            ->assertSee('SUMMARY:English event', false)->assertSee('LOCATION:English hall', false)
            ->assertSee('DESCRIPTION:English description', false)->assertSee('/en/podiyi', false);
        $this->get('/podiyi/'.$event->id.'/ics')->assertOk()->assertSee('SUMMARY:Українська подія', false);
        app()->setLocale('en');
        parse_str(parse_url($event->google_calendar_url, PHP_URL_QUERY), $calendar);
        $this->assertSame('English event', $calendar['text']);
        $this->assertSame('English hall', $calendar['location']);
        $this->assertSame('English description', $calendar['details']);
        $this->assertStringContainsString('DTSTART:'.$event->utcStart()->format('Ymd\THis\Z'), $english->getContent());
        $this->assertSame('Українська подія', $event->fresh()->title);
        $event->update(['starts_at' => now()->subDays(3), 'ends_at' => now()->subDays(3)->addHour()]);
        $this->get('/en/podiyi')->assertSee('English event');
        $this->get('/en')->assertDontSee('English event');
        $event->update(['is_published' => false]);
        $this->get('/en/podiyi')->assertDontSee('English event');
        $this->get('/en/podiyi/'.$event->id.'/ics')->assertNotFound();
    }

    public function test_incomplete_event_translation_falls_back_as_a_whole_in_calendars_and_page(): void
    {
        $event = $this->event();
        Event::whereKey($event->id)->update(['location_en' => ' ']);
        $this->get('/en/podiyi')->assertSee('Українська подія')->assertSee('Український опис події')
            ->assertSee('Українська зала')->assertDontSee('English event')->assertDontSee('English description');
        $this->get('/en/podiyi/'.$event->id.'/ics')->assertSee('SUMMARY:Українська подія', false)
            ->assertSee('LOCATION:Українська зала', false)->assertDontSee('English description');
        app()->setLocale('en');
        parse_str(parse_url($event->fresh()->google_calendar_url, PHP_URL_QUERY), $calendar);
        $this->assertSame('Українська подія', $calendar['text']);
    }

    public function test_news_category_translates_filters_cards_details_without_changing_slug_or_news(): void
    {
        $category = NewsCategory::create([
            'title' => 'Українська категорія', 'slug' => 'supporting-category',
            'title_en' => 'English category', 'translation_published' => true,
        ]);
        $news = News::create([
            'title' => 'Новина категорії', 'slug' => 'supporting-news', 'category_id' => $category->id,
            'is_published' => true, 'published_at' => now()->subDay(),
        ]);
        foreach (['/en/novyny?category='.$category->slug, '/en/novyny/'.$news->slug] as $path) {
            $this->get($path)->assertOk()->assertSee('English category')->assertSee('Новина категорії')
                ->assertDontSee('Українська категорія');
        }
        $this->get('/novyny')->assertSee('Українська категорія')->assertDontSee('English category');
        $category->update(['translation_published' => false]);
        $this->get('/en/novyny')->assertSee('Українська категорія')->assertDontSee('English category');
        $this->assertSame('supporting-category', $category->fresh()->slug);
    }

    public function test_publication_requires_all_populated_fields_and_source_changes_preserve_hash(): void
    {
        foreach ([$this->faq(), $this->event(), NewsCategory::create([
            'title' => 'Категорія', 'title_en' => 'Category', 'translation_published' => true,
        ])] as $record) {
            $primary = $record instanceof Faq ? 'question' : 'title';
            $required = match (true) {
                $record instanceof Faq => ['question', 'answer'],
                $record instanceof Event => ['title', 'description', 'location'],
                default => ['title'],
            };
            foreach ($required as $field) {
                try {
                    $record->fresh()->update([$field.'_en' => ' ']);
                    $this->fail('Incomplete translation was published');
                } catch (ValidationException $exception) {
                    $this->assertArrayHasKey('translation_published', $exception->errors());
                }
            }
            $hash = $record->translation_source_hash;
            $record->update([$primary => 'Змінений оригінал']);
            $this->assertSame($hash, $record->translation_source_hash);
            $this->assertTrue($record->translationIsStale());
            $this->assertTrue($record->hasPublishedEnglishTranslation());
            $record->update([$primary.'_en' => 'Updated translation']);
            $this->assertFalse($record->translationIsStale());
            $record->update(['translation_published' => false]);
            $this->assertNotNull($record->translation_source_hash);
        }
    }

    public function test_question_primary_field_works_for_sql_completeness_and_locale_search(): void
    {
        $faq = $this->faq();
        app()->setLocale('en');
        $this->assertTrue(Faq::searchPublic('English answer')->whereKey($faq->id)->exists());
        $this->assertFalse(Faq::searchPublic('Українське')->whereKey($faq->id)->exists());
        Faq::whereKey($faq->id)->update(['answer_en' => null]);
        $this->assertFalse(Faq::withPublishedEnglishTranslation()->whereKey($faq->id)->exists());
        $this->assertFalse(Faq::searchPublic('English')->whereKey($faq->id)->exists());
        $this->assertTrue(Faq::searchPublic('Українське')->whereKey($faq->id)->exists());
        app()->setLocale('uk');
        $this->assertTrue(Faq::searchPublic('Українське')->whereKey($faq->id)->exists());
    }

    public function test_event_suggestions_search_the_visible_language_and_exclude_hidden_and_past_events(): void
    {
        $event = $this->event();
        $title = 'English event ('.$event->starts_at->format('d.m').')';
        $this->get('/en/poshuk/pidkazky?q=English description')->assertOk()
            ->assertJsonFragment(['title' => $title, 'url' => url('/en/podiyi')]);
        $this->get('/en/poshuk/pidkazky?q=Українська')->assertJsonMissing(['title' => $title]);
        $this->get('/poshuk/pidkazky?q=English')->assertJsonMissing(['title' => $title]);
        $event->update(['translation_published' => false]);
        $this->get('/en/poshuk/pidkazky?q=English')->assertJsonMissing(['title' => $title]);
        $this->get('/en/poshuk/pidkazky?q=Українська')->assertJsonFragment([
            'title' => 'Українська подія ('.$event->starts_at->format('d.m').')',
        ]);
        $event->update(['translation_published' => true, 'is_published' => false]);
        $this->get('/en/poshuk/pidkazky?q=English')->assertJsonMissing(['title' => $title]);
        $event->update(['is_published' => true, 'starts_at' => now()->subDays(2), 'ends_at' => null]);
        $this->get('/en/poshuk/pidkazky?q=English')->assertJsonMissing(['url' => url('/en/podiyi')]);
    }

    public function test_filament_validates_and_saves_all_three_translation_forms(): void
    {
        $this->actingAs(User::firstOrFail());
        $faq = $this->faq();
        $event = $this->event();
        $category = NewsCategory::create(['title' => 'Категорія']);
        foreach ([[$faq, EditFaq::class, 'question', ['answer_en' => "Answer\nSecond line"]],
            [$event, EditEvent::class, 'title', ['description_en' => 'Description', 'location_en' => 'Hall']],
            [$category, EditNewsCategory::class, 'title', []]] as [$record, $component, $primary, $fields]) {
            Livewire::test($component, ['record' => $record->getRouteKey()])
                ->fillForm(['translation_published' => true, $primary.'_en' => ''])
                ->call('save')->assertHasFormErrors([$primary.'_en' => 'required']);
            if ($fields) {
                $required = array_key_first($fields);
                Livewire::test($component, ['record' => $record->getRouteKey()])
                    ->fillForm(['translation_published' => true, $primary.'_en' => 'Admin translation', $required => ''])
                    ->call('save')->assertHasFormErrors([$required => 'required']);
            }
            Livewire::test($component, ['record' => $record->getRouteKey()])
                ->fillForm(array_merge($fields, ['translation_published' => true, $primary.'_en' => 'Admin translation']))
                ->call('save')->assertHasNoFormErrors();
            $this->assertSame('Опубліковано', $record->fresh()->translationStatus());
            foreach ($fields as $field => $value) {
                $this->assertSame($value, $record->fresh()->getAttribute($field));
            }
        }
    }
}
