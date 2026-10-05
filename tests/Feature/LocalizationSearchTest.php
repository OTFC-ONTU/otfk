<?php

namespace Tests\Feature;

use App\Models\News;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_english_search_and_suggestions_find_published_translation_fields(): void
    {
        foreach ([News::class, Page::class] as $model) {
            $model::create([
                'title' => 'Назва оригіналу', 'slug' => 'search-translation', 'body' => 'Український текст',
                'title_en' => 'Maritime engineering', 'excerpt_en' => 'Robotics courses',
                'body_en' => '<p>Laboratory projects</p>', 'translation_published' => true, 'is_published' => true,
            ]);
        }
        foreach (['Maritime', 'Robotics', 'Laboratory'] as $term) {
            $this->get('/en/poshuk?q='.$term)->assertOk()->assertSee('Maritime engineering')
                ->assertSee('Robotics courses')->assertDontSee('Назва оригіналу');
            $response = $this->getJson('/en/poshuk/pidkazky?q='.$term)->assertOk()
                ->assertJsonPath('total', 2)->assertJsonPath('results.0.group', 'News')
                ->assertJsonPath('results.1.group', 'Page');
            foreach ($response->json('results') as $result) {
                $this->assertStringContainsString('/en/', $result['url']);
                $this->assertSame('Maritime engineering', $result['title']);
            }
        }
        $this->getJson('/poshuk/pidkazky?q=Maritime')->assertJsonPath('total', 0);
        $this->getJson('/poshuk/pidkazky?q=Назва')->assertJsonPath('total', 2)
            ->assertJsonPath('results.0.group', 'Новина')->assertJsonPath('results.0.title', 'Назва оригіналу');
    }

    public function test_drafts_unpublished_future_and_incomplete_translations_are_not_searchable(): void
    {
        foreach ([News::class, Page::class] as $model) {
            foreach ([false, true] as $published) {
                $model::create([
                    'slug' => 'search-visibility-'.(int) $published,
                    'title' => 'Резервний заголовок', 'body' => 'Текст', 'title_en' => 'Secretdraft headline',
                    'body_en' => 'Secretdraft body', 'is_published' => $published,
                    'translation_published' => ! $published,
                ]);
                if ($published) {
                    $this->getJson('/en/poshuk/pidkazky?q=Резервний')->assertOk()
                        ->assertJsonFragment(['title' => 'Резервний заголовок']);
                }
            }
            $incomplete = $model::create(['title' => 'Резервний неповний', 'body' => 'Текст',
                'title_en' => 'Secretdraft incomplete', 'is_published' => true]);
            // Зовнішній SQL може обійти валідацію: пошук усе одно не відкриває неповний переклад.
            $model::whereKey($incomplete->id)->update(['translation_published' => true]);
        }
        News::create(['title' => 'Майбутня новина', 'body' => 'Текст', 'title_en' => 'Secretdraft future',
            'body_en' => 'Future body', 'is_published' => true, 'translation_published' => true, 'published_at' => now()->addDay()]);
        $this->getJson('/en/poshuk/pidkazky?q=Secretdraft')->assertOk()->assertJsonPath('total', 0);
        $this->get('/en/poshuk?q=Secretdraft')->assertOk()->assertSee('No results found')
            ->assertDontSee('Secretdraft headline')->assertDontSee('Secretdraft future');
        $this->getJson('/en/poshuk/pidkazky?q=Резервний')->assertOk()->assertJsonPath('total', 4);
    }

    public function test_english_search_uses_the_same_language_as_the_material_and_keeps_stale_translations(): void
    {
        $page = Page::create(['title' => 'Український прихований заголовок', 'body' => 'Текст',
            'title_en' => 'Publishedastronomy', 'body_en' => 'English body', 'is_published' => true, 'translation_published' => true]);
        $this->getJson('/en/poshuk/pidkazky?q=прихований')->assertJsonPath('total', 0);
        $page->update(['body' => 'Оновлений текст']);
        $this->getJson('/en/poshuk/pidkazky?q=Publishedastronomy')->assertJsonPath('total', 1);
        $page->update(['translation_published' => false]);
        $this->getJson('/en/poshuk/pidkazky?q=Publishedastronomy')->assertJsonPath('total', 0);
        $this->getJson('/en/poshuk/pidkazky?q=прихований')->assertJsonPath('total', 1);
    }

    public function test_search_keeps_minimum_query_length_and_does_not_accept_sql_fragments(): void
    {
        $this->getJson('/en/poshuk/pidkazky?q=a')->assertJsonPath('total', 0);
        $this->getJson('/en/poshuk/pidkazky?q='.urlencode("' OR 1=1 --"))->assertOk()->assertJsonPath('total', 0);
    }
}
