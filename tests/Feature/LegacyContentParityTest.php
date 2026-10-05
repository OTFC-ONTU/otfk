<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Page;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LegacyContentParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_contacts_page_shows_cms_page_text_instead_of_settings_block(): void
    {
        $this->get('/kontakty')->assertOk()->assertSee(__('public.contact_us'));

        Page::create([
            'title' => 'Контакти', 'slug' => 'kontakty', 'is_published' => true,
            'body' => '<p>Поштова адреса коледжу для подання інформаційного запиту:</p>',
            'title_en' => 'Contacts', 'body_en' => '<p>Postal address of the college for information requests:</p>',
            'translation_published' => true,
        ]);

        $this->get('/kontakty')->assertOk()
            ->assertSee('Поштова адреса коледжу для подання інформаційного запиту:')
            ->assertDontSee(__('public.contact_intro'))
            ->assertSee('name="message"', false);
        $this->get('/en/kontakty')->assertOk()->assertSee('Postal address of the college for information requests:');
    }

    public function test_empty_footer_about_hides_footer_description(): void
    {
        Setting::updateOrCreate(['key' => 'footer_about'], ['value' => 'Опис у підвалі', 'type' => 'textarea']);
        Cache::flush();
        $this->get('/')->assertOk()->assertSee('Опис у підвалі');

        Setting::where('key', 'footer_about')->update(['value' => null]);
        Cache::flush();
        $this->get('/')->assertOk()
            ->assertDontSee('Опис у підвалі')
            ->assertDontSee(__('layout.about'));
    }

    public function test_document_title_longer_than_255_characters_is_stored_in_full(): void
    {
        $title = str_repeat('Результати опитування здобувачів освіти щодо якості освітньої програми ', 6);
        $category = DocumentCategory::query()->first() ?? DocumentCategory::create(['title' => 'Звіти', 'slug' => 'zvity']);

        $document = Document::create(['document_category_id' => $category->id, 'title' => $title, 'external_url' => 'https://example.org/a.pdf']);

        $this->assertGreaterThan(255, mb_strlen($title));
        $this->assertSame($title, $document->fresh()->title);
    }
}
