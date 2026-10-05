<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HostingSchemaRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function recovery(): object
    {
        return require database_path('migrations/2026_10_04_175000_restore_missing_content_tables.php');
    }

    private function translations(): object
    {
        return require database_path('migrations/2026_10_04_180000_add_english_content_to_home_blocks_and_settings.php');
    }

    public function test_missing_tables_are_restored_without_seeding_or_deleting_content(): void
    {
        $pages = DB::table('pages')->get()->toArray();
        foreach (['testimonials', 'applicant_requests', 'feedback_messages'] as $name) {
            Schema::drop($name);
        }

        $this->recovery()->up();
        $this->translations()->up();
        $this->recovery()->down();

        foreach (['testimonials', 'applicant_requests', 'feedback_messages'] as $name) {
            $this->assertTrue(Schema::hasTable($name));
            $this->assertSame(0, DB::table($name)->count());
        }
        $this->assertTrue(Schema::hasColumns('testimonials', ['name_en', 'role_en', 'quote_en', 'translation_published']));
        $this->assertEquals($pages, DB::table('pages')->get()->toArray());
        $specialty = DB::table('specialties')->first();
        $request = DB::table('applicant_requests')->insertGetId(['name' => 'Заявка', 'phone' => '123', 'specialty_id' => $specialty->id]);
        DB::table('specialties')->where('id', $specialty->id)->delete();
        $this->assertNull(DB::table('applicant_requests')->where('id', $request)->value('specialty_id'));
    }

    public function test_existing_tables_and_rows_survive_repeated_recovery(): void
    {
        DB::table('applicant_requests')->insert(['name' => 'Заявка', 'phone' => '123', 'message' => 'Збережені дані']);
        DB::table('feedback_messages')->insert(['name' => 'Звернення', 'message' => 'Збережені дані']);
        $before = [];
        foreach (['testimonials', 'applicant_requests', 'feedback_messages'] as $name) {
            $before[$name] = DB::table($name)->get()->toArray();
        }

        $this->recovery()->up();
        $this->recovery()->up();
        $this->recovery()->down();

        foreach ($before as $name => $rows) {
            $this->assertEquals($rows, DB::table($name)->get()->toArray());
        }
    }

    public function test_partially_added_translation_columns_resume_and_preserve_translations(): void
    {
        $banner = DB::table('banners')->first();
        DB::table('banners')->where('id', $banner->id)->update(['title_en' => 'Saved translation', 'translation_published' => true, 'translation_source_hash' => str_repeat('a', 64)]);
        Schema::table('banners', fn ($table) => $table->dropColumn('subtitle_en'));
        Schema::table('quick_links', fn ($table) => $table->dropColumn(['title_en', 'description_en', 'translation_source_hash']));
        foreach (['stat_items' => ['label_en'], 'settings' => ['value_en']] as $name => $fields) {
            Schema::table($name, fn ($table) => $table->dropColumn(array_merge($fields, ['translation_published', 'translation_source_hash'])));
        }
        Cache::put('settings.map', ['stale' => true]);
        Cache::put('settings.translations', ['stale' => true]);

        $this->translations()->up();
        $this->translations()->up();

        $saved = DB::table('banners')->where('id', $banner->id)->first();
        $this->assertSame('Saved translation', $saved->title_en);
        $this->assertSame(1, $saved->translation_published);
        $this->assertSame(str_repeat('a', 64), $saved->translation_source_hash);
        $this->assertNull($saved->subtitle_en);
        $this->assertTrue(Schema::hasColumns('quick_links', ['title_en', 'description_en', 'translation_source_hash']));
        $this->assertTrue(Schema::hasColumns('stat_items', ['label_en', 'translation_published']));
        $this->assertTrue(Schema::hasColumns('settings', ['value_en', 'translation_published']));
        $this->assertNull(Cache::get('settings.map'));
        $this->assertNull(Cache::get('settings.translations'));
    }
}
