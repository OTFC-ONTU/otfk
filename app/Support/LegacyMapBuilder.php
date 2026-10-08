<?php

namespace App\Support;

use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\FileMirror;
use App\Models\LegacyRedirect;
use App\Models\News;
use App\Models\Page;
use App\Models\Specialty;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Збирання карти «стара адреса → нова адреса» для otfk:legacy-redirects
 * (docs/seo-plan.md, етап 1, п. 3 і «Перенесення файлів»). Лише читання БД
 * і публічного диска.
 *
 * Джерела за пріоритетом (менше — сильніше):
 *  1. маркери <!--imported-from:URL--> у тілах матеріалів → публічна українська адреса запису;
 *  2. file_mirrors зі статусом done → FileMirror::publicUrl() (записаний path важливіший за шаблон);
 *  3. виведені файлові відповідності: фото новин news/imported/<ім'я>, активи сторінок
 *     imported/{images,files}/<старий шлях>, PDF документів documents/<категорія>/<md5 URL>.pdf.
 *
 * Однакова стара адреса з різними призначеннями одного пріоритету — конфлікт
 * (рядок не пишеться); нижчий пріоритет поступається вищому з поміткою.
 * Фото новин зберігалися лише за basename: старий шлях беремо тільки з відомих
 * адрес (списки, sitemap, збережені сторінки), кілька різних шляхів з одним
 * ім'ям — конфлікт, а не здогадка.
 */
class LegacyMapBuilder
{
    public const PRIORITY_MARKER = 1;

    public const PRIORITY_MIRROR = 2;

    public const PRIORITY_FILE = 3;

    /**
     * Моделі з маркерами імпорту: клас, поле HTML і ім'я українського маршруту.
     * Видимість — той самий scope published(), що й у публічній частині.
     */
    private const MARKER_SOURCES = [
        ['model' => Page::class, 'column' => 'body', 'route' => 'pages.show', 'label' => 'page'],
        ['model' => News::class, 'column' => 'body', 'route' => 'news.show', 'label' => 'news'],
        ['model' => Specialty::class, 'column' => 'description', 'route' => 'specialties.show', 'label' => 'specialty'],
        ['model' => Department::class, 'column' => 'description', 'route' => 'structure.show', 'label' => 'department'],
        ['model' => Staff::class, 'column' => 'bio', 'route' => 'staff.show', 'label' => 'staff'],
    ];

    private const IMAGE_EXT = '/\.(jpe?g|png|gif|webp)$/i';

    /** @var array<string, list<array>> кандидати за хешем старої адреси */
    private array $candidates = [];

    /** @var array<string, array{path: string, query: ?string, origin: string}> адреси зі списків/sitemap (для звіту unmapped) */
    private array $listed = [];

    /** @var array<string, string> відомі старі шляхи (нормалізовані) з усіх джерел */
    private array $known = [];

    /** @var list<array> */
    private array $rows = [];

    /** @var list<array> */
    private array $conflicts = [];

    /** @var list<array> */
    private array $unmapped = [];

    /** @var array<string, array<string, int>> лічильники за джерелом */
    private array $stats = [];

    private int $foreign = 0;

    /** @var array<string, true> старі адреси, вже згадані у звітах (непублічні) */
    private array $listedCovered = [];

    /** @var array<string, array> обрані рядки карти за хешем старої адреси */
    private array $mapped = [];

    public function __construct(private readonly string $oldHost) {}

    /**
     * Адреси старого сайту зі списку (Search Console, обхід) чи sitemap: використовуються
     * для пошуку файлів і потрапляють у звіт unmapped, якщо карта їх не покриває.
     */
    public function addListedUrl(string $url, string $origin): void
    {
        $parsed = $this->parseOld($url);
        if (! $parsed) {
            $this->foreign++;

            return;
        }
        [$path, $query] = $parsed;
        $this->listed[LegacyRedirects::hash($path, $query)] ??= ['path' => $path, 'query' => $query, 'origin' => $origin];
        $this->known[$path] = $path;
    }

    /** Адреса, знайдена у збережених сторінках старого сайту: лише для зіставлення файлів, без звіту unmapped. */
    public function addKnownUrl(string $url): void
    {
        if ($parsed = $this->parseOld($url)) {
            $this->known[$parsed[0]] = $parsed[0];
        }
    }

