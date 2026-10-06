<?php

namespace App\Http\Controllers;

use App\Models\Page;

class PageController extends Controller
{
    public function show(Page $page)
    {
        // Чернетки бачать лише залогінені адміністратори (превʼю з адмінки).
        abort_unless($page->is_published || auth()->check(), 404);

        return view('pages.show', compact('page'));
    }
}
