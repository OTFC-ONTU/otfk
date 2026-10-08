<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Support\Seo;

class ContactController extends Controller
{
    public function index()
    {
        $page = Page::published()->where('slug', 'kontakty')->first();

        // Текст CMS-сторінки замінює текст з налаштувань — тоді й індексація /en
        // залежить від її перекладу; без сторінки діє перелік розділів Seo.
        if ($page && filled($page->body)) {
            Seo::translation($page);
        }

        return view('contacts', compact('page'));
    }
}
