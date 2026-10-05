<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Event;
use App\Models\News;
use App\Models\Page;
use App\Models\Specialty;
use App\Support\LocalizedUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $results = new Collection;

        if (mb_strlen($q) >= 2) {
            $results = $results
                ->concat(News::published()->searchPublic($q)->limit(15)->get()
                    ->map(fn ($n) => ['type' => __('public.search_news'), 'title' => $n->localized('title'), 'url' => LocalizedUrl::route('news.show', $n), 'excerpt' => $n->localized('excerpt')]))
                ->concat(Specialty::published()->searchPublic($q)->limit(15)->get()
                    ->map(fn ($s) => ['type' => __('public.search_specialty'), 'title' => $s->localized('title'), 'url' => LocalizedUrl::route('specialties.show', $s), 'excerpt' => $s->localized('short_description')]))
                ->concat(Page::published()->searchPublic($q)->limit(15)->get()
                    ->map(fn ($p) => ['type' => __('public.search_page'), 'title' => $p->localized('title'), 'url' => LocalizedUrl::to('/'.$p->slug), 'excerpt' => $p->localized('excerpt')]))
                ->concat(Document::published()->searchPublic($q)->limit(15)->get()
                    ->map(fn ($d) => ['type' => __('public.search_document'), 'title' => $d->localized('title'), 'url' => $d->file_url ?: LocalizedUrl::route('documents.index'), 'excerpt' => $d->localized('description')]));
        }

        return view('search.index', compact('q', 'results'));
    }

    /** Миттєві підказки для пошуку в шапці (JSON, до 9 результатів). */
    public function suggest(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['results' => [], 'total' => 0]);
        }

        $results = collect()
            ->concat(News::published()->searchPublic($q)->recent()->limit(3)->get()
                ->map(fn ($n) => ['group' => __('public.search_news'), 'title' => $n->localized('title'), 'url' => LocalizedUrl::route('news.show', $n)]))
            ->concat(Page::published()->searchPublic($q)->limit(3)->get()
                ->map(fn ($p) => ['group' => __('public.search_page'), 'title' => $p->localized('title'), 'url' => LocalizedUrl::to('/'.$p->slug)]))
            ->concat(Specialty::published()->searchPublic($q)->limit(2)->get()
                ->map(fn ($s) => ['group' => __('public.search_specialty'), 'title' => $s->localized('title'), 'url' => LocalizedUrl::route('specialties.show', $s)]))
            ->concat(Document::published()->searchPublic($q)->limit(2)->get()
                ->map(fn ($d) => ['group' => __('public.search_document'), 'title' => $d->localized('title'), 'url' => $d->file_url ?: LocalizedUrl::route('documents.index')]))
            ->concat(Event::published()->upcoming()->searchPublic($q)->limit(2)->get()
                ->map(fn ($e) => ['group' => __('public.search_event'), 'title' => $e->localized('title').' ('.$e->starts_at->format('d.m').')', 'url' => LocalizedUrl::route('events')]))
            ->take(9)
            ->values();

        return response()->json(['results' => $results, 'total' => $results->count()]);
    }
}
