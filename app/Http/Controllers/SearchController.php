<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Event;
use App\Models\MenuItem;
use App\Models\News;
use App\Models\Page;
use App\Models\Specialty;
use App\Support\LocalizedUrl;
use App\Support\SearchQuery;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SearchController extends Controller
{
    /** Скільки результатів показуємо на сторінці. */
    private const PER_PAGE = 12;

    /** Типи результатів: ключ фільтра => [назва, назва в множині, іконка]; підписи — словник public.search_*. */
    private const GROUPS = [
        'news' => ['Новина', 'Новини', 'newspaper'],
        'pages' => ['Сторінка', 'Сторінки', 'document-text'],
        'specialties' => ['Спеціальність', 'Спеціальності', 'academic-cap'],
        'documents' => ['Документ', 'Документи', 'folder'],
        'events' => ['Подія', 'Події', 'calendar-days'],
    ];

    public function index(Request $request)
    {
        $q = SearchQuery::from($request);
        $type = SearchQuery::from($request, 'type');

        if (! isset(self::GROUPS[$type])) {
            $type = '';
        }

        $all = mb_strlen($q) >= 2 ? $this->collectResults($q) : new Collection();

        // Лічильники по типах — для чипів-фільтрів (рахуємо до фільтрації)
        $counts = $all->countBy('group');

        $filtered = $type === '' ? $all : $all->where('group', $type)->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $results = new LengthAwarePaginator(
            $filtered->forPage($page, self::PER_PAGE)->values(),
            $filtered->count(),
            self::PER_PAGE,
            $page,
            ['path' => LocalizedUrl::route('search'), 'query' => array_filter(['q' => $q, 'type' => $type])],
        );

        return view('search.index', [
            'q' => $q,
            'type' => $type,
            'results' => $results,
            'counts' => $counts,
            'total' => $all->count(),
            'groups' => collect(self::GROUPS)->map(fn ($values, $key) => [$this->label($key), __('public.search_groups.'.$key), $values[2]])->all(),
            'quickLinks' => $this->quickLinks(),
        ]);
    }

    /** Миттєві підказки для пошуку в шапці (JSON, до 9 результатів). */
    public function suggest(Request $request)
    {
        $q = SearchQuery::from($request);

        if (mb_strlen($q) < 2) {
            return response()->json(['results' => [], 'total' => 0]);
        }

        $all = $this->collectResults($q);

        // По кілька результатів кожного типу, щоб дропдаун не займала одна секція
        $limits = ['news' => 3, 'pages' => 3, 'specialties' => 2, 'documents' => 2, 'events' => 2];

        $results = collect($limits)
            ->flatMap(fn (int $limit, string $group) => $all->where('group', $group)->take($limit)->values())
            ->map(fn (array $r) => ['group' => $r['label'], 'title' => $r['title'].($r['group'] === 'events' ? ' ('.$r['date'].')' : ''), 'url' => $r['url']])
            ->take(9)
            ->values();

        return response()->json(['results' => $results, 'total' => $results->count()]);
    }

    /**
     * Усі збіги за запитом, згруповані за типом.
     *
     * Фільтруємо колекцію в PHP через mb_stripos, а не через `where('title','like',…)`:
     * у SQLite (dev/тести) LIKE регістронезалежний лише для ASCII, тож «положення»
     * не знайшло б «Положення …» (Gotcha 21); на MySQL SearchQuery::prefilter() спершу звужує вибірку. Повні атрибути потрібні для перевірки цілісності перекладу.
     */
    private function collectResults(string $q): Collection
    {
        $rows = function ($query) use ($q) {
            if (app()->getLocale() === 'en') {
                return $query->searchPublic($q)->get();
            }
            return SearchQuery::prefilter($query, 'title', $q)->get()->filter(fn ($row) => mb_stripos((string) $row->title, $q) !== false)->values();
        };
        $news = $rows(News::published()->recent())->map(fn (News $n) => $this->item('news', $n->localized('title'), LocalizedUrl::route('news.show', $n), $n->localized('excerpt'), $n->published_at?->translatedFormat('j F Y')));
        $pages = $rows(Page::published()->with('parent'))->map(fn (Page $p) => $this->item('pages', $p->localized('title'), LocalizedUrl::to('/'.$p->slug), $p->localized('excerpt'), $p->parent?->localized('title')));
        $specialties = $rows(Specialty::published()->ordered())->map(fn (Specialty $s) => $this->item('specialties', $s->localized('title'), LocalizedUrl::route('specialties.show', $s), $s->localized('short_description'), $s->code));
        $documents = $rows(Document::published()->with('category'))->map(fn (Document $d) => $this->item('documents', $d->localized('title'), $d->file_url ?: LocalizedUrl::route('documents.index'), $d->localized('description'), $d->category?->localized('title'), (bool) $d->file_url));
        $events = $rows(Event::published()->upcoming())->map(fn (Event $e) => $this->item('events', $e->localized('title'), LocalizedUrl::route('events'), $e->localized('description'), $e->starts_at?->translatedFormat('j F Y')) + ['date' => $e->starts_at->format('d.m')]);
        return $news->concat($pages)->concat($specialties)->concat($documents)->concat($events)->values();
    }

    /** Назва типу результату в однині («Новина», «Документ»). */
    private function label(string $group): string
    {
        return __('public.search_'.['news' => 'news', 'pages' => 'page', 'specialties' => 'specialty', 'documents' => 'document', 'events' => 'event'][$group]);
    }

    /** Один результат пошуку у форматі, який очікує шаблон. */
    private function item(string $group, string $title, string $url, ?string $excerpt, ?string $meta = null, bool $external = false): array
    {
        return [
            'group' => $group,
            'label' => $this->label($group),
            'icon' => self::GROUPS[$group][2],
            'title' => $title,
            'url' => $url,
            'excerpt' => $excerpt ? \Illuminate\Support\Str::limit(strip_tags($excerpt), 180) : null,
            'meta' => $meta,
            'external' => $external,
        ];
    }

    /**
     * Швидкі посилання для порожнього запиту та стану «нічого не знайдено» —
     * верхній рівень меню з адмінки, без хардкоду розділів у шаблоні.
     */
    private function quickLinks(): Collection
    {
        return MenuItem::navigation()
            ->map(fn (MenuItem $item) => ['label' => $item->localized_label, 'url' => $item->href])
            ->filter(fn (array $link) => $link['url'] !== '#')
            ->take(8)
            ->values();
    }
}
