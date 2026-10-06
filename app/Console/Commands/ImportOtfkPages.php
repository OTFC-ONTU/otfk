<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ReadsOtfkExport;
use App\Models\Page;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Імпорт текстів усіх змістовних сторінок старого сайту otfk.od.ua з ЛОКАЛЬНОГО
 * дзеркала (site-audit/…): content-export/**\/*.md → CMS-сторінки (Page).
 *
 * Відомі сторінки нового сайту оновлюються за мапою «старий шлях → наш slug»,
 * решта створюється з транслітерованим slug і вкладеністю (parent_id) за
 * розділами старого сайту. Зображення й документи копіюються у storage
 * (диск public, каталог imported/…), внутрішні посилання переписуються.
 *
 * Ідемпотентність: маркер <!--imported-from:URL--> у тілі; повторний запуск
 * знаходить сторінку за маркером (або slug) і оновлює її, не плодячи копій.
 */
class ImportOtfkPages extends Command
{
    use ReadsOtfkExport;

    protected $signature = 'otfk:import-pages
        {--from-export= : Шлях до локального дзеркала site-audit/… (обов\'язково)}
        {--dry-run : Показати план, нічого не зберігати}';

    protected $description = 'Імпорт CMS-сторінок старого сайту otfk.od.ua з локального дзеркала (site-audit)';

    /** Старий шлях → slug ІСНУЮЧОЇ сторінки нового сайту (оновлюємо тіло, лишаємо наш заголовок/місце). */
    private array $existingMap = [
        // Про коледж
        '/about_us/college_strategy' => 'stratehiya-rozvytku',
        '/about_us/college_today' => 'koledzh-sohodni',
        '/about_us/education_activity_concept' => 'kontseptsiya-osvitnoyi-diyalnosti',
        '/about_us/history_of_the_college' => 'istoriya',
        '/about_us/museum' => 'hordist-koledzhu',
        // Абітурієнту
        '/applicant/admission_rules' => 'pravyla-pryyomu',
        '/applicant/terms' => 'rozyasnennya-terminiv-pryyomu',
        '/applicant/cost_of_education' => 'vartist-navchannya',
        '/applicant/the_volume_of_the_state_order' => 'obsyah-derzhavnoho-zamovlennya',
        '/applicant/list_of_competitive_subjects' => 'perelik-konkursnykh-predmetiv',
        '/applicant/documents' => 'dokumenty-neobhidni-dlya-vstupu',
        '/applicant/certificates' => 'sertyfikaty',
        '/applicant/entrance_examination_programs' => 'prohramy-vstupnykh-vyprobuvan',
        '/applicant/test_schedule_9th_grade' => 'rozklad-vyprobuvan-9-klas',
        '/applicant/test_schedule_11th_grade' => 'rozklad-fakhovykh-vyprobuvan',
        '/applicant/number_of_vacancies' => 'kilkist-vakantnykh-mists',
        '/applicant/results_of_entrance_exams' => 'rezultaty-vstupnykh-ispytiv',
        '/applicant/rate_lists' => 'reytynhovi-spysky-rekomendovani',
        '/applicant/applicant_instruction_on_registration' => 'instruktsiya-elektronnyy-kabinet',
        '/applicant/preparatory_courses' => 'pidhotovchi-kursy',
        // Студенту
        '/student/teaching' => 'navchannya',
        '/student/diploma_design' => 'dyplomne-proektuvannya',
        '/student/training_and_production_work' => 'navchalno-vyrobnycha-robota',
        '/student/selective_disciplines' => 'vybirkovi-dystsypliny',
        '/student/fire_security' => 'pozhezhna-bezpeka',
        '/student/lifeguard_security' => 'ohorona-pratsi',
        '/student/dorm' => 'hurtozhytok',
        '/student/sport_life' => 'sportyvne-zhyttya',
        '/student/college_psychologist' => 'sotsialno-psyholohichna-sluzhba',
        '/student/educational_work' => 'vyhovna-robota',
        '/student/academic_ virtues' => 'akademichna-dobrochesnist',
        '/student/digital_publications_in_industries' => 'tsyfrovi-vydannya-u-haluzyah',
        '/student/preparation_for_external_evaluation' => 'pidhotovka-do-nmt',
        // Інше
        '/licensing_and_accreditation' => 'litsenzuvannya-ta-akredytatsiya',
        // Структура
        '/structure/library' => 'biblioteka',
        '/structure/student_government' => 'studentske-samovryaduvannya',
        '/structure/employment' => 'vzayemodiya-z-robotodavtsyamy',
    ];

    /**
     * Пропускаємо: службові сторінки, розділи з власними модулями нового сайту
     * (новини, документи, контакти, відео) та сторінки, що їдуть у Department/Staff
     * командою otfk:import-staff.
     */
    private array $skipExact = [
        '/', '/index.php', '/news', '/search.php', '/contacts', '/feedback', '/video',
        '/survey',                        // стара форма опитування (результати — імпортуються)
        '/study/zoom_training',           // дублікат /distance_learning/zoom_training
        '/structure/departments',         // → Department (otfk:import-staff)
        '/structure/chairs',              // → Department (otfk:import-staff)
        '/structure/cycles_commissions',  // → Department (otfk:import-staff)
    ];

    /** Регекспи шляхів, що їдуть у Department/Staff або пропускаються. */
    private array $skipPatterns = [
        '#^/news/#',
        '#^/public_information(/|$)#',                    // → категорії /dokumenty (otfk:import-docs)
        '#^/structure/cycles_commissions/[^/]+$#',        // сторінка комісії → Department.description
        '#^/structure/chairs/list/[^/]+$#',               // сторінка кафедри → Department.description
        '#^/structure/(cycles_commissions|chairs)/.*/personel$#', // склад → Staff
    ];

    /** Топ-розділ старого сайту → [slug сторінки-предка за замовчуванням, section]. */
    private array $sectionDefaults = [
        'about_us' => ['pro-koledzh', 'pro-koledzh'],
        'applicant' => ['abituriyentu', 'abituriyentu'],
        'student' => ['studentu', 'studentu'],
        'distance_learning' => ['dystantsiyne-navchannya', 'studentu'],
        'study' => ['dystantsiyne-navchannya', 'studentu'],
        'structure' => [null, 'struktura'],
        'licensing_and_accreditation' => ['pro-koledzh', 'pro-koledzh'],
        'attestation' => ['pro-koledzh', 'pro-koledzh'],
    ];

    public function handle(): int
    {
        if (blank($this->option('from-export')) || ! $this->initExport((string) $this->option('from-export'))) {
            $this->error('Потрібна опція --from-export=<шлях до site-audit/YYYY-MM-DD>.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');

        // 1. Збираємо всі md-файли експорту (крім новин).
        $files = $this->collectMarkdownFiles();
        $this->line('md-файлів у дзеркалі (без новин): ' . count($files));

        $plan = [];      // path => ['md' =>…, 'slug' =>…, 'exists' => bool]
        $skipped = 0;

        foreach ($files as $file) {
            $md = $this->parseExportMarkdown($file);

            if ($md === null) {
                $this->warn('  ? не розпізнано: ' . $file);

                continue;
            }

            $path = $md['path'];

            if ($this->shouldSkip($path) || isset($plan[$path])) {
                $skipped++;

                continue;
            }

            $plan[$path] = ['md' => $md, 'file' => $file];
        }

        // Сортуємо за глибиною, щоб батьки створювались раніше за нащадків.
        uksort($plan, fn ($a, $b) => [substr_count($a, '/'), $a] <=> [substr_count($b, '/'), $b]);

        // 2. Прохід 1: заголовки, slug-и, дерево (batьки) — щоб мапа посилань була повною.
        $created = 0;
        $updated = 0;

        // Розділ «Дистанційне навчання» — контейнер для distance_learning/*.
        if (! $dry && collect($plan)->keys()->contains(fn ($p) => Str::startsWith($p, ['/distance_learning', '/study']))) {
            $this->ensureDistancePage();
        }

        foreach ($plan as $path => &$item) {
            $marker = $this->markerFor($path);
            $mapped = $this->existingMap[$path] ?? null;

            $page = Page::query()->where('body', 'like', "%{$marker}%")->first()
                ?? ($mapped ? Page::where('slug', $mapped)->first() : null);

            $title = $this->cleanTitle($item['md']);

            if ($page === null) {
                $slug = $mapped ?? $this->uniqueSlug($title, $path);
                $item['exists'] = false;

                if ($dry) {
                    $item['slug'] = $slug;
                    $this->line("  [dry][new] {$path} → /{$slug}");

                    continue;
                }

                $page = Page::create([
                    'title' => Str::limit($title, 250, ''),
                    'slug' => $slug,
                    'parent_id' => $this->resolveParentId($path),
                    'section' => $this->sectionFor($path),
                    'body' => $marker, // тіло — у проході 2
                    'is_published' => true,
                    'sort_order' => 0,
                    'meta_description' => Str::limit((string) $item['md']['description'], 250, ''),
                ]);
                $created++;
            } else {
                $item['exists'] = true;
                $updated++;
            }

            $item['slug'] = $page->slug;
            $item['page_id'] = $page->id;
        }
        unset($item);

        if ($dry) {
            $this->info('[dry] Нових сторінок: ' . count(array_filter($plan, fn ($i) => ! ($i['exists'] ?? true)))
                . ', оновлюваних: ' . count(array_filter($plan, fn ($i) => $i['exists'] ?? false))
                . ", пропущено: {$skipped}");

            return self::SUCCESS;
        }

        // Повна мапа посилань: старий шлях → наш URL.
        $linkMap = [];
        foreach ($plan as $path => $item) {
            $linkMap[$path] = '/' . $item['slug'];
        }
        $linkMap += $this->moduleLinks();

        // 3. Прохід 2: тіла сторінок (картинки/файли → storage, посилання → нові URL).
        $done = 0;
        foreach ($plan as $path => $item) {
            $html = $this->convertExportBodyToHtml(
                $item['md']['body'],
                $path,
                fn (string $p) => $linkMap[$p] ?? null,
            );

            Page::whereKey($item['page_id'])->update([
                'body' => trim($html) . "\n" . $this->markerFor($path),
                'meta_description' => Str::limit((string) $item['md']['description'], 250, ''),
            ]);

            $done++;
            if ($done % 50 === 0) {
                $this->line("  …{$done} сторінок оброблено");
            }
        }

        $this->newLine();
        $this->info("Сторінок оброблено: {$done} (нових: {$created}, оновлено існуючих: {$updated}), пропущено файлів: {$skipped}.");

        return self::SUCCESS;
    }

    /** Всі md-файли content-export, крім новин та карт/службових файлів. */
    private function collectMarkdownFiles(): array
    {
        // Шляхи нормалізуємо до "/" — на Windows ітератор віддає їх з "\",
        // і префікс $root тоді не відрізається (news/ та files/ не відсіювались).
        $root = str_replace('\\', '/', $this->exportPath('content-export'));
        $out = [];

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            /** @var \SplFileInfo $f */
            if ($f->getExtension() !== 'md') {
                continue;
            }
            $pathname = str_replace('\\', '/', $f->getPathname());
            $rel = Str::after($pathname, $root . '/');
            if (Str::startsWith($rel, ['news/', 'files/', 'images/'])) {
                continue;
            }
            $out[] = $pathname;
        }

        sort($out);

        return $out;
    }

    private function shouldSkip(string $path): bool
    {
        if (in_array($path, $this->skipExact, true)) {
            return true;
        }

        foreach ($this->skipPatterns as $re) {
            if (preg_match($re, $path)) {
                return true;
            }
        }

        return false;
    }

    private function markerFor(string $path): string
    {
        return '<!--imported-from:https://otfk.od.ua' . $path . '-->';
    }

    /** Заголовок сторінки: H1 сторінки, а не службовий <title> старого сайту. */
    private function cleanTitle(array $md): string
    {
        $title = $md['title'];

        // «Одеський технічний коледж ОНАХТ - X» → X; якщо в тілі є власний заголовок — беремо його.
        if (preg_match('/коледж(?:у)?\s+(?:ОНАХТ|ОНТУ)\s*[-–—]\s*(.+)$/ui', $title, $m)
            || preg_match('/college\s+ONTU\s*[-–—]\s*(.+)$/ui', $title, $m)) {
            $title = trim($m[1]);
        }

        if (preg_match('/^#{1,6}\s+(.+)$/mu', $md['body'], $h)) {
            $bodyHeading = trim($h[1]);
            if (mb_strlen($bodyHeading) >= 3 && ! Str::startsWith($bodyHeading, '!')) {
                // Тіло починається з власного заголовка — він надійніший за <title>.
                $firstLine = Str::of($md['body'])->trim()->before("\n")->trim()->value();
                if (Str::startsWith($firstLine, '#')) {
                    $title = $bodyHeading;
                }
            }
        }

        return Str::squish($title);
    }

    /** Унікальний транслітерований slug (конвенція /novyny-стилю). */
    private function uniqueSlug(string $title, string $path): string
    {
        $slug = Str::slug(Str::limit($title, 90, '')) ?: Str::slug(basename($path));

        $base = $slug;
        $n = 2;
        while (Page::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }

    /** parent_id нової сторінки: найближчий імпортований предок або розділ за замовчуванням. */
    private function resolveParentId(string $path): ?int
    {
        $ancestor = $path;
        // dirname() на Windows повертає для "/section" саме "\", тож без заміни
        // сепаратора умова виходу ніколи не спрацьовує і цикл стає нескінченним.
        while (($ancestor = rtrim(str_replace('\\', '/', dirname($ancestor)), '/')) !== ''
            && ! in_array($ancestor, ['/', '.'], true)) {
            $mapped = $this->existingMap[$ancestor] ?? null;
            $page = $mapped
                ? Page::where('slug', $mapped)->first()
                : Page::where('body', 'like', '%' . $this->markerFor($ancestor) . '%')->first();

            if ($page) {
                return $page->id;
            }
        }

        $top = explode('/', ltrim($path, '/'))[0] ?? '';
        $slug = $this->sectionDefaults[$top][0] ?? null;

        return $slug ? Page::where('slug', $slug)->value('id') : null;
    }

    private function sectionFor(string $path): ?string
    {
        $top = explode('/', ltrim($path, '/'))[0] ?? '';

        return $this->sectionDefaults[$top][1] ?? null;
    }

    /** Розділ «Дистанційне навчання» під «Студенту» (ідемпотентно). */
    private function ensureDistancePage(): void
    {
        Page::firstOrCreate(
            ['slug' => 'dystantsiyne-navchannya'],
            [
                'title' => 'Дистанційне навчання',
                'parent_id' => Page::where('slug', 'studentu')->value('id'),
                'section' => 'studentu',
                'body' => '<p>Інструкції та матеріали для дистанційного навчання.</p>',
                'is_published' => true,
                'sort_order' => 0,
            ]
        );
    }

    /** Посилання на модулі нового сайту для шляхів, які не є CMS-сторінками. */
    private function moduleLinks(): array
    {
        $links = [
            '/news' => '/novyny',
            '/contacts' => '/kontakty',
            '/video' => '/video',
            '/conference' => '/novyny?category=konferentsiyi',
            '/feedback' => '/kontakty',
            '/structure/departments' => '/struktura#viddilennya',
            '/structure/cycles_commissions' => '/struktura#tsyklova-komisiya',
            '/structure/chairs' => '/struktura#kafedra',
        ];

        // Категорії документів: /public_information/x → /dokumenty/slug (мапа з otfk:import-docs).
        $docs = (new ImportOtfkDocs)->publicInformationMap();
        foreach ($docs as $old => $slug) {
            $links[$this->normalizeOldPath($old)] = '/dokumenty/' . $slug;
        }

        return $links;
    }
}
