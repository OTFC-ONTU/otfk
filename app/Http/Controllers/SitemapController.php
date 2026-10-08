<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\DocumentCategory;
use App\Models\Gallery;
use App\Models\News;
use App\Models\Page;
use App\Models\Specialty;
use App\Models\Staff;
use App\Support\Seo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SitemapController extends Controller
{
    /** Кеш відносних адрес; скидається трейтом FlushesSitemap при збереженні матеріалів. */
    public const CACHE_KEY = 'sitemap.entries';

    private const CACHE_TTL = 3600;

    public function index()
    {
        // Кешуємо відносні шляхи: домен підставляється під час кожної відповіді.
        $entries = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => $this->entries());

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";
        foreach ($entries as $e) {
            $en = $e['en'] ?? null;
            // Пара мовних версій: обидві адреси з однаковим набором alternate.
            $alternates = '';
            if ($en) {
                foreach (['uk' => $e['path'], 'en' => $en, 'x-default' => $e['path']] as $lang => $href) {
                    $alternates .= '<xhtml:link rel="alternate" hreflang="'.$lang.'" href="'.htmlspecialchars(url($href)).'"/>';
                }
            }
            foreach (array_filter([$e['path'], $en]) as $path) {
                $xml .= '  <url><loc>'.htmlspecialchars(url($path)).'</loc>';
                if ($e['lastmod']) {
                    $xml .= '<lastmod>'.$e['lastmod'].'</lastmod>';
                }
                $xml .= '<changefreq>'.$e['changefreq'].'</changefreq>';
                $xml .= '<priority>'.$e['priority'].'</priority>'.$alternates.'</url>'."\n";
            }
        }
        $xml .= '</urlset>';

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }

    /**
     * Канонічні адреси опублікованих матеріалів: українська версія завжди,
     * `/en` — лише для розділів Seo::ENGLISH_LISTINGS і матеріалів з повним
     * опублікованим незастарілим перекладом (docs/seo-plan.md, етап 4). RSS —
     * окремий канал виявлення, у карту сторінок не входить. Вибираються лише
     * потрібні поля.
     *
     * @return list<array{path: string, en: ?string, lastmod: ?string, changefreq: string, priority: string}>
     */
    private function entries(): array
    {
        $entries = [];
        $add = function (string $path, mixed $updatedAt, string $changefreq, string $priority, ?string $en = null) use (&$entries) {
            $entries[$path] ??= [
                'path' => $path,
                'en' => $en,
                'lastmod' => $updatedAt ? Carbon::parse($updatedAt)->toDateString() : null,
                'changefreq' => $changefreq,
                'priority' => $priority,
            ];
        };
        $path = fn (string $name, array $parameters = []) => route($name, $parameters, false);

        // Статичні розділи: без точної дати зміни. Контакти з текстом CMS-сторінки
        // індексуються англійською лише разом із її перекладом.
        $kontakty = Page::published()->where('slug', 'kontakty')->first();
        $contactsEnglish = ! ($kontakty && filled($kontakty->body)) || $kontakty->hasIndexableEnglishTranslation();

        $add('/', null, 'daily', '1.0', $path('en.home'));
        $sections = [
            'news.index' => '0.9', 'specialties.index' => '0.9',
            'events' => '0.8', 'documents.index' => '0.6', 'structure.index' => '0.6',
            'galleries.index' => '0.6', 'contacts' => '0.6', 'faq' => '0.6',
            'staff.administration' => '0.5', 'video.index' => '0.5', 'bells' => '0.5',
        ];
        foreach ($sections as $name => $priority) {
            $english = in_array($name, Seo::ENGLISH_LISTINGS, true) && ($name !== 'contacts' || $contactsEnglish);
            $add($path($name), null, 'weekly', $priority, $english ? $path('en.'.$name) : null);
        }

        // Динамічні сторінки: lastmod з updated_at (лічильники його не змінюють).
        $english = $this->englishSlugs(News::published());
        foreach (News::published()->toBase()->orderByDesc('published_at')->get(['slug', 'updated_at']) as $n) {
            $en = isset($english[$n->slug]) ? $path('en.news.show', ['news' => $n->slug]) : null;
            $add($path('news.show', ['news' => $n->slug]), $n->updated_at, 'monthly', '0.7', $en);
        }
        $english = $this->englishSlugs(Page::published());
        foreach (Page::published()->toBase()->get(['slug', 'updated_at']) as $p) {
            // Слаг, який перехоплює спеціальний маршрут, не є адресою цієї сторінки.
            if ($this->servedByPageRoute($p->slug)) {
                $add('/'.$p->slug, $p->updated_at, 'monthly', '0.6', isset($english[$p->slug]) ? '/en/'.$p->slug : null);
            }
        }
        $english = $this->englishSlugs(Specialty::published());
        foreach (Specialty::published()->toBase()->get(['slug', 'updated_at']) as $s) {
            $en = isset($english[$s->slug]) ? $path('en.specialties.show', ['specialty' => $s->slug]) : null;
            $add($path('specialties.show', ['specialty' => $s->slug]), $s->updated_at, 'monthly', '0.7', $en);
        }
        $english = $this->englishSlugs(Department::published());
        foreach (Department::published()->toBase()->get(['slug', 'updated_at']) as $d) {
            $en = isset($english[$d->slug]) ? $path('en.structure.show', ['department' => $d->slug]) : null;
            $add($path('structure.show', ['department' => $d->slug]), $d->updated_at, 'monthly', '0.5', $en);
        }
        $english = $this->englishSlugs(Gallery::published()->with('photos:id,gallery_id,caption,caption_en,translation_published,translation_source_hash'));
        foreach (Gallery::published()->toBase()->get(['slug', 'updated_at']) as $g) {
            $en = isset($english[$g->slug]) ? $path('en.galleries.show', ['gallery' => $g->slug]) : null;
            $add($path('galleries.show', ['gallery' => $g->slug]), $g->updated_at, 'monthly', '0.5', $en);
        }
        $english = $this->englishSlugs(Staff::published()->whereNotNull('slug'));
        foreach (Staff::published()->whereNotNull('slug')->toBase()->get(['slug', 'updated_at']) as $person) {
            $en = isset($english[$person->slug]) ? $path('en.staff.show', ['staff' => $person->slug]) : null;
            $add($path('staff.show', ['staff' => $person->slug]), $person->updated_at, 'monthly', '0.4', $en);
        }
        $english = $this->englishSlugs(DocumentCategory::query());
        foreach (DocumentCategory::query()->toBase()->get(['slug', 'updated_at']) as $c) {
            $en = isset($english[$c->slug]) ? $path('en.documents.category', ['documentCategory' => $c->slug]) : null;
            $add($path('documents.category', ['documentCategory' => $c->slug]), $c->updated_at, 'monthly', '0.5', $en);
        }

        return array_values($entries);
    }

    /**
     * Слаги матеріалів, чия англійська версія індексується: SQL відбирає повні
     * опубліковані переклади, застарілість (хеш оригіналу) перевіряється в PHP
     * лише для них, частинами й лише по полях перекладу.
     *
     * @return array<string, true>
     */
    private function englishSlugs(Builder $query): array
    {
        $model = $query->getModel();
        $columns = array_values(array_unique(array_merge([$model->getKeyName(), 'slug'], $model->translationColumns())));

        $slugs = [];
        foreach ($query->withPublishedEnglishTranslation()->select($columns)->lazyById(200) as $record) {
            if (filled($record->slug) && $record->hasIndexableEnglishTranslation()) {
                $slugs[$record->slug] = true;
            }
        }

        return $slugs;
    }

    /** Чи відкриває адреса `/{slug}` саме CMS-сторінку, а не інший маршрут. */
    private function servedByPageRoute(?string $slug): bool
    {
        if (blank($slug) || str_contains($slug, '/')) {
            return false;
        }

        try {
            return Route::getRoutes()->match(Request::create('/'.$slug))->getName() === 'pages.show';
        } catch (HttpException) {
            return false;
        }
    }
}
