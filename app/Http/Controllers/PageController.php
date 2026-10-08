<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Support\Seo;

class PageController extends Controller
{
    public function show(Page $page)
    {
        // Чернетки бачать лише залогінені адміністратори (превʼю з адмінки).
        abort_unless($page->is_published || auth()->check(), 404);

        // /en індексується лише з повним незастарілим перекладом (App\Support\Seo).
        Seo::translation($page);

        return view('pages.show', compact('page'));
    }
}
