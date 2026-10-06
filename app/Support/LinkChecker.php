<?php

namespace App\Support;

use App\Filament\Resources\NewsResource;
use App\Filament\Resources\PageResource;
use App\Models\Department;
use App\Models\DocumentCategory;
use App\Models\Gallery;
use App\Models\News;
use App\Models\Page;
use App\Models\Specialty;
use App\Models\Staff;
use Illuminate\Support\Facades\Storage;

/**
 * Перевірка внутрішніх посилань у контенті (тіла сторінок і новин) на биті
 * цілі: неіснуючі сторінки/новини/розділи та відсутні файли у сховищі.
 * Актуально після імпорту старого сайту — частина PDF так і не переїхала
 * (див. ARCHITECTURE.md, розділ про імпорт). Зовнішні сайти не перевіряємо,
 * але посилання на старий otfk.od.ua позначаємо окремо.
 */
class LinkChecker
{
    /** Топ-рівневі шляхи з явними маршрутами (routes/web.php) — завжди живі. */
    private const STATIC_PATHS = [
        'novyny', 'video', 'rozklad-dzvinkiv', 'podiyi', 'faq', 'kviz',
        'dokumenty', 'spetsialnosti', 'struktura', 'administratsiya',
        'halereya', 'poshuk', 'kontakty', 'sitemap.xml', 'robots.txt', 'admin',
    ];

    /** @var array<class-string, array<string, int>> slug-довідники на час сканування */
    private array $slugSets = [];

    /** @var array<string, bool>|null slug сторінки → чи опублікована */
    private ?array $pageSlugs = null;

    /**
     * @return list<array{source: string, title: string, edit_url: string, url: string, reason: string}>
     */
    public function scan(): array
    {
        $broken = [];

        Page::query()->select(['id', 'title', 'slug', 'body'])->chunkById(100, function ($pages) use (&$broken) {
            foreach ($pages as $page) {
                $this->collect($broken, (string) $page->body, 'Сторінка', $page->title,
                    PageResource::getUrl('edit', ['record' => $page]));
            }
        });

        News::query()->select(['id', 'title', 'slug', 'body'])->chunkById(100, function ($items) use (&$broken) {
            foreach ($items as $news) {
                $this->collect($broken, (string) $news->body, 'Новина', $news->title,
                    NewsResource::getUrl('edit', ['record' => $news]));
            }
        });

        return $broken;
    }

    /** @param list<array{source: string, title: string, edit_url: string, url: string, reason: string}> $broken */
    private function collect(array &$broken, string $html, string $source, string $title, string $editUrl): void
    {
        preg_match_all('/(?:href|src)\s*=\s*["\']([^"\']+)["\']/iu', $html, $matches);

        foreach (array_unique($matches[1]) as $url) {
            if ($reason = $this->checkUrl($url)) {
                $broken[] = [
                    'source' => $source,
                    'title' => $title,
                    'edit_url' => $editUrl,
                    'url' => $url,
                    'reason' => $reason,
                ];
            }
        }
    }

    /** null — посилання живе або не наше; рядок — опис проблеми. */
    private function checkUrl(string $url): ?string
    {
        $url = trim(html_entity_decode($url));

        if ($url === '' || $url[0] === '#' || preg_match('~^(mailto:|tel:|javascript:|data:)~i', $url)) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if ($host !== null) {
            if (in_array(strtolower($host), ['otfk.od.ua', 'www.otfk.od.ua'], true)) {
                return 'веде на старий сайт otfk.od.ua';
            }

            if (strcasecmp($host, (string) parse_url((string) config('app.url'), PHP_URL_HOST)) !== 0) {
                return null; // зовнішні сайти не перевіряємо
            }
        }

        $path = urldecode(trim((string) parse_url($url, PHP_URL_PATH), '/'));

        if ($path === '') {
            return null; // головна або чистий якір
        }

        $segments = explode('/', $path);

        if ($segments[0] === 'storage') {
            $file = implode('/', array_slice($segments, 1));

            return Storage::disk('public')->exists($file) ? null : 'файл відсутній у сховищі';
        }

        if (count($segments) === 1) {
            if (in_array($segments[0], self::STATIC_PATHS, true)) {
                return null;
            }

            return match ($this->pageSlugs()[$segments[0]] ?? null) {
                null => 'сторінки з таким slug не існує',
                false => 'сторінка існує, але не опублікована (чернетка)',
                default => null,
            };
        }

        $slug = $segments[1];

        return match ($segments[0]) {
            'novyny' => $slug === 'feed.xml' || $this->exists(News::class, $slug) ? null : 'новини з таким slug не існує',
            'dokumenty' => $this->exists(DocumentCategory::class, $slug) ? null : 'категорії документів не існує',
            'spetsialnosti' => $this->exists(Specialty::class, $slug) ? null : 'спеціальності не існує',
            'struktura' => $this->exists(Department::class, $slug) ? null : 'підрозділу не існує',
            'personal' => $this->exists(Staff::class, $slug) ? null : 'співробітника не існує',
            'halereya' => $this->exists(Gallery::class, $slug) ? null : 'галереї не існує',
            'admin', 'admin-preview', 'livewire', 'build', 'vendor', 'podiyi', 'video', 'poshuk' => null,
            default => 'невідомий шлях — на сайті немає такого розділу',
        };
    }

    /** @return array<string, bool> */
    private function pageSlugs(): array
    {
        return $this->pageSlugs ??= Page::query()->pluck('is_published', 'slug')
            ->map(fn ($v) => (bool) $v)->all();
    }

    /** @param class-string<\Illuminate\Database\Eloquent\Model> $model */
    private function exists(string $model, string $slug): bool
    {
        $this->slugSets[$model] ??= $model::query()->pluck('slug')->flip()->all();

        return isset($this->slugSets[$model][$slug]);
    }
}
