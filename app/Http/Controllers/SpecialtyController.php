<?php

namespace App\Http\Controllers;

use App\Models\Specialty;

class SpecialtyController extends Controller
{
    public function index()
    {
        $specialties = Specialty::published()->ordered()->with('programs')->get();

        return view('specialties.index', compact('specialties'));
    }

    public function show(Specialty $specialty)
    {
        abort_unless($specialty->is_published, 404);

        $specialty->load('programs');

        $others = Specialty::published()->ordered()->whereKeyNot($specialty->id)->get();

        return view('specialties.show', compact('specialty', 'others'));
    }
}
