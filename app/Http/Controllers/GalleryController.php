<?php

namespace App\Http\Controllers;

use App\Models\Gallery;
use App\Support\Seo;

class GalleryController extends Controller
{
    public function index()
    {
        $galleries = Gallery::published()->ordered()->with('photos')->withCount('photos')->paginate(12);
        abort_if($galleries->currentPage() > $galleries->lastPage(), 404);

        return view('galleries.index', compact('galleries'));
    }

    public function show(Gallery $gallery)
    {
        abort_unless($gallery->is_published, 404);

        $gallery->load('photos');
        Seo::translation($gallery);

        // Інші альбоми — для блоку навігації внизу сторінки
        $others = Gallery::published()->ordered()->with('photos')->withCount('photos')
            ->whereKeyNot($gallery->getKey())
            ->take(4)
            ->get();

        return view('galleries.show', compact('gallery', 'others'));
    }
}
