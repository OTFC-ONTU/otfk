<?php

namespace App\Http\Controllers;

use App\Models\Specialty;
use App\Support\Seo;

class SpecialtyController extends Controller
{
    public function index()
    {
        $specialties = Specialty::published()->ordered()->with('programs')->get();

        return view('specialties.index', compact('specialties'));
    }

    public function show(Specialty $specialty)
    {
        // Чернетки бачать лише залогінені адміністратори (превʼю з адмінки).
        abort_unless($specialty->is_published || auth()->check(), 404);

        $specialty->load('programs');
        Seo::translation($specialty);

        $others = Specialty::published()->ordered()->whereKeyNot($specialty->id)->get();

        return view('specialties.show', compact('specialty', 'others'));
    }
}
