<?php

namespace App\Http\Controllers;

use App\Models\Page;

class ContactController extends Controller
{
    public function index()
    {
        $page = Page::published()->where('slug', 'kontakty')->first();

        return view('contacts', compact('page'));
    }
}
