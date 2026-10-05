<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Event;
use App\Models\News;
use App\Models\QuickLink;
use App\Models\StatItem;
use App\Models\Testimonial;
use App\Models\Video;

class HomeController extends Controller
{
    public function index()
    {
        $banners = Banner::active()->ordered()->get();
        $tiles = QuickLink::visible()->location('home_tile')->ordered()->get();
        $stats = StatItem::active()->get();
        $events = Event::published()->upcoming()->limit(3)->get();
        $news = News::published()->recent()->with('category')->limit(6)->get();
        $videos = Video::published()->ordered()->limit(6)->get();
        $testimonials = Testimonial::active()->limit(3)->get();
        $onThisDay = $this->onThisDay();

        return view('home', compact('banners', 'tiles', 'stats', 'events', 'news', 'videos', 'testimonials', 'onThisDay'));
    }

    /**
     * «Цього дня в коледжі»: новина цього самого дня в минулі роки
     * (а якщо точного збігу немає — в межах ±3 днів). Архів — з 2014 року.
     */
    private function onThisDay(): ?News
    {
        $base = fn () => News::published()
            ->whereNotNull('published_at')
            ->whereYear('published_at', '<', now()->year)
            ->orderByDesc('published_at');

        $exact = $base()
            ->with('category')
            ->whereMonth('published_at', now()->month)
            ->whereDay('published_at', now()->day)
            ->first();

        if ($exact) {
            return $exact;
        }

        // ±3 дні: добу року (місяць+день) звіряємо на боці БД, не тягнучи
        // весь архів у пам'ять. Беремо найсвіжішу новину з вікна.
        $window = collect(range(-3, 3))->map(fn ($d) => now()->copy()->addDays($d));

        return $base()
            ->with('category')
            ->where(function ($q) use ($window) {
                foreach ($window as $day) {
                    $q->orWhere(fn ($w) => $w
                        ->whereMonth('published_at', $day->month)
                        ->whereDay('published_at', $day->day));
                }
            })
            ->first();
    }
}
