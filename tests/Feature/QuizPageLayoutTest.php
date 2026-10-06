<?php

namespace Tests\Feature;

use App\Models\QuizQuestion;
use App\Models\Specialty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Контракти редизайну сторінки квізу /kviz: світла шапка розділу,
 * серверний рендер питань і варіантів, екран результату по спеціальностях.
 */
class QuizPageLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_quiz_has_light_section_header_with_counter(): void
    {
        $count = QuizQuestion::active()->count();

        $this->get('/kviz')
            ->assertOk()
            ->assertSee('bg-slate-50/80', false)
            ->assertSee('accent-rule', false)
            ->assertSee($count.' питань')
            ->assertSee('близько хвилини')
            ->assertSee('без реєстрації');
    }

    public function test_quiz_intro_explains_steps_and_lists_specialties(): void
    {
        $specialty = Specialty::published()->ordered()->first();

        $this->get('/kviz')
            ->assertOk()
            ->assertSee('Почати тест')
            ->assertSee('Обираєш відповіді')
            ->assertSee('Рахуємо збіги')
            ->assertSee('Отримуєш результат')
            ->assertSee('Спеціальності в тесті')
            ->assertSee($specialty->title);
    }

    /** Питання рендеряться сервером — видні без JS і доступні пошуковим системам. */
    public function test_questions_and_options_are_rendered_server_side(): void
    {
        $questions = QuizQuestion::active()->with('options')->get();
        $html = $this->get('/kviz')->assertOk()->getContent();

        foreach ($questions as $qi => $question) {
            $this->assertStringContainsString($question->question, $html);

            foreach ($question->options as $oi => $option) {
                $this->assertStringContainsString($option->label, $html);
                $this->assertStringContainsString('data-step="'.$qi.'" data-opt="'.$oi.'"', $html);
            }
        }
    }

    public function test_progressbar_and_back_navigation_present(): void
    {
        $this->get('/kviz')
            ->assertOk()
            ->assertSee('role="progressbar"', false)
            ->assertSee('aria-label="Прогрес тесту"', false)
            ->assertSee('Назад');
    }

    public function test_result_screen_links_to_specialty_and_contacts(): void
    {
        $specialty = Specialty::published()->ordered()->first();

        $this->get('/kviz')
            ->assertOk()
            ->assertSee('Твій результат')
            ->assertSee('Як розподілилися твої відповіді')
            ->assertSee(route('contacts'), false)
            ->assertSee(route('specialties.show', $specialty), false)
            ->assertSee('Пройти ще раз');
    }

    public function test_quiz_shows_empty_state_without_questions(): void
    {
        QuizQuestion::query()->update(['is_active' => false]);

        $this->get('/kviz')
            ->assertOk()
            ->assertSee('Тест ще готується')
            ->assertDontSee('Почати тест');
    }
}
