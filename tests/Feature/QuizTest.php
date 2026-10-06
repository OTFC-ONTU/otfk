<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizTest extends TestCase
{
    use RefreshDatabase;

    public function test_quiz_page_renders_with_questions(): void
    {
        // Питання вбудовуються через @js (unicode-екранування), тому перевіряємо
        // інтро-екран + наявність даних квізу в БД.
        $this->get('/kviz')
            ->assertOk()
            ->assertSee('Яка спеціальність тобі підходить?')
            ->assertSee('Не знаєш, куди вступати?')
            ->assertSee('Почати тест');

        $this->assertSame(6, \App\Models\QuizQuestion::active()->count());
        $this->assertSame(24, \App\Models\QuizOption::count());
    }

    public function test_abituriyentu_section_has_quiz_tile(): void
    {
        $this->get('/abituriyentu')->assertOk()->assertSee('Яка спеціальність мені підходить?');
    }
}
