<?php

namespace App\Http\Controllers;

use App\Models\Gallery;

class GalleryController extends Controller
{
    public function index()
    {
        $galleries = Gallery::published()->ordered()->with('photos')->withCount('photos')->paginate(12);

        return view('galleries.index', compact('galleries'));
    }

    public function show(Gallery $gallery)
    {
        abort_unless($gallery->is_published, 404);

        $gallery->load('photos');

        // Інші альбоми — для блоку навігації внизу сторінки
        $others = Gallery::published()->ordered()->with('photos')->withCount('photos')
            ->whereKeyNot($gallery->getKey())
            ->take(4)
            ->get();

        return view('galleries.show', compact('gallery', 'others'));
    }
}
