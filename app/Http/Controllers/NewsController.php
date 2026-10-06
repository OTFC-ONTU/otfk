<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use App\Models\News;
use App\Models\NewsCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class NewsController extends Controller
{
    public function index(Request $request)
    {
        $categories = NewsCategory::orderBy('sort_order')->get();

        $query = News::published()->recent()->with('category');

        $activeCategory = null;
        if ($slug = $request->query('category')) {
            $activeCategory = NewsCategory::where('slug', $slug)->first();
            if ($activeCategory) {
                $query->where('category_id', $activeCategory->id);
            }
        }

        // Фільтр за роком (архів з 2014-го)
        $activeYear = (int) $request->query('year') ?: null;
        if ($activeYear) {
            $query->whereYear('published_at', $activeYear);
        }

        $years = News::published()->whereNotNull('published_at')
            ->get(['published_at'])
            ->map(fn ($n) => $n->published_at->year)
            ->unique()->sortDesc()->values();

        $news = $query->paginate(9)->withQueryString();

        return view('news.index', compact('news', 'categories', 'activeCategory', 'years', 'activeYear'));
    }

    public function show(Request $request, News $news)
    {
        // Чернетки бачать лише залогінені адміністратори (превʼю з адмінки).
        abort_unless($news->is_published || auth()->check(), 404);

        $news->loadMissing('category');

        // Чесний лічильник: +1 лише раз за сесію відвідувача (не накручується F5);
        // превʼю чернетки перегляди не накручує.
        if ($news->is_published && ! $request->session()->has("viewed_news.{$news->id}")) {
            $news->increment('views');
            $request->session()->put("viewed_news.{$news->id}", true);
        }

        $liked = $news->likeRecords()
            ->where('fingerprint', $this->fingerprint($request))
            ->exists();

        $related = News::published()->recent()
            ->whereKeyNot($news->id)
            ->limit(5)
            ->get();

        [$prev, $next] = $this->neighbours($news);

        return view('news.show', [
            'news' => $news,
            'related' => $related,
            'liked' => $liked,
            'prev' => $prev,
            'next' => $next,
            'abiturientLinks' => $this->abiturientLinks(),
        ]);
    }

    /**
     * Сусідні новини у стрічці: [попередня (старіша), наступна (новіша)].
     * Порядок такий самий, як у scopeRecent — дата, далі id.
     *
     * @return array{0: ?News, 1: ?News}
     */
    private function neighbours(News $news): array
    {
        if (! $news->published_at) {
            return [null, null];
        }

        $at = $news->published_at;

        $prev = News::published()
            ->where(fn ($q) => $q->where('published_at', '<', $at)
                ->orWhere(fn ($q2) => $q2->where('published_at', $at)->where('id', '<', $news->id)))
            ->recent()
            ->first();

        $next = News::published()
            ->where(fn ($q) => $q->where('published_at', '>', $at)
                ->orWhere(fn ($q2) => $q2->where('published_at', $at)->where('id', '>', $news->id)))
            ->orderBy('published_at')->orderBy('id')
            ->first();

        return [$prev, $next];
    }

    /**
     * Корисні посилання для абітурієнта — діти пункту меню «Абітурієнту».
     * Нічого не хардкодимо: немає такого пункту в меню — блок не показуємо.
     */
    private function abiturientLinks(): Collection
    {
        $root = MenuItem::navigation()
            ->first(fn (MenuItem $item) => $item->page?->slug === 'abituriyentu'
                || str_ends_with($item->href, '/abituriyentu'));

        return $root ? $root->children->take(5) : collect();
    }

    /** Вподобайка без реєстрації: один лайк на відвідувача, повторний клік знімає. */
    public function like(Request $request, News $news)
    {
        abort_unless($news->is_published, 404);

        $fp = $this->fingerprint($request);

        $existing = $news->likeRecords()->where('fingerprint', $fp)->first();

        if ($existing) {
            $existing->delete();
            $news->where('id', $news->id)->where('likes', '>', 0)->decrement('likes');
            $liked = false;
        } else {
            $news->likeRecords()->create(['fingerprint' => $fp]);
            $news->increment('likes');
            $liked = true;
        }

        return response()->json([
            'likes' => (int) $news->fresh()->likes,
            'liked' => $liked,
        ]);
    }

    /** Анонімний відбиток відвідувача (IP + браузер) — без реєстрації та кук. */
    private function fingerprint(Request $request): string
    {
        return sha1($request->ip() . '|' . (string) $request->userAgent());
    }
}
