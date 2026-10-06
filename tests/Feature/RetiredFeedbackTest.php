<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Faq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RetiredFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_drop_migration_preserves_archived_records(): void
    {
        $records = [
            'testimonials' => ['name' => 'Archive', 'quote' => 'Preserve this testimonial'],
            'applicant_requests' => ['name' => 'Archive', 'phone' => '123456'],
            'feedback_messages' => ['name' => 'Archive', 'message' => 'Preserve this message'],
        ];
        foreach ($records as $table => $record) {
            DB::table($table)->insert($record);
        }
        $migration = require database_path('migrations/2026_08_28_180000_drop_applicant_feedback_testimonials.php');
        $migration->up();
        $migration->down();
        foreach ($records as $table => $record) {
            $this->assertDatabaseHas($table, $record);
        }
    }

    public function test_faq_update_is_repeatable_and_preserves_custom_content(): void
    {
        $custom = Faq::create(['question' => 'Як подати заявку на вступ онлайн?', 'answer' => 'Власна актуальна відповідь коледжу']);
        $fixture = Faq::create([
            'question' => 'Як подати заявку на вступ онлайн?',
            'answer' => 'Заповніть форму «Залишити заявку» на сторінці /zayavka — вкажіть імʼя, телефон і спеціальність, що цікавить. Приймальна комісія зателефонує вам найближчим часом.',
        ]);
        $migration = require database_path('migrations/2026_10_06_200000_update_retired_form_faqs.php');
        $migration->up();
        $migration->up();
        $migration->down();
        $this->assertSame('Власна актуальна відповідь коледжу', $custom->fresh()->answer);
        $this->assertTrue($fixture->fresh()->hasPublishedEnglishTranslation());
        $this->assertFalse($fixture->fresh()->translationIsStale());
        $this->assertSame('Де отримати інформацію про вступ?', $fixture->fresh()->question);
    }

    public function test_feedback_links_page_remains_public_in_both_languages(): void
    {
        $page = Page::create(['title' => 'Зворотний зв’язок', 'slug' => 'zvorotniy-zvyazok', 'body' => '<p><a href="https://example.test/feedback">Форма коледжу</a></p>', 'is_published' => true, 'title_en' => 'Feedback', 'body_en' => '<p><a href="https://example.test/feedback">College feedback form</a></p>', 'translation_published' => true]);
        $this->get('/'.$page->slug)->assertOk()->assertSee('https://example.test/feedback')->assertSee('Форма коледжу');
        $this->get('/en/'.$page->slug)->assertOk()->assertSee('College feedback form')->assertSee('https://example.test/feedback');
    }

    public function test_old_feedback_and_testimonials_are_removed_from_admin_and_site(): void
    {
        $this->get('/')->assertOk()->assertDontSee('Відгуки студентів');
        $this->get('/kontakty')->assertOk()->assertDontSee('contacts.store');
        $this->actingAs(User::firstOrFail())->get('/admin/feedback-messages')->assertNotFound();
        $this->get('/admin/testimonials')->assertNotFound();
    }
}
