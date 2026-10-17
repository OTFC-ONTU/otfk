<?php

use App\Models\QuizQuestion;
use Database\Seeders\QuizSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** Шість питань першого квізу (QuizSeeder до 2026-10-17): лише 4 спеціальності з 9. */
    private const LEGACY = [
        'Чим би ти зайнявся у вільний вечір?',
        'Який шкільний предмет тобі найближчий?',
        'Твоя суперсила в команді?',
        'Що звучить як робота мрії?',
        'Обери свій інструмент:',
        'Що тебе найбільше драйвить?',
    ];

    /**
     * Новий квіз по всіх ОПП замінює старий лише тоді, коли в адмінці його не змінювали:
     * немає власних питань редакторів і тексти старих питань збігаються з початковими.
     * Інакше квіз лишається як є (редактор наповнює його сам). down() вміст не видаляє.
     */
    public function up(): void
    {
        if (QuizSeeder::programIds() === []) {
            return;
        }

        $questions = QuizQuestion::query()->pluck('question');
        if ($questions->diff(self::LEGACY)->isNotEmpty()) {
            return;
        }

        QuizQuestion::query()->whereIn('question', self::LEGACY)->get()->each->delete();

        (new QuizSeeder)->run();
    }

    public function down(): void
    {
        //
    }
};
