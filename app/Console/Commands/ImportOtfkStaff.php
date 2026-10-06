<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ReadsOtfkExport;
use App\Models\Department;
use App\Models\Page;
use App\Models\Staff;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Імпорт структури та персоналу старого сайту otfk.od.ua з ЛОКАЛЬНОГО дзеркала:
 *
 * - structure/departments.md            → Department type=viddilennya (4 відділення);
 * - structure/cycles_commissions/*      → Department type=tsyklova-komisiya + Staff з таблиць
 *                                         «Викладацький склад» (personel/index.php.md);
 * - structure/chairs/*                  → Department type=kafedra (+ Staff з таблиці кафедри
 *                                         енергетичного машинобудування);
 * - about_us/leaders_of_the_college.md  → Staff category=administration.
 *
 * Фото копіюються у storage (диск public, imported/images/…). Посилання на
 * персональні сторінки викладачів (prof/kval/activity) резолвляться у CMS-сторінки,
 * імпортовані командою otfk:import-pages (запускати її ПЕРЕД цією командою).
 *
 * Ідемпотентність: updateOrCreate за slug (Department) та full_name (Staff).
 * Демо-дані SiteSeeder не чіпаємо; окрема опція --replace-demo видаляє лише
 * очевидно фейкові записи сидера перед імпортом.
 */
class ImportOtfkStaff extends Command
{
    use ReadsOtfkExport;

