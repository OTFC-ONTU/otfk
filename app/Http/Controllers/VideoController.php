<?php

namespace App\Http\Controllers;

use App\Models\Video;

class VideoController extends Controller
{
    public function index()
    {
        $videos = Video::published()->ordered()->paginate(12);
        abort_if($videos->currentPage() > $videos->lastPage(), 404);

        return view('videos.index', compact('videos'));
    }
}
