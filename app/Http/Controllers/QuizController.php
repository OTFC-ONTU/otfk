<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\QuizQuestion;
use Illuminate\Support\Collection;

class QuizController extends Controller
{
    public function index()
    {
        $questions = QuizQuestion::active()
            ->with('options.scores')
            ->get();

        $max = QuizQuestion::maxPoints($questions);

        $programs = $this->programs(array_keys($max));
        $max = array_intersect_key($max, $programs->keyBy('id')->all());
        $specialties = $programs->pluck('specialty')->unique('id')->values();

        return view('quiz.index', compact('questions', 'programs', 'specialties', 'max'));
    }

    /** ОПП опублікованих спеціальностей, які отримують бали, у порядку сайту. */
    private function programs(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return Program::query()
            ->whereIn('id', $ids)
            ->whereHas('specialty', fn ($query) => $query->published())
            ->with('specialty')
            ->get()
            ->sortBy(fn (Program $program) => [$program->specialty->sort_order, $program->specialty->id, $program->sort_order, $program->id])
            ->values();
    }
}
