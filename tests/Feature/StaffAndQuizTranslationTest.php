<?php

namespace Tests\Feature;

use App\Filament\Resources\QuizQuestionResource\Pages\EditQuizQuestion;
use App\Filament\Resources\StaffResource\Pages\EditStaff;
use App\Models\Department;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class StaffAndQuizTranslationTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): Staff
    {
        return Staff::create([
            'full_name' => 'Українське Ім’я', 'position' => 'Українська посада', 'academic_degree' => 'Український ступінь',
            'bio' => 'Українська біографія', 'category' => 'administration', 'is_published' => true,
            'full_name_en' => 'English Name', 'position_en' => 'English position', 'academic_degree_en' => 'English degree',
            'bio_en' => 'English biography', 'translation_published' => true, 'email' => 'staff@example.test',
        ]);
    }

    private function question(): QuizQuestion
    {
        $question = QuizQuestion::create([
            'question' => 'Українське питання квізу', 'question_en' => 'English quiz question',
            'is_active' => true, 'translation_published' => true,
        ]);
        foreach (['First', 'Second'] as $i => $label) {
            $question->options()->create([
                'label' => 'Український варіант '.$i, 'label_en' => $label.' English option',
                'translation_published' => true, 'points' => $i + 1, 'sort_order' => $i,
            ]);
        }

        return $question;
    }

    public function test_staff_translates_administration_department_cards_and_initials_preserving_contacts(): void
    {
        $staff = $this->staff();
        $department = Department::create(['title' => 'Підрозділ', 'type' => 'kafedra', 'is_published' => true]);
        $staff->update(['department_id' => $department->id]);
        foreach (['/en/administratsiya', '/en/struktura/'.$department->slug] as $path) {
            $this->get($path)->assertOk()->assertSee('English Name')->assertSee('English position')
                ->assertSee('English degree')->assertSee('staff@example.test')->assertDontSee('Українське Ім’я');
        }
        app()->setLocale('en');
        $this->assertSame('EN', $staff->initials());
        $staff->update(['photo' => 'staff/missing-photo.jpg']);
        $this->get('/en/administratsiya')->assertSee('alt="English Name"', false);
        $this->get('/administratsiya')->assertSee('Українське Ім’я')->assertDontSee('English Name');
        Staff::whereKey($staff->id)->update(['bio_en' => null]);
        $this->get('/en/administratsiya')->assertSee('Українське Ім’я')->assertSee('Українська посада')->assertDontSee('English degree');
        $this->assertSame('Українська біографія', $staff->fresh()->bio);
        $staff->refresh()->update(['is_published' => false]);
        $this->get('/en/administratsiya')->assertDontSee('Українське Ім’я');
        $this->get('/en/struktura/'.$department->slug)->assertDontSee('Українське Ім’я');
    }

    public function test_staff_requires_every_populated_field_and_preserves_stale_hash(): void
    {
        $staff = $this->staff();
        foreach (['full_name', 'position', 'academic_degree', 'bio'] as $field) {
            try {
                $staff->fresh()->update([$field.'_en' => ' ']);
                $this->fail('Incomplete translation was published');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('translation_published', $exception->errors());
            }
        }
        $hash = $staff->translation_source_hash;
        $staff->update(['bio' => 'Змінена біографія', 'phone' => '+380001234567']);
        $this->assertSame($hash, $staff->translation_source_hash);
        $this->assertTrue($staff->translationIsStale());
        $staff->update(['bio_en' => 'Updated biography']);
        $this->assertFalse($staff->translationIsStale());
        $staff->update(['translation_published' => false]);
        $this->get('/en/administratsiya')->assertSee('Українське Ім’я')->assertDontSee('English Name');
    }

    public function test_quiz_payload_translates_whole_question_and_keeps_scoring_and_originals(): void
    {
        $question = $this->question();
        app()->setLocale('en');
        $payload = $question->publicPayload();
        $this->assertSame('English quiz question', $payload['q']);
        $this->assertSame(['First English option', 'Second English option'], array_column($payload['options'], 'label'));
        $this->assertSame([1, 2], array_column($payload['options'], 'pts'));
        $this->assertSame([null, null], array_column($payload['options'], 'sid'));
        $this->get('/en/kviz')->assertOk()->assertSee('English quiz question')->assertSee('First English option');
        $this->get('/kviz')->assertViewHas('questions', fn ($questions) => $questions->find($question->id)->publicPayload()['q'] === 'Українське питання квізу')->assertDontSee('English quiz question');
        $this->assertSame('Українське питання квізу', $question->fresh()->question);
        $question->update(['is_active' => false]);
        $this->get('/en/kviz')->assertDontSee('English quiz question')->assertDontSee('First English option');
    }

    public function test_missing_draft_or_new_option_returns_whole_question_to_original(): void
    {
        $question = $this->question();
        $option = $question->options()->firstOrFail();
        foreach ([['translation_published' => false], ['label_en' => null]] as $changes) {
            QuizOption::whereKey($option->id)->update($changes);
            app()->setLocale('en');
            $payload = $question->fresh()->publicPayload();
            $this->assertSame('Українське питання квізу', $payload['q']);
            $this->assertSame(['Український варіант 0', 'Український варіант 1'], array_column($payload['options'], 'label'));
            QuizOption::whereKey($option->id)->update(['translation_published' => true, 'label_en' => 'First English option']);
        }
        $question->options()->create(['label' => 'Новий варіант', 'points' => 3, 'sort_order' => 2]);
        $this->get('/en/kviz')->assertViewHas('questions', fn ($questions) => $questions->find($question->id)->publicPayload()['options'][2]['label'] === 'Новий варіант')->assertDontSee('English quiz question')->assertDontSee('First English option');
        $question->options()->where('sort_order', 2)->delete();
        $question->update(['translation_published' => false]);
        app()->setLocale('en');
        $this->assertSame('Українське питання квізу', $question->fresh()->publicPayload()['q']);
        $this->assertSame('Український варіант 0', $question->fresh()->publicPayload()['options'][0]['label']);
    }

    public function test_option_tracks_its_own_source_changes_and_validates_publication(): void
    {
        $option = $this->question()->options()->firstOrFail();
        $hash = $option->translation_source_hash;
        $option->update(['points' => 5]);
        $this->assertFalse($option->translationIsStale());
        $option->update(['label' => 'Змінений варіант']);
        $this->assertSame($hash, $option->translation_source_hash);
        $this->assertTrue($option->translationIsStale());
        $option->update(['label_en' => 'Updated option']);
        $this->assertFalse($option->translationIsStale());
        $this->expectException(ValidationException::class);
        $option->update(['label_en' => ' ']);
    }

    public function test_filament_saves_staff_and_nested_quiz_option_translations(): void
    {
        $this->actingAs(User::firstOrFail());
        $staff = $this->staff();
        Livewire::test(EditStaff::class, ['record' => $staff->id])
            ->fillForm(['position_en' => ''])->call('save')->assertHasFormErrors(['position_en' => 'required']);
        Livewire::test(EditStaff::class, ['record' => $staff->id])
            ->fillForm(['bio_en' => "Admin biography\nSecond line"])->call('save')->assertHasNoFormErrors();
        $this->assertSame("Admin biography\nSecond line", $staff->fresh()->bio_en);
        $question = $this->question();
        Livewire::test(EditQuizQuestion::class, ['record' => $question->id])
            ->fillForm(['question_en' => ''])->call('save')->assertHasFormErrors(['question_en' => 'required']);
        $component = Livewire::test(EditQuizQuestion::class, ['record' => $question->id]);
        $options = $component->get('data.options');
        $key = array_key_first($options);
        $options[$key]['label_en'] = 'Admin option';
        $component->fillForm(['question_en' => 'Admin question', 'options' => $options])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Admin question', $question->fresh()->question_en);
        $this->assertSame('Admin option', $question->options()->firstOrFail()->label_en);
        $options[$key]['label_en'] = '';
        $component->fillForm(['options' => $options])->call('save')->assertHasFormErrors(['options.'.$key.'.label_en' => 'required']);
        $this->assertSame('Admin option', $question->options()->firstOrFail()->label_en);
    }
}
