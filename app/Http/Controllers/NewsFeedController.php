<?php

namespace App\Http\Controllers;

use App\Models\News;
use App\Models\Setting;

class NewsFeedController extends Controller
{
    public function __invoke()
    {
        $news = News::published()->recent()->limit(30)->get();
        $siteName = app()->getLocale() === 'en' ? Setting::publicGet('brand_name', __('layout.brand_name')) : config('app.name');
        $description = Setting::publicGet('site_description', __('public.news_title'));

        return response()
            ->view('feed.news', compact('news', 'siteName', 'description'), 200)
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8')
            // Читалки/агрегатори опитують часто — нехай кешують пів години (і проксі теж).
            ->header('Cache-Control', 'public, max-age=1800');
    }
}
