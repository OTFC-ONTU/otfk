<?php

namespace App\Support;

use App\Models\DocumentCategory;
use App\Models\LegacyRedirect;
use App\Models\NotFoundLog;
use App\Models\Page;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\ImplicitRouteBinding;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Старі адреси сайту (docs/seo-plan.md, етап 1).
 *
 * Карта `legacy_redirects` читається лише тоді, коли застосунок уже формує 404
 * (bootstrap/app.php → render NotFoundHttpException): редирект не може перекрити
 * живу сторінку й не додає роботи звичайним запитам. Невідома адреса потрапляє
 * до журналу `not_found_logs`; помилка журналу ніколи не ламає відповідь.
 */
class LegacyRedirects
{
    private const VERSION_KEY = 'legacy_redirects.version';

    private const CACHE_TTL = 86400;

    /** Службові адреси, які не перенаправляються і не журналюються. */
    private const SKIP = ['admin', 'admin-preview', 'livewire', 'build', 'vendor', 'up', '_debugbar'];

    /** Відповідь для 404: редирект/410 з карти або null (стандартна сторінка 404 + запис у журнал). */
    public static function handleNotFound(Request $request): ?Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return null;
        }
        if (self::skipped($request->getPathInfo())) {
            return null;
        }

        try {
            [$path, $query] = self::normalize($request->getPathInfo(), (string) $request->server('QUERY_STRING'));
            $match = self::lookup($path, $query);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        if ($match) {
            self::countHit($match['id']);

            return $match['action'] === LegacyRedirect::GONE
                ? response()->view('errors.410', [], 410)
                : redirect()->to(self::absoluteTarget($request, $match['target']), $match['code']);
        }

        NotFoundLog::record($request, $path, $query);

        return null;
    }

    /**
     * Нормалізація старої адреси: декодований шлях без подвійних і кінцевого
     * слешів (регістр зберігається) та лише значущі параметри старої CMS у
     * сталому порядку. Параметри відстеження (utm_*, fbclid…) відкидаються.
     *
     * @return array{0: string, 1: ?string}
     */
    public static function normalize(string $path, ?string $query = null): array
    {
        $path = rawurldecode($path);
        $path = '/'.ltrim((string) preg_replace('~/{2,}~', '/', $path), '/');
        // Старий сайт віддавав ту саму сторінку за /розділ/ і /розділ/index.php — одна адреса.
        $path = (string) preg_replace('~(^|/)index\.php$~', '/', $path);
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        $keys = array_map('strtolower', (array) config('otfk.legacy.query_keys', []));
        $kept = [];
        foreach (explode('&', (string) $query) as $pair) {
            if ($pair === '') {
                continue;
            }
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $key = strtolower(rawurldecode(str_replace('+', ' ', $key)));
            if (in_array($key, $keys, true)) {
                $kept[$key] = rawurldecode(str_replace('+', ' ', $value));
            }
        }
        ksort($kept);

        return [$path, $kept === [] ? null : http_build_query($kept, '', '&', PHP_QUERY_RFC3986)];
    }

    /** Розбирає адресу з CSV/форми: відносну або абсолютну на основному домені. */
    public static function parseSource(string $source): ?array
    {
        $source = trim($source);
        $parts = parse_url($source);
        if ($source === '' || $parts === false || str_contains($source, '\\')) {
            return null;
        }
        if (isset($parts['host'])) {
            $host = strtolower(preg_replace('/^www\./i', '', $parts['host']));
            if ($host !== strtolower((string) config('otfk.seo.primary_host')) || isset($parts['user'])) {
                return null;
            }
        } elseif (isset($parts['scheme']) || ! str_starts_with($source, '/') || str_starts_with($source, '//')) {
            return null;
        }

        return self::normalize($parts['path'] ?? '/', $parts['query'] ?? null);
    }

    public static function hash(string $path, ?string $query): string
    {
        return hash('sha256', $path."\n".($query ?? ''));
    }

    /** Чи відповідає адреса зараз 200: маршрут із наявною моделлю або файл у public/storage. */
    public static function resolves(string $url): bool
    {
        $path = rawurldecode((string) (parse_url($url, PHP_URL_PATH) ?: '/'));

        if (str_starts_with($path, '/storage/')) {
            return Storage::disk('public')->exists(substr($path, strlen('/storage/')));
        }
        if ($path !== '/' && is_file(public_path(ltrim($path, '/')))) {
            return true;
        }

        try {
            $route = Route::getRoutes()->match(Request::create($path, 'GET'));
            ImplicitRouteBinding::resolveForRoute(app(), $route);
        } catch (Throwable) {
            return false;
        }

        // Модель знайдено — але гість має отримати 200: чернетка, майбутня новість
        // чи CMS-сторінка, що сама переадресовує на розділ документів, не є призначенням.
        foreach ($route->parameters() as $parameter) {
            if ($parameter instanceof Model && ! self::publiclyServed($parameter)) {
                return false;
            }
        }

        return true;
    }

    /** Чи віддає публічна частина цей запис гостю зі статусом 200 (той самий scope published()). */
    private static function publiclyServed(Model $model): bool
    {
        if ($model instanceof Page && DocumentCategory::where('page_id', $model->getKey())->exists()) {
            return false;
        }
        if (method_exists($model, 'scopePublished')) {
            return $model->newQuery()->published()->whereKey($model->getKey())->exists();
        }

        return true;
    }

    /**
     * Старі посилання http:// і www. основного домену .htaccess пропускає до Laravel
     * (розділи старого сайту), тож редирект одразу веде на https без www — один перехід.
     * Інші домени (тестовий хостинг, localhost) отримують відносну адресу як раніше.
     */
    private static function absoluteTarget(Request $request, string $target): string
    {
        $primary = strtolower((string) config('otfk.seo.primary_host'));
        $host = (string) preg_replace('/^www\./', '', strtolower($request->getHost()));

        return $primary !== '' && $host === $primary ? 'https://'.$primary.$target : $target;
    }

    public static function flush(): void
    {
        Cache::forever(self::VERSION_KEY, (int) Cache::get(self::VERSION_KEY, 0) + 1);
    }

    public static function skipped(string $path): bool
    {
        $first = strtolower(explode('/', trim($path, '/'))[0] ?? '');

        return in_array($first, self::SKIP, true);
    }

    /**
     * Спершу точний збіг path+query, потім запис лише за path. Результат
     * (включно з відсутністю запису) кешується до наступної зміни карти.
     *
     * @return array{id: int, action: string, target: ?string, code: int}|null
     */
    private static function lookup(string $path, ?string $query): ?array
    {
        $version = (int) Cache::get(self::VERSION_KEY, 0);
        $hashes = array_unique(array_filter([
            $query !== null ? self::hash($path, $query) : null,
            self::hash($path, null),
        ]));

        foreach ($hashes as $hash) {
            // Кешуються лише знайдені записи: промахи (бот-сміття, випадкові адреси) не
            // пишуть рядків у кеш-таблицю — для них досить індексованого запиту за source_hash.
            $key = "legacy_redirects.{$version}.{$hash}";
            if ($row = Cache::get($key)) {
                return $row;
            }
            $record = LegacyRedirect::query()->where('source_hash', $hash)->where('is_active', true)->first();
            if ($record) {
                $row = [
                    'id' => $record->id,
                    'action' => $record->action,
                    'target' => $record->target_url,
                    'code' => (int) $record->status_code,
                ];
                Cache::put($key, $row, self::CACHE_TTL);

                return $row;
            }
        }

        return null;
    }

    private static function countHit(int $id): void
    {
        try {
            LegacyRedirect::whereKey($id)->toBase()->update([
                'hits' => DB::raw('hits + 1'),
                'last_hit_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