    protected $signature = 'otfk:import-staff
        {--from-export= : Шлях до локального дзеркала site-audit/… (обов\'язково)}
        {--dry-run : Показати план, нічого не зберігати}
        {--replace-demo : Спершу видалити демо-персонал і демо-підрозділи SiteSeeder}';

    protected $description = 'Імпорт відділень/комісій/кафедр (Department) і персоналу (Staff) з локального дзеркала старого сайту';

    /** Фейкові ПІБ із SiteSeeder — єдине, що видаляє --replace-demo серед персоналу. */
    private array $demoStaff = [
        'Петренко Олександр Іванович', 'Коваленко Марія Сергіївна', 'Шевченко Андрій Петрович',
        'Бондаренко Ольга Вікторівна', 'Ткаченко Ірина Олегівна', 'Мельник Сергій Васильович',
        'Кравчук Наталія Ігорівна', 'Поліщук Дмитро Олександрович',
    ];

    /** Slug-и демо-підрозділів SiteSeeder. */
    private array $demoDepartments = [
        'viddilennya-it', 'viddilennya-ekonomiky', 'ck-prohramnoyi-inzheneriyi',
        'ck-zahalnoosvitnih', 'kafedra-ipz', 'kafedra-zahalnotehnichnyh',
    ];

    public function handle(): int
    {
        if (blank($this->option('from-export')) || ! $this->initExport((string) $this->option('from-export'))) {
            $this->error('Потрібна опція --from-export=<шлях до site-audit/YYYY-MM-DD>.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');

        if ($this->option('replace-demo') && ! $dry) {
            $staff = Staff::whereIn('full_name', $this->demoStaff)->delete();
            $deps = Department::whereIn('slug', $this->demoDepartments)->delete();
            $this->warn("Видалено демо-записів SiteSeeder: персонал — {$staff}, підрозділи — {$deps}.");
        }

        $departments = 0;
        $staff = 0;

        // 1. Відділення (structure/departments.md, секції "#### …").
        foreach ($this->parseViddilennya() as $i => $dep) {
            $departments += $this->saveDepartment($dep, 'viddilennya', $i + 1, $dry);
        }

        // 2. Циклові комісії + викладацький склад.
        foreach ($this->parseCommissions() as $i => $dep) {
            $departments += $this->saveDepartment($dep, 'tsyklova-komisiya', 10 + $i, $dry);
            $staff += $this->saveStaffRows($dep, $dry);
        }

        // 3. Кафедри (сторінки chairs/list/* + «Кафедра економіки» з chairs.md).
        foreach ($this->parseChairs() as $i => $dep) {
            $departments += $this->saveDepartment($dep, 'kafedra', 20 + $i, $dry);
            $staff += $this->saveStaffRows($dep, $dry);
        }

        // 4. Адміністрація (about_us/leaders_of_the_college.md).
        $staff += $this->importAdministration($dry);

        $this->newLine();
        $this->info(($dry ? '[dry] Буде імпортовано' : 'Імпортовано') . ": підрозділів — {$departments}, персоналій — {$staff}.");

        return self::SUCCESS;
    }

    /* ------------------------------------------------------------------ */
    /* Парсинг                                                             */
    /* ------------------------------------------------------------------ */

    /** Відділення: секції "#### Назва" на structure/departments.md. */
    private function parseViddilennya(): array
    {
        $md = $this->parseExportMarkdown($this->exportPath('content-export/structure/departments.md'));

        if ($md === null) {
            return [];
        }

        $out = [];
        $parts = preg_split('/^####\s+(.+)$/mu', $md['body'], -1, PREG_SPLIT_DELIM_CAPTURE);

        for ($i = 1; $i < count($parts) - 1; $i += 2) {
            $out[] = [
                'title' => Str::squish($parts[$i]),
                'path' => '/structure/departments',
                'description_md' => trim($parts[$i + 1]),
                'rows' => [],
            ];
        }

        return $out;
    }

    /** Циклові комісії: каталоги structure/cycles_commissions/<dir>/. */
    private function parseCommissions(): array
    {
        $base = $this->exportPath('content-export/structure/cycles_commissions');
        $out = [];

        foreach (glob($base . '/*/index.php.md') ?: [] as $file) {
            $md = $this->parseExportMarkdown($file);
            if ($md === null) {
                continue;
            }

            $body = $this->stripPersonelLinks($md['body']);
            $personel = dirname($file) . '/personel/index.php.md';

            $out[] = [
                'title' => Str::squish($md['title']),
                'path' => $md['path'],
                'description_md' => $body,
                'rows' => $this->parseStaffTable($personel),
            ];
        }

        return $out;
    }

    /** Кафедри: сторінки chairs/list/* (склад — inline-таблиця) + «Кафедра економіки» з chairs.md. */
    private function parseChairs(): array
    {
        $out = [];

        foreach (glob($this->exportPath('content-export/structure/chairs/list') . '/*/index.php.md') ?: [] as $file) {
            $md = $this->parseExportMarkdown($file);
            if ($md === null) {
                continue;
            }

            [$body, $rows] = $this->extractInlineStaffTable($md['body'], $md['path']);

            $out[] = [
                'title' => Str::squish($md['title']),
                'path' => $md['path'],
                'description_md' => $this->stripPersonelLinks($body),
                'rows' => $rows,
            ];
        }

        // «Кафедра "Економіки"» описана секцією просто на chairs.md (без власної сторінки).
        $chairs = $this->parseExportMarkdown($this->exportPath('content-export/structure/chairs.md'));
        if ($chairs !== null && preg_match('/^####\s+Кафедра\s+(.+)$/mu', $chairs['body'], $m, PREG_OFFSET_CAPTURE)) {
            $title = 'Кафедра ' . Str::squish(trim($m[1][0], ' "«»'));
            $out[] = [
                'title' => $title,
                'path' => '/structure/chairs',
                'description_md' => trim(substr($chairs['body'], $m[0][1] + strlen($m[0][0]))),
                'rows' => [],
            ];
        }

        return $out;
    }

    /** Таблиця «Викладацький склад» із personel/index.php.md. */
    private function parseStaffTable(string $file): array
    {
        $md = $this->parseExportMarkdown($file);

        if ($md === null) {
            return [];
        }

        [, $rows] = $this->extractInlineStaffTable($md['body'], $md['path']);

        return $rows;
    }

    /**
     * Витягує рядки staff-таблиці "| ![](фото) | текст |" з markdown
     * і повертає [markdown-без-таблиці, рядки].
     */
    private function extractInlineStaffTable(string $body, string $pagePath): array
    {
        $rows = [];

        $clean = preg_replace_callback('/^\|\s*!\[[^\]]*\]\(([^)\s]+)\)\s*\|\s*(.+?)\s*\|\s*$/mu', function ($m) use (&$rows, $pagePath) {
            $rows[] = ['photo' => $m[1], 'cell' => $m[2], 'path' => $pagePath];

            return '';
        }, $body);

        // Прибираємо роздільники таблиці, що лишились.
        $clean = preg_replace('/^\|[\s|:-]+\|\s*$/mu', '', (string) $clean);

        return [trim((string) $clean), $rows];
    }

    /** Прибирає навігаційні лінки «Викладацький склад …» (на новому сайті склад — на тій самій сторінці). */
    private function stripPersonelLinks(string $md): string
    {
        return trim((string) preg_replace('/^-?\s*\[[^\]]*склад[^\]]*\]\([^)]*\)\s*$/muiu', '', $md));
    }

    /* ------------------------------------------------------------------ */
    /* Збереження                                                          */
    /* ------------------------------------------------------------------ */

    private function saveDepartment(array &$dep, string $type, int $order, bool $dry): int
    {
        $slug = Str::slug(Str::limit($dep['title'], 90, ''));
        $dep['slug'] = $slug;

        if ($dry) {
            $this->line(sprintf('  [dry] %-18s %s (%d осіб)', $type, $dep['title'], count($dep['rows'])));

            return 1;
        }

        $model = Department::updateOrCreate(
            ['slug' => $slug],
            [
                'title' => Str::limit($dep['title'], 250, ''),
                'type' => $type,
                'description' => $this->convertExportBodyToHtml($dep['description_md'], $dep['path'], $this->pageLinkResolver()),
                'sort_order' => $order,
                'is_published' => true,
            ]
        );

        $dep['id'] = $model->id;

        return 1;
    }

    private function saveStaffRows(array $dep, bool $dry): int
    {
        $count = 0;

        foreach ($dep['rows'] as $i => $row) {
            $cell = Str::squish($row['cell']);

            if (! preg_match('/^([А-ЯЇІЄҐ][\w\'’ʼ-]*(?:\s+[А-ЯЇІЄҐ][\w\'’ʼ-]*){1,2})/u', $cell, $nm)) {
                $this->warn('  ? не розпізнано ПІБ: ' . Str::limit($cell, 60));

                continue;
            }
            $name = Str::squish($nm[1]);

            // Посада: значення «**Посада:**» + роль перед ним («Голова циклової комісії …»), якщо є.
            $role = Str::squish(Str::of($cell)->after($nm[1])->before('**')->value());
            $posada = preg_match('/\*\*Посада:\*\*\s*([^*]+)/u', $cell, $p) ? Str::squish($p[1]) : null;
            $position = trim(implode(', ', array_filter([$role, $posada]))) ?: 'викладач';

            $degree = preg_match('/\*\*Вчене звання:\*\*\s*([^*]+)/u', $cell, $d) ? Str::squish($d[1]) : null;

            if ($dry) {
                $count++;

                continue;
            }

            // Фото — у storage; біо — повний текст комірки (посилання на персональні
            // сторінки викладачів ведуть на CMS-сторінки з otfk:import-pages).
            $photoOld = $this->normalizeAsset($this->resolveOldPath($row['photo'], $row['path']));
            $photo = $this->copyExportAsset($photoOld, $this->importedAssetTarget($photoOld));

            $bio = $this->convertExportBodyToHtml($row['cell'], $row['path'], $this->pageLinkResolver());

            Staff::updateOrCreate(
                ['full_name' => $name],
                [
                    'position' => Str::limit($position, 250, ''),
                    'category' => 'teacher',
                    'department_id' => $dep['id'] ?? null,
                    'photo' => $photo,
                    'academic_degree' => $degree ? Str::limit($degree, 250, '') : null,
                    'bio' => $bio,
                    'sort_order' => $i,
                    'is_published' => true,
                ]
            );

            $count++;
        }

        return $count;
    }

    /** Адміністрація: пари «фото + абзаци» на about_us/leaders_of_the_college.md. */
    private function importAdministration(bool $dry): int
    {
        $md = $this->parseExportMarkdown($this->exportPath('content-export/about_us/leaders_of_the_college.md'));

        if ($md === null) {
            return 0;
        }

        $parts = preg_split('/^!\[[^\]]*\]\(([^)\s]+)\)\s*$/mu', $md['body'], -1, PREG_SPLIT_DELIM_CAPTURE);
        $count = 0;

        // $parts: [вступ, фото1, текст1, фото2, текст2, …]
        for ($i = 1; $i < count($parts) - 1; $i += 2) {
            $photoRel = $parts[$i];
            $text = trim($parts[$i + 1]);

            if (! preg_match('/^([А-ЯЇІЄҐ][\w\'’ʼ-]*(?:\s+[А-ЯЇІЄҐ][\w\'’ʼ-]*){1,2})/u', Str::squish($text), $nm)) {
                continue;
            }
            $name = Str::squish($nm[1]);

            $position = preg_match('/^[^,]+,\s*(.+?)(?=[.\n]|Тел|$)/su', $text, $p)
                ? Str::squish(rtrim($p[1], ' ,'))
                : 'адміністрація';

            $phone = preg_match('/Тел\.?\s*([+\d ()\-]{7,})/u', $text, $ph) ? Str::squish($ph[1]) : null;

            if ($dry) {
                $count++;

                continue;
            }

            $photoOld = $this->normalizeAsset($this->resolveOldPath($photoRel, $md['path']));
            $photo = $this->copyExportAsset($photoOld, $this->importedAssetTarget($photoOld));

            Staff::updateOrCreate(
                ['full_name' => $name],
                [
                    'position' => Str::limit(Str::ucfirst($position), 250, ''),
                    'category' => 'administration',
                    'photo' => $photo,
                    'phone' => $phone,
                    'bio' => $this->exportMarkdownToHtml($text),
                    'sort_order' => intdiv($i, 2),
                    'is_published' => true,
                ]
            );

            $count++;
        }

        return $count;
    }

    /** Резолвер посилань на імпортовані CMS-сторінки (за маркером otfk:import-pages). */
    private function pageLinkResolver(): callable
    {
        return function (string $path): ?string {
            $slug = Page::query()
                ->where('body', 'like', '%<!--imported-from:https://otfk.od.ua' . $path . '-->%')
                ->value('slug');

            return $slug ? '/' . $slug : null;
        };
    }
}