    /**
     * Витягує адреси (src/href/data, markdown-посилання) зі збережених HTML/MD сторінок.
     * Відносні посилання без кореня пропускаються.
     */
    public function scanDirectory(string $dir): int
    {
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (! $file->isFile() || ! preg_match('/\.(html?|md)$/i', $file->getFilename())) {
                continue;
            }
            $content = (string) file_get_contents($file->getPathname());
            preg_match_all('~(?:src|href|data)\s*=\s*["\']([^"\']+)["\']|\]\(([^)\s]+)~iu', $content, $m, PREG_SET_ORDER);
            foreach ($m as $match) {
                $url = html_entity_decode(str_replace('\\', '/', $match[1] !== '' ? $match[1] : ($match[2] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($this->parseOld($url)) {
                    $this->addKnownUrl($url);
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * @return array{rows: list<array>, conflicts: list<array>, unmapped: list<array>, stats: array, foreign: int}
     */
    public function build(bool $verify = false, bool $guessPhotos = false): array
    {
        $this->collectMarkers();
        $this->collectMirrors();
        $this->collectNewsPhotos($guessPhotos);
        $this->collectPageAssets();
        $this->collectDocuments();

        $this->resolve($verify);
        $this->collectUnmapped();

        usort($this->rows, fn ($a, $b) => [$a['origin'], $a['source']] <=> [$b['origin'], $b['source']]);

        return [
            'rows' => $this->rows,
            'conflicts' => $this->conflicts,
            'unmapped' => $this->unmapped,
            'stats' => $this->stats,
            'foreign' => $this->foreign,
        ];
    }

    // ── Джерела ──────────────────────────────────────────────────────────

    private function collectMarkers(): void
    {
        // CMS-сторінка розділу публічної інформації сама переадресовує на /dokumenty/{slug}
        // (PageController) — карта веде одразу туди, без проміжного переходу.
        $sectionTargets = Schema::hasColumn('document_categories', 'page_id')
            ? DocumentCategory::query()->whereNotNull('page_id')->pluck('slug', 'page_id')
                ->map(fn ($slug) => route('documents.category', $slug, false))->all()
            : [];
        // Стара сторінка «Викладачі комісії …» переадресовує на блок викладачів комісії — карта веде туди
        foreach (DepartmentStaffAnchor::pageDepartments() as $pageId => $department) {
            $sectionTargets[$pageId] = DepartmentStaffAnchor::path($department);
        }

        foreach (self::MARKER_SOURCES as $source) {
            /** @var class-string<Model> $model */
            $model = $source['model'];
            $table = (new $model)->getTable();
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $source['column'])) {
                continue;
            }
            $visible = $model::query()->published()->pluck('id')->flip();
            $origin = 'marker:'.$source['label'];

            $model::query()->toBase()
                ->select(['id', 'slug', $source['column'].' as html'])
                ->where($source['column'], 'like', '%<!--imported-from:%')
                ->orderBy('id')
                ->chunk(200, function ($records) use ($source, $visible, $origin, $sectionTargets) {
                    foreach ($records as $record) {
                        preg_match_all('/<!--imported-from:(.+?)-->/u', (string) $record->html, $m);
                        foreach (array_unique($m[1]) as $url) {
                            $parsed = $this->parseOld(trim($url));
                            if (! $parsed) {
                                $this->count($origin, 'foreign');

                                continue;
                            }
                            [$path, $query] = $parsed;
                            $this->known[$path] = $path;
                            $target = blank($record->slug) ? null : route($source['route'], $record->slug, false);
                            if ($source['model'] === Page::class && isset($sectionTargets[$record->id])) {
                                $target = $sectionTargets[$record->id];
                            }
                            $note = "{$source['label']} #{$record->id}";

                            if (! isset($visible[$record->id]) || $target === null) {
                                $this->unmapped[] = $this->unmappedRow($path, $query, 'unpublished', $target ?? '', $origin, $note);
                                $this->count($origin, 'unpublished');
                                // Стара адреса відома — не повторювати її як «unmapped» зі списків.
                                $this->listedCovered[LegacyRedirects::hash($path, $query)] = true;

                                continue;
                            }

                            $this->add($path, $query, $target, $origin, self::PRIORITY_MARKER, $note, ['route']);
                        }
                    }
                });
        }
    }

    private function collectMirrors(): void
    {
        if (! Schema::hasTable('file_mirrors')) {
            return;
        }
        $hasSha = Schema::hasColumn('file_mirrors', 'sha256');

        FileMirror::query()->where('status', FileMirror::DONE)->orderBy('id')->chunk(500, function ($mirrors) use ($hasSha) {
            foreach ($mirrors as $mirror) {
                $parsed = $this->parseOld((string) $mirror->source_url);
                $target = $mirror->publicUrl();
                if (! $parsed) {
                    $this->count('mirror', 'foreign');

                    continue;
                }
                if ($target === null) {
                    continue;
                }
                [$path, $query] = $parsed;
                $this->known[$path] = $path;
                $this->add($path, $query, $target, 'mirror', self::PRIORITY_MIRROR, "file_mirrors #{$mirror->id}",
                    ['storage', (string) $mirror->path, $hasSha ? $mirror->sha256 : null]);
            }
        });
    }

    /**
     * Фото новин: імпорт зберігав лише basename (/uploads/a/b.jpg → news/imported/b.jpg),
     * тож старий шлях відновлюється тільки з відомих адрес. Без доказів — звіт, не здогадка
     * (крім явного --guess-photos: /uploads/<ім'я>).
     */
    private function collectNewsPhotos(bool $guess): void
    {
        $disk = Storage::disk('public');
        if (! $disk->exists('news/imported')) {
            return;
        }

        $byName = [];
        foreach ($this->known as $path) {
            if (preg_match(self::IMAGE_EXT, $path)) {
                $byName[basename($path)][$path] = $path;
            }
        }

        foreach ($disk->files('news/imported') as $file) {
            $name = basename($file);
            if (! preg_match(self::IMAGE_EXT, $name)) {
                continue;
            }
            $target = '/storage/news/imported/'.rawurlencode($name);
            $paths = array_values($byName[$name] ?? []);

            if (count($paths) > 1) {
                foreach ($paths as $path) {
                    $this->conflict($path, null, $target, 'basename-collision', 'news-photo',
                        'кілька старих шляхів з іменем '.$name.': '.implode(' | ', $paths).' — звірити фото вручну');
                }
                $this->count('news-photo', 'conflict');

                continue;
            }
            if ($paths === []) {
                if ($guess) {
                    $this->add('/uploads/'.$name, null, $target, 'news-photo', self::PRIORITY_FILE, 'guess: /uploads/<ім\'я>', ['storage', $file, null]);
                } else {
                    $this->unmapped[] = $this->unmappedRow('/uploads/'.$name, null, 'photo-unconfirmed', $target, 'news-photo', 'старий шлях невідомий; припущення /uploads/<ім\'я>');
                    $this->count('news-photo', 'unconfirmed');
                }

                continue;
            }
            $this->add($paths[0], null, $target, 'news-photo', self::PRIORITY_FILE, '', ['storage', $file, null]);
        }
    }

    /**
     * Активи сторінок (ReadsOtfkExport::importedAssetTarget): imported/{images,files}/<старий шлях>,
     * де недозволені символи замінено «_». Точна стара адреса — з відомих адрес, інакше шлях як є.
     */
    private function collectPageAssets(): void
    {
        $disk = Storage::disk('public');
        if (! $disk->exists('imported')) {
            return;
        }

        $bySanitized = [];
        foreach ($this->known as $path) {
            $bySanitized[self::sanitizeAsset($path)][$path] = $path;
        }

        foreach ($disk->allFiles('imported') as $file) {
            if (! preg_match('~^imported/(images|files)/(.+)$~u', $file, $m)) {
                continue;
            }
            $target = '/storage/'.implode('/', array_map('rawurlencode', explode('/', $file)));
            $paths = array_values($bySanitized[$m[2]] ?? []);

            if (count($paths) > 1) {
                foreach ($paths as $path) {
                    $this->conflict($path, null, $target, 'asset-collision', 'page-asset', 'кілька старих шляхів дали той самий файл: '.implode(' | ', $paths));
                }
                $this->count('page-asset', 'conflict');

                continue;
            }
            $note = $paths === [] && str_contains($m[2], '_') ? 'inferred: «_» міг замінити пробіл чи інший символ' : '';
            $this->add($paths[0] ?? '/'.$m[2], null, $target, 'page-asset', self::PRIORITY_FILE, $note, ['storage', $file, null]);
        }
    }

    /** PDF документів зберігались як documents/<категорія>/<md5(URL)>.pdf: зіставляються лише відомі адреси. */
    private function collectDocuments(): void
    {
        $disk = Storage::disk('public');
        if (! $disk->exists('documents') || $this->known === []) {
            return;
        }

        $byHash = [];
        foreach ($disk->allFiles('documents') as $file) {
            if (preg_match('~/([0-9a-f]{32})\.pdf$~', $file, $m)) {
                $byHash[$m[1]] = $file;
            }
        }
        if ($byHash === []) {
            return;
        }

        $published = Document::query()->toBase()->pluck('is_published', 'file_path');

        foreach ($this->known as $path) {
            if (! preg_match('/\.pdf$/i', $path)) {
                continue;
            }
            $encoded = self::encodePath($path);
            foreach (['https://', 'http://', 'https://www.', 'http://www.'] as $prefix) {
                foreach (array_unique([$encoded, $path]) as $variant) {
                    $file = $byHash[md5($prefix.$this->oldHost.$variant)] ?? null;
                    if ($file === null) {
                        continue;
                    }
                    $target = '/storage/'.implode('/', array_map('rawurlencode', explode('/', $file)));
                    if (isset($published[$file]) && ! $published[$file]) {
                        $this->unmapped[] = $this->unmappedRow($path, null, 'unpublished', $target, 'document', 'документ знято з публікації');
                        $this->count('document', 'unpublished');
                    } else {
                        $this->add($path, null, $target, 'document', self::PRIORITY_FILE, '', ['storage', $file, null]);
                    }

                    continue 3;
                }
            }
        }
    }

    // ── Зведення ─────────────────────────────────────────────────────────

    private function resolve(bool $verify): void
    {
        $chosen = [];

        foreach ($this->candidates as $hash => $list) {
            usort($list, fn ($a, $b) => $a['priority'] <=> $b['priority']);
            $best = $list[0]['priority'];
            $top = array_filter($list, fn ($c) => $c['priority'] === $best);
            $targets = array_unique(array_column($top, 'target'));

            if (count($targets) > 1) {
                foreach ($top as $c) {
                    $this->conflict($c['path'], $c['query'], $c['target'], 'different-targets', $c['origin'], 'одна стара адреса — кілька призначень: '.implode(' | ', $targets));
                }
                $this->count($top[array_key_first($top)]['origin'], 'conflict');

                continue;
            }

            $row = $list[0];
            foreach ($list as $c) {
                if ($c['priority'] !== $best && $c['target'] !== $row['target']) {
                    $this->conflict($c['path'], $c['query'], $c['target'], 'superseded', $c['origin'], "поступається {$row['origin']} → {$row['target']} (інформаційно)");
                }
            }

            $source = self::encodePath($row['path']).($row['query'] !== null ? '?'.$row['query'] : '');
            [$targetPath, $targetQuery] = LegacyRedirects::normalize((string) parse_url($row['target'], PHP_URL_PATH), parse_url($row['target'], PHP_URL_QUERY));

            if ($targetPath === $row['path'] && $targetQuery === $row['query']) {
                $this->count($row['origin'], 'unchanged');

                continue;
            }
            if (LegacyRedirects::skipped($row['path'])) {
                $this->conflict($row['path'], $row['query'], $row['target'], 'service-path', $row['origin'], 'службова адреса не перенаправляється');
                $this->count($row['origin'], 'conflict');

                continue;
            }
            if ($row['query'] === null && LegacyRedirects::resolves($source)) {
                $this->conflict($row['path'], null, $row['target'], 'live-path', $row['origin'], 'стара адреса зараз відповідає 200 на новому сайті — редирект не спрацює; перевірити, чи це той самий матеріал');
                $this->count($row['origin'], 'live');

                continue;
            }
            if ($error = LegacyRedirect::targetError($row['target'])) {
                $this->conflict($row['path'], $row['query'], $row['target'], 'bad-target', $row['origin'], $error);
                $this->count($row['origin'], 'conflict');

                continue;
            }
            if ($verify && ($problem = $this->verifyTarget($row))) {
                $this->conflict($row['path'], $row['query'], $row['target'], 'verify', $row['origin'], $problem);
                $this->count($row['origin'], 'verify-failed');

                continue;
            }

            $chosen[$hash] = $row + ['source' => $source];
        }

        // Ланцюжки: призначення рядка саме є старою адресою іншого рядка.
        foreach ($chosen as $hash => $row) {
            [$targetHash, $targetPathHash] = LegacyRedirect::targetHashes($row['target']);
            if (isset($chosen[$targetHash]) || isset($chosen[$targetPathHash])) {
                $this->conflict($row['path'], $row['query'], $row['target'], 'chain', $row['origin'], 'призначення є старою адресою іншого рядка карти');
                $this->count($row['origin'], 'conflict');
                unset($chosen[$hash]);
            }
        }

        foreach ($chosen as $row) {
            $this->rows[] = ['source' => $row['source'], 'target' => $row['target'], 'code' => 301, 'note' => trim($row['origin'].($row['note'] !== '' ? ' — '.$row['note'] : '')), 'origin' => $row['origin']];
            $this->count($row['origin'], 'mapped');
        }

        $this->mapped = $chosen;
    }

    private function collectUnmapped(): void
    {
        foreach ($this->listed as $hash => $item) {
            $pathHash = LegacyRedirects::hash($item['path'], null);
            if (isset($this->candidates[$hash]) || isset($this->mapped[$pathHash]) || isset($this->listedCovered[$hash])) {
                continue;
            }
            if ($item['query'] === null && LegacyRedirects::resolves(self::encodePath($item['path']))) {
                $this->count($item['origin'], 'live');

                continue;
            }
            $this->unmapped[] = $this->unmappedRow($item['path'], $item['query'], 'unmapped', '', $item['origin'], 'вирішує редактор: відповідний матеріал, 410 за затвердженим списком або 404');
            $this->count($item['origin'], 'unmapped');
        }
    }

    private function verifyTarget(array $row): ?string
    {
        $check = $row['check'];
        if ($check[0] === 'route') {
            return LegacyRedirects::resolves($row['target']) ? null : 'маршрут призначення не відповідає 200';
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($check[1])) {
            return 'файлу немає на публічному диску: '.$check[1];
        }
        if (filled($check[2] ?? null)) {
            try {
                $actual = hash_file('sha256', $disk->path($check[1]));
            } catch (Throwable) {
                $actual = null;
            }
            if ($actual !== $check[2]) {
                return 'sha256 файлу не збігається з реєстром file_mirrors';
            }
        }

        return null;
    }

    // ── Допоміжне ────────────────────────────────────────────────────────

    /**
     * Стара адреса (відносна або абсолютна на старому домені, з www чи без) →
     * нормалізовані [path, query] тими самими правилами, що й LegacyRedirects.
     *
     * @return array{0: string, 1: ?string}|null
     */
    public function parseOld(string $url): ?array
    {
        $url = trim(str_replace('\\', '/', $url));
        if ($url === '' || str_starts_with($url, '#') || preg_match('/[\x00-\x1F]/', $url)) {
            return null;
        }
        $url = (string) preg_replace('/#.*$/s', '', $url);
        $parts = parse_url($url);
        if ($parts === false) {
            return null;
        }
        if (isset($parts['host'])) {
            $host = strtolower((string) preg_replace('/^www\./i', '', $parts['host']));
            $scheme = strtolower($parts['scheme'] ?? 'https');
            if ($host !== strtolower($this->oldHost) || ! in_array($scheme, ['http', 'https'], true) || isset($parts['user'])) {
                return null;
            }
        } elseif (isset($parts['scheme']) || ! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return null;
        }

        return LegacyRedirects::normalize($parts['path'] ?? '/', $parts['query'] ?? null);
    }

    /** Кодує сегменти нормалізованого шляху: імпорт декодує їх назад тим самим rawurldecode. */
    public static function encodePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    /** Те саме перетворення, що ReadsOtfkExport::importedAssetTarget() робить зі старим шляхом. */
    private static function sanitizeAsset(string $path): string
    {
        return (string) preg_replace('/[^\pL\pN_\-.\/]+/u', '_', ltrim($path, '/'));
    }

    private function add(string $path, ?string $query, string $target, string $origin, int $priority, string $note, array $check): void
    {
        $hash = LegacyRedirects::hash($path, $query);
        foreach ($this->candidates[$hash] ?? [] as $existing) {
            if ($existing['target'] === $target && $existing['priority'] === $priority) {
                return; // той самий зв'язок з іншого запису — не дубль
            }
        }
        $this->candidates[$hash][] = compact('path', 'query', 'target', 'origin', 'priority', 'note', 'check');
    }

    private function conflict(string $path, ?string $query, string $target, string $type, string $origin, string $detail): void
    {
        $this->conflicts[] = [
            'source' => self::encodePath($path).($query !== null ? '?'.$query : ''),
            'target' => $target,
            'type' => $type,
            'origin' => $origin,
            'detail' => $detail,
        ];
    }

    private function unmappedRow(string $path, ?string $query, string $reason, string $suggested, string $origin, string $note): array
    {
        return [
            'source' => self::encodePath($path).($query !== null ? '?'.$query : ''),
            'reason' => $reason,
            'suggested_target' => $suggested,
            'origin' => $origin,
            'note' => $note,
        ];
    }

    private function count(string $origin, string $key): void
    {
        $this->stats[$origin][$key] = ($this->stats[$origin][$key] ?? 0) + 1;
    }
}
