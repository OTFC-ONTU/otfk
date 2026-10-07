<?php

namespace Tests\Feature;

use App\Filament\Resources\DepartmentResource\Pages\EditDepartment;
use App\Filament\Resources\ProgramResource\Pages\EditProgram;
use App\Filament\Resources\SpecialtyResource\Pages\EditSpecialty;
use App\Models\Department;
use App\Models\Program;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AcademicTranslationTest extends TestCase
{
    use RefreshDatabase;

    private function specialty(array $overrides = []): Specialty
    {
        return Specialty::create(array_merge([
            'title' => 'Українська спеціальність', 'slug' => 'academic-translation', 'code' => '777',
            'short_description' => 'Український короткий опис', 'description' => '<p>Український повний опис</p>',
            'degree' => 'Український ступінь', 'study_form' => 'Українська форма', 'duration' => 'Український термін',
            'title_en' => 'English specialty', 'short_description_en' => 'English summary',
            'description_en' => '<p>English description <a href="/kontakty#map">Contact</a></p>',
            'degree_en' => 'Professional Junior Bachelor', 'study_form_en' => 'Full-time', 'duration_en' => 'Three years',
            'translation_published' => true, 'is_published' => true,
        ], $overrides));
    }

    public function test_specialty_translation_reaches_details_cards_form_quiz_and_metadata(): void
    {
        $specialty = $this->specialty();
        foreach (['/en/spetsialnosti', '/en/spetsialnosti/'.$specialty->slug, '/en/kviz'] as $path) {
            $this->get($path)->assertOk()->assertSee('English specialty')->assertDontSee('Українська спеціальність');
        }
        $this->get('/en/spetsialnosti/'.$specialty->slug)->assertOk()
            ->assertSee('English summary')->assertSee('English description')->assertSee('Professional Junior Bachelor')
            ->assertSee('Full-time')->assertSee('Three years')->assertSee('/en/kontakty#map', false)
            ->assertSee('"name":"English specialty"', false)->assertDontSee('Український');
        $this->get('/spetsialnosti/'.$specialty->slug)->assertOk()->assertSee('Український повний опис')
            ->assertSee('Українська форма')->assertDontSee('English description');
        $this->assertSame('academic-translation', $specialty->fresh()->slug);
        $this->assertSame('<p>Український повний опис</p>', $specialty->fresh()->description);
        $this->assertStringContainsString('href="/kontakty#map"', $specialty->fresh()->description_en);
    }

    public function test_related_specialties_and_programs_use_their_own_publication_and_keep_file_urls(): void
    {
        $specialty = $this->specialty();
        $other = $this->specialty(['slug' => 'other-academic', 'title_en' => 'Other English specialty']);
        $program = Program::create([
            'specialty_id' => $specialty->id, 'title' => 'Українська програма', 'description' => 'Український опис програми',
            'title_en' => 'English program', 'description_en' => 'English program description',
            'translation_published' => true, 'file_path' => 'programs/original.pdf',
        ]);
        $fileUrl = $program->file_url;
        $this->get('/en/spetsialnosti/'.$specialty->slug)->assertOk()->assertSee('Other English specialty')
            ->assertSee('English program')->assertSee('English program description')->assertSee($fileUrl, false)
            ->assertDontSee('Українська програма');
        $program->update(['translation_published' => false]);
        $other->update(['translation_published' => false]);
        $this->get('/en/spetsialnosti/'.$specialty->slug)->assertOk()->assertSee('Українська програма')
            ->assertSee('Український опис програми')->assertDontSee('English program')->assertDontSee('Other English specialty');
        $this->assertSame($fileUrl, $program->fresh()->file_url);
    }

    public function test_departments_translate_lists_details_and_html_links(): void
    {
        $department = Department::create([
            'title' => 'Український підрозділ', 'slug' => 'academic-department', 'type' => 'viddilennya',
            'description' => '<p>Український опис підрозділу</p>', 'title_en' => 'English department',
            'description_en' => '<p>English department description <a href="/spetsialnosti">Courses</a></p>',
            'translation_published' => true, 'is_published' => true,
        ]);
        foreach (['/en/struktura', '/en/struktura/'.$department->slug] as $path) {
            $this->get($path)->assertOk()->assertSee('English department')->assertDontSee('Український підрозділ');
        }
        $this->get('/en/struktura/'.$department->slug)->assertSee('English department description')
            ->assertSee('/en/spetsialnosti', false)->assertDontSee('Український опис підрозділу');
        $this->get('/struktura/'.$department->slug)->assertOk()->assertSee('Український підрозділ')->assertDontSee('English department');
        $department->update(['translation_published' => false]);
        $this->get('/en/struktura/'.$department->slug)->assertOk()->assertSee('Український опис підрозділу')->assertDontSee('English department');
        $department->update(['is_published' => false]);
        $this->get('/en/struktura/'.$department->slug)->assertNotFound();
        $this->get('/en/struktura')->assertDontSee('Український підрозділ');
    }

    public function test_every_populated_specialty_field_is_required_for_publication(): void
    {
        foreach (['title', 'short_description', 'description', 'degree', 'study_form', 'duration'] as $field) {
            $specialty = $this->specialty(['slug' => 'missing-'.$field, $field.'_en' => ' ', 'translation_published' => false]);
            try {
                $specialty->update(['translation_published' => true]);
                $this->fail('Incomplete translation was published');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('translation_published', $exception->errors());
            }
            $this->assertFalse($specialty->fresh()->translation_published);
            app()->setLocale('en');
            $this->assertSame('Українська спеціальність', $specialty->fresh()->localized('title'));
        }
        $titleOnly = Specialty::create(['title' => 'Лише назва', 'title_en' => 'Title only', 'translation_published' => true]);
        $this->assertTrue($titleOnly->hasPublishedEnglishTranslation());
        $this->assertNull($titleOnly->localized('duration'));
    }

    public function test_source_changes_and_optional_new_fields_keep_hash_and_trigger_whole_fallback_when_incomplete(): void
    {
        $specialty = $this->specialty(['duration' => null, 'duration_en' => null]);
        $hash = $specialty->translation_source_hash;
        $specialty->update(['degree' => 'Змінений ступінь', 'sort_order' => 3]);
        $this->assertSame($hash, $specialty->translation_source_hash);
        $this->assertTrue($specialty->translationIsStale());
        $this->assertTrue($specialty->hasPublishedEnglishTranslation());
        $specialty->update(['duration' => 'Новий термін']);
        $this->assertFalse($specialty->hasPublishedEnglishTranslation());
        $this->assertSame($hash, $specialty->translation_source_hash);
        $this->get('/en/spetsialnosti/'.$specialty->slug)->assertOk()->assertSee('Новий термін')->assertDontSee('English specialty');
        $specialty->update(['duration_en' => 'New duration']);
        $this->assertFalse($specialty->translationIsStale());
        $this->assertTrue($specialty->hasPublishedEnglishTranslation());
    }

    public function test_specialty_search_uses_published_translation_and_fallback_without_exposing_drafts(): void
    {
        $specialty = $this->specialty(['description_en' => '<p>Unique academic keyword</p>']);
        $this->get('/en/poshuk?q=academic')->assertOk()->assertSee('English specialty')->assertSee('English summary');
        $this->get('/en/poshuk/pidkazky?q=academic')->assertOk()->assertJsonFragment(['title' => 'English specialty']);
        $this->get('/poshuk?q=academic')->assertOk()->assertDontSee('English specialty');
        $this->get('/en/poshuk/pidkazky?q=Українська')->assertJsonMissing(['title' => 'English specialty']);
        $specialty->update(['translation_published' => false]);
        $this->get('/en/poshuk?q=academic')->assertDontSee('English specialty');
        $this->get('/en/poshuk/pidkazky?q=Українська')->assertJsonFragment(['title' => 'Українська спеціальність']);
        $specialty->update(['translation_published' => true, 'is_published' => false]);
        $this->get('/en/poshuk/pidkazky?q=academic')->assertJsonMissing(['title' => 'English specialty']);
        $this->get('/en/spetsialnosti/'.$specialty->slug)->assertNotFound();
        // Прямий SQL імітує неповний переклад, який не пройшов валідацію моделі.
        Specialty::whereKey($specialty->id)->update(['is_published' => true, 'duration_en' => null]);
        $this->get('/en/poshuk/pidkazky?q=academic')->assertJsonMissing(['title' => 'English specialty']);
        $this->get('/en/poshuk/pidkazky?q=Українська')->assertJsonFragment(['title' => 'Українська спеціальність']);
    }

    public function test_filament_validates_and_preserves_translations_for_all_three_resources(): void
    {
        $this->actingAs(User::firstOrFail());
        $specialty = $this->specialty(['translation_published' => false]);
        $department = Department::create(['title' => 'Підрозділ', 'type' => 'kafedra', 'description' => '<p>Оригінал</p>']);
        $program = Program::create(['specialty_id' => $specialty->id, 'title' => 'Програма', 'description' => 'Оригінал']);
        $html = '<div class="kept"><table><tr><td>English</td></tr></table><a href="/kontakty">Contact</a></div>';
        foreach ([$specialty, $department, $program] as $record) {
            $component = match ($record::class) {
                Specialty::class => EditSpecialty::class,
                Department::class => EditDepartment::class,
                Program::class => EditProgram::class,
            };
            Livewire::test($component, ['record' => $record->getRouteKey()])
                ->fillForm(['translation_published' => true, 'title_en' => 'Admin title', 'description_en' => ''])
                ->call('save')->assertHasFormErrors(['description_en' => 'required']);
            $description = $record instanceof Program ? 'English plain description' : $html;
            Livewire::test($component, ['record' => $record->getRouteKey()])
                ->fillForm(['translation_published' => true, 'title_en' => 'Admin title', 'description_en' => $description])
                ->call('save')->assertHasNoFormErrors();
            $expected = $description; // HTML проходить SafeHtml без змін: таблиця, клас і посилання вже в канонічній формі
            $this->assertSame($expected, $record->fresh()->description_en);
            $this->assertSame('Опубліковано', $record->fresh()->translationStatus());
            $record->refresh();
            $hash = $record->translation_source_hash;
            $record->update(['title' => 'Змінений оригінал']);
            $this->assertSame($hash, $record->translation_source_hash);
            $this->assertTrue($record->translationIsStale());
        }
        Livewire::test(EditSpecialty::class, ['record' => $specialty->slug])
            ->fillForm(['duration_en' => ''])->call('save')->assertHasFormErrors(['duration_en' => 'required']);
    }
}
