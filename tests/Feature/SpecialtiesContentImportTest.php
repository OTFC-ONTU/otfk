<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\Specialty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Імпорт описів спеціальностей зі старого сайту (міграція
 * 2026_08_28_210000_import_specialties_content): 9 спеціальностей
 * з реальними описами замість заглушок і 15 записів Program (ОПП).
 */
class SpecialtiesContentImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_seeds_nine_specialties_with_letter_codes(): void
    {
        $expected = [
            'inzheneriya-programnoho-zabezpechennya' => 'F2',
            'kompyuterna-inzheneriya' => 'F7',
            'kharchovi-tekhnolohiyi' => 'G13',
            'finansy-bankivska-sprava' => 'D2',
            'menedzhment' => 'D3',
            'marketynh' => 'D5',
            'torhivlya' => 'D7',
            'enerhovyrobnytstvo' => 'G4',
            'tekhnolohiyi-lehkoyi-promyslovosti' => 'G15',
        ];

        foreach ($expected as $slug => $code) {
            $specialty = Specialty::where('slug', $slug)->first();

            $this->assertNotNull($specialty, "Немає спеціальності {$slug}");
            $this->assertSame($code, $specialty->code);
            $this->assertTrue($specialty->is_published);
            $this->assertStringNotContainsString('наповнюється', strip_tags($specialty->description));
            $this->assertNotEmpty($specialty->short_description);
            $this->assertGreaterThan(0, $specialty->programs()->count(), "У {$slug} немає жодної ОПП");
        }

        $imported = Program::whereIn('specialty_id', Specialty::whereIn('slug', array_keys($expected))->pluck('id'));
        $this->assertSame(15, $imported->count());
    }

    public function test_specialty_page_shows_imported_description_and_programs(): void
    {
        $this->get('/spetsialnosti/enerhovyrobnytstvo')
            ->assertOk()
            ->assertSee('Енерговиробництво')
            ->assertSee('Холодильні і кліматичні технології')
            ->assertSee('Монтаж і обслуговування холодильно-компресорних машин та установок')
            ->assertSee('Освітньо-професійні програми');
    }

    public function test_imported_specialties_have_no_demo_programs(): void
    {
        $this->assertSame(0, Program::where('description', 'like', 'Демонстраційний запис%')->count());
    }

    /**
     * Чистка демо-залишків (міграція remove_demo_specialty_and_fix_descriptions):
     * демо-спеціальності 071 немає, демо-описи замінені реальними,
     * порядок — як на старому сайті (першою йде F7).
     */
    public function test_demo_accounting_specialty_removed_and_descriptions_are_real(): void
    {
        $this->assertNull(Specialty::where('slug', 'oblik-i-opodatkuvannya')->first());

        $this->assertSame(0, Specialty::where('short_description', 'like', 'Сучасна підготовка фахівців%')->count());

        $this->assertSame('kompyuterna-inzheneriya', Specialty::published()->ordered()->first()->slug);
    }

    public function test_link_opp_programs_command_backfills_missing_pdfs(): void
    {
        $category = \App\Models\DocumentCategory::firstOrCreate(
            ['slug' => 'osvitno-profesiyni-prohramy'],
            ['title' => 'Освітньо-професійні програми', 'sort_order' => 99]
        );

        \App\Models\Document::create([
            'document_category_id' => $category->id,
            'title' => "ОПП спеціальність: G4 «Енерговиробництво». Освітньо-професійна програма: «Монтаж і обслуговування холодильнокомпресорних машин та установок»",
            'file_path' => 'documents/osvitno-profesiyni-prohramy/test-g4.pdf',
            'published_at' => now(),
            'sort_order' => 1,
            'is_published' => true,
        ]);

        $program = Specialty::where('slug', 'enerhovyrobnytstvo')->first()
            ->programs()->where('title', 'Монтаж і обслуговування холодильно-компресорних машин та установок')->first();
        $program->update(['file_path' => null]);

        $this->artisan('otfk:link-opp-programs')->assertExitCode(0);

        // Дефіс/без дефіса у назві — нормалізація має знайти файл 2025 року
        $this->assertSame('documents/osvitno-profesiyni-prohramy/test-g4.pdf', $program->fresh()->file_path);
    }
}
