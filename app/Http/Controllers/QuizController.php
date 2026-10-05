<?php

namespace App\Http\Controllers;

use App\Models\QuizQuestion;
use App\Models\Specialty;

class QuizController extends Controller
{
    public function index()
    {
        $questions = QuizQuestion::active()
            ->with('options')
            ->get();

        $specialties = Specialty::published()->ordered()
            ->get();

        return view('quiz.index', compact('questions', 'specialties'));
    }
}
