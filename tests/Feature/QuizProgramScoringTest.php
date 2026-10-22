<?php

namespace Tests\Feature;

use App\Filament\Resources\QuizQuestionResource;
use App\Filament\Resources\QuizQuestionResource\Pages\EditQuizQuestion;
use App\Models\Program;
use App\Models\QuizOption;
use App\Models\QuizOptionScore;
use App\Models\QuizQuestion;
use App\Models\Specialty;
use App\Models\User;
use App\Support\ProgramAnchors;
use Database\Seeders\QuizSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Квіз рахує бали освітньо-професійним програмам: варіант додає бали кільком ОПП,
 * результат — ОПП з найбільшим відсотком збігу від її можливого максимуму.
 */
class QuizProgramScoringTest extends TestCase
{
    use RefreshDatabase;

    /** Стандартний квіз охоплює всі ОПП опублікованих спеціальностей з приблизно рівним максимумом. */
    public function test_default_quiz_covers_every_program_with_balanced_maximum(): void
    {
        $programs = Program::whereHas('specialty', fn ($q) => $q->published())->pluck('id');
        $this->assertCount(count(QuizSeeder::PROGRAMS), QuizSeeder::programIds());

        $max = QuizQuestion::maxPoints(QuizQuestion::active()->with('options.scores')->get());

        $this->assertEqualsCanonicalizing($programs->all(), array_keys($max));
        $this->assertLessThanOrEqual(2, max($max) - min($max), 'Максимуми ОПП мають бути близькими: '.json_encode($max));
    }

    /** Жоден варіант не веде до однієї ОПП «напряму» у більшості питань: більшість варіантів зачіпає кілька програм. */
    public function test_most_options_score_several_programs(): void
    {
        $options = QuizOption::withCount('scores')->get();

        $this->assertGreaterThan($options->count() / 2, $options->where('scores_count', '>', 1)->count());
        $this->assertSame(0, $options->where('scores_count', 0)->count());
    }

    public function test_page_has_result_card_and_anchor_link_for_every_program(): void
    {
        $html = $this->get('/kviz')->assertOk()->getContent();

        foreach (Program::with('specialty')->get() as $program) {
            $this->assertStringContainsString('winnerId === '.$program->id.'"', $html);
            $this->assertStringContainsString(e(ProgramAnchors::url($program->specialty, $program)), $html);
            $this->assertStringContainsString(e($program->title), $html);
        }

        $this->assertStringContainsString('2–3 хвилини', $html);
        $this->assertStringContainsString('Про освітню програму', $html);
    }

    public function test_view_passes_weights_and_maximum_per_program(): void
    {
        $question = QuizQuestion::create(['question' => 'Тестове питання', 'is_active' => true]);
        $first = Program::orderBy('id')->firstOrFail();
        $second = Program::orderBy('id')->skip(1)->firstOrFail();
        $option = $question->options()->create(['label' => 'Обидві', 'sort_order' => 1]);
        $option->scores()->createMany([['program_id' => $first->id, 'points' => 3], ['program_id' => $second->id, 'points' => 1]]);
        $question->options()->create(['label' => 'Лише друга', 'sort_order' => 2])
            ->scores()->create(['program_id' => $second->id, 'points' => 2]);

        $this->get('/kviz')->assertOk()->assertViewHas('max', function (array $max) use ($first, $second) {
            $base = QuizQuestion::maxPoints(QuizQuestion::active()->where('question', '!=', 'Тестове питання')->with('options.scores')->get());

            return $max[$first->id] === ($base[$first->id] ?? 0) + 3 && $max[$second->id] === ($base[$second->id] ?? 0) + 2;
        });

        $this->assertSame([[$first->id => 3, $second->id => 1], [$second->id => 2]], array_column($question->fresh()->publicPayload()['options'], 'scores'));
    }

    public function test_programs_of_unpublished_specialty_are_not_results(): void
    {
        $program = Program::with('specialty')->firstOrFail();
        $program->specialty->update(['is_published' => false]);

        $this->get('/kviz')->assertOk()
            ->assertViewHas('programs', fn ($programs) => ! $programs->contains('id', $program->id))
            ->assertViewHas('max', fn ($max) => ! array_key_exists($program->id, $max))
            ->assertDontSee('winnerId === '.$program->id.'"', false);
    }

    /**
     * Відкочує дві міграції квізу разом з усіма новішими (кількість кроків
     * рахується від назви, тож нові міграції після них не ламають тест).
     */
    private function rollbackQuizMigrations(): void
    {
        $steps = DB::table('migrations')->where('migration', '>=', '2026_10_17_100000_create_quiz_option_scores_table')->count();
        Artisan::call('migrate:rollback', ['--step' => $steps]);
    }

    /** Стара прив’язка «варіант → спеціальність» переноситься на всі ОПП спеціальності. */
    public function test_migration_moves_specialty_links_to_all_programs_of_specialty(): void
    {
        $this->rollbackQuizMigrations();

        $specialty = Specialty::whereHas('programs', null, '>', 1)->firstOrFail();
        $questionId = DB::table('quiz_questions')->insertGetId(['question' => 'Власне питання редактора', 'sort_order' => 99, 'is_active' => true]);
        $optionId = DB::table('quiz_options')->insertGetId([
            'quiz_question_id' => $questionId, 'label' => 'Варіант', 'specialty_id' => $specialty->id, 'points' => 2, 'sort_order' => 1,
        ]);

        Artisan::call('migrate');

        $this->assertEqualsCanonicalizing(
            $specialty->programs()->pluck('id')->all(),
            QuizOptionScore::where('quiz_option_id', $optionId)->where('points', 2)->pluck('program_id')->all()
        );
        // Квіз змінено в адмінці (є власне питання) — новий набір його не замінює
        $this->assertTrue(QuizQuestion::where('question', 'Власне питання редактора')->exists());
    }

    public function test_untouched_legacy_quiz_is_replaced_and_edited_one_is_kept(): void
    {
        $this->rollbackQuizMigrations();
        DB::table('quiz_questions')->delete();
        foreach (['Чим би ти зайнявся у вільний вечір?', 'Обери свій інструмент:'] as $i => $text) {
            DB::table('quiz_questions')->insert(['question' => $text, 'sort_order' => $i, 'is_active' => true]);
        }

        Artisan::call('migrate');

        $this->assertFalse(QuizQuestion::where('question', 'Обери свій інструмент:')->exists());
        $this->assertSame(count(QuizSeeder::questions()), QuizQuestion::count());

        // Повторний запуск сідера не дублює питань і варіантів
        $options = QuizOption::count();
        (new QuizSeeder)->run();
        $this->assertSame($options, QuizOption::count());
    }

    public function test_admin_saves_nested_program_scores_and_sees_coverage(): void
    {
        $this->actingAs(User::firstOrFail());
        $question = QuizQuestion::with('options')->orderBy('sort_order')->firstOrFail();
        $program = Program::orderByDesc('id')->firstOrFail();

        $component = Livewire::test(EditQuizQuestion::class, ['record' => $question->id]);
        $options = $component->get('data.options');
        $key = array_key_first($options);
        $options[$key]['scores'] = [['program_id' => $program->id, 'points' => 3]];
        $component->fillForm(['options' => $options])->call('save')->assertHasNoFormErrors();

        $first = $question->options()->orderBy('sort_order')->firstOrFail();
        $this->assertSame([$program->id => 3], $first->load('scores')->scoreMap());

        QuizOptionScore::where('program_id', $program->id)->delete();
        $this->assertStringContainsString('Без балів (не можуть стати результатом): '.$program->title, QuizQuestionResource::coverageSummary());
    }
}
