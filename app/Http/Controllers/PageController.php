<?php

namespace App\Http\Controllers;

use App\Models\DocumentCategory;
use App\Models\Page;
use App\Support\LocalizedUrl;

class PageController extends Controller
{
    public function show(Page $page)
    {
        // Чернетки бачать лише залогінені адміністратори (превʼю з адмінки).
        abort_unless($page->is_published || auth()->check(), 404);

        // Сторінка розділу публічної інформації має одну адресу — розділ документів, без дубля вмісту
        if ($page->is_published && $category = DocumentCategory::where('page_id', $page->id)->first()) {
            return redirect(LocalizedUrl::route('documents.category', $category), 301);
        }

        return view('pages.show', compact('page'));
    }
}
