<?php

namespace App\Http\Controllers;

use App\Models\Gallery;

class GalleryController extends Controller
{
    public function index()
    {
        $galleries = Gallery::published()->ordered()->with('photos')->withCount('photos')->get();

        return view('galleries.index', compact('galleries'));
    }

    public function show(Gallery $gallery)
    {
        abort_unless($gallery->is_published, 404);

        $gallery->load('photos');

        return view('galleries.show', compact('gallery'));
    }
}
