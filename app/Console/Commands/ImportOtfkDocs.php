<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ReadsOtfkExport;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Page;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportOtfkDocs extends Command
{
    use ReadsOtfkExport;

    protected $signature = 'otfk:import-docs {--dry-run : Показати, без завантаження} {--audit : Лише підрахувати всі PDF по розділах} {--from-export= : Читати з локального дзеркала site-audit/… замість HTTP} {--fresh : Видалити попередній імпорт перед завантаженням}';

    protected $description = 'Імпорт PDF зі старого сайту otfk.od.ua: у Документи (Публічна інформація) та у тіло сторінок (Абітурієнту/Студенту)';

    private string $base = 'https://otfk.od.ua';

    /** Сторінка old-сайту => slug нашої КАТЕГОРІЇ документів (Публічна інформація). */
    private array $docMap = [
        '/public_information/provision/' => 'normatyvna-baza',
        '/public_information/contracts/' => 'dohovory',
        '/public_information/reports/' => 'zvity',
        '/public_information/work_plan/' => 'plan-roboty',
        '/public_information/to_discuss/' => 'do-obhovorennya',
        '/public_information/student_survey/' => 'rezultaty-opytuvannya',
        '/public_information/monitoring_of_edu_quality_indicators/' => 'monitorynh-yakosti-osvity',
        '/public_information/election_of_director_otk/' => 'vybory-dyrektora',
        '/public_information/election_of_rector/' => 'vybory-rektora',
        '/public_information/public_organization/' => 'hromadska-orhanizatsiya',
        '/public_information/classroom_foundation/' => 'audytornyy-fond',
        '/public_information/budget_of_the_college/' => 'byudzhet-koledzhu',
        '/public_information/financial_report/' => 'finansovyy-zvit',
        '/public_information/information_about_received_charitable/' => 'blahodiyna-dopomoha',
        '/public_information/conditions_of_inclusiveness/' => 'vysnovok-inklyuzyvnist',
        '/public_information/vacant_positions/' => 'vakantni-posady',
        '/public_information/civil_control_labor_protection/' => 'tsyvilnyy-zahyst-ohorona-pratsi',
        // Звірено із sitemap.json дзеркала site-audit/2026-08-28: єдиний розділ
        // public_information/*, якого бракувало в мапі (категорія вже існує в БД).
        '/public_information/tot_acception/' => 'vyznannya-rezultativ-tot',
        // Освітньо-професійні програми та навчальні плани (~62 PDF) — окремий
        // розділ «Абітурієнту» на старому сайті; категорію створює міграція
        // 2026_08_28_190000_seed_opp_document_category_and_menu.
        '/applicant/educational_and_professional_programs/' => 'osvitno-profesiyni-prohramy',
    ];

    /** Сторінка old-сайту => slug нашої СТОРІНКИ (файли вставляються в тіло сторінки). */
    private array $pageMap = [
        // Про коледж
        '/about_us/college_strategy/' => 'stratehiya-rozvytku',
        // Абітурієнту
        '/applicant/admission_rules/' => 'pravyla-pryyomu',
        '/applicant/terms/' => 'rozyasnennya-terminiv-pryyomu',
        '/applicant/cost_of_education/' => 'vartist-navchannya',
        '/applicant/the_volume_of_the_state_order/' => 'obsyah-derzhavnoho-zamovlennya',
        '/applicant/list_of_competitive_subjects/' => 'perelik-konkursnykh-predmetiv',
        '/applicant/documents/' => 'dokumenty-neobhidni-dlya-vstupu',
        '/applicant/certificates/' => 'sertyfikaty',
        '/applicant/entrance_examination_programs/' => 'prohramy-vstupnykh-vyprobuvan',
        '/applicant/test_schedule_9th_grade/' => 'rozklad-vyprobuvan-9-klas',
        '/applicant/test_schedule_11th_grade/' => 'rozklad-fakhovykh-vyprobuvan',
        '/applicant/number_of_vacancies/' => 'kilkist-vakantnykh-mists',
        '/applicant/results_of_entrance_exams/' => 'rezultaty-vstupnykh-ispytiv',
        '/applicant/rate_lists/' => 'reytynhovi-spysky-rekomendovani',
        // Студенту
        '/student/teaching/' => 'navchannya',
        '/student/diploma_design/' => 'dyplomne-proektuvannya',
        '/student/training_and_production_work/' => 'navchalno-vyrobnycha-robota',
        '/student/selective_disciplines/' => 'vybirkovi-dystsypliny',
        '/student/fire_security/' => 'pozhezhna-bezpeka',
        '/student/lifeguard_security/' => 'ohorona-pratsi',
        // Структура
        '/structure/library/' => 'biblioteka',
        '/structure/student_government/' => 'studentske-samovryaduvannya',
        '/structure/employment/' => 'vzayemodiya-z-robotodavtsyamy',
    ];

    /** Мапа «стара сторінка public_information → slug категорії документів» (для інших команд). */
    public function publicInformationMap(): array
    {
        return $this->docMap;
    }

    public function handle(): int
    {
        if (filled($this->option('from-export')) && ! $this->initExport((string) $this->option('from-export'))) {
            return self::FAILURE;
        }

        if ($this->option('audit')) {
            return $this->audit();
        }

        $dry = (bool) $this->option('dry-run');

        if ($this->option('fresh') && ! $dry) {
            $this->purge();
        }

        $this->info('=== ДОКУМЕНТИ (Публічна інформація) ===');
        [$f1, $d1] = $this->importDocuments($dry);

        $this->newLine();
        $this->info('=== ФАЙЛИ ДО СТОРІНОК (Абітурієнту / Студенту / Стратегія) ===');
        [$f2, $d2] = $this->importPageFiles($dry);

        $this->newLine();
        $this->info('Усього знайдено PDF: ' . ($f1 + $f2) . '. ' . ($dry ? 'Буде оброблено' : 'Оброблено') . ': ' . ($d1 + $d2) . '.');

        return self::SUCCESS;
    }

    private function purge(): void
    {
        $this->warn('Очищення попереднього імпорту (документи з файлами + блоки на сторінках)...');

        foreach (array_unique(array_values($this->docMap)) as $slug) {
            $cat = DocumentCategory::where('slug', $slug)->first();
            if (! $cat) {
                continue;
            }
            $cat->documents()->whereNotNull('file_path')->delete();
            Storage::disk('public')->deleteDirectory('documents/' . $slug);
        }

        foreach (array_unique(array_values($this->pageMap)) as $slug) {
            Storage::disk('public')->deleteDirectory('page-files/' . $slug);
            $page = Page::where('slug', $slug)->first();
            if ($page) {
                $body = preg_replace('/<!--imported-files-->.*?<!--\/imported-files-->/s', '', $page->body ?? '');
                $page->body = trim((string) $body);
                $page->save();
            }
        }

        $this->info('Попередній імпорт видалено.');
    }

    private function importDocuments(bool $dry): array
    {
        $found = 0;
        $done = 0;

        foreach ($this->docMap as $path => $catSlug) {
            $category = DocumentCategory::where('slug', $catSlug)->first();
            if (! $category) {
                continue;
            }
            $links = $this->pdfLinks($path);
            $this->line("\n<info>{$category->title}</info> ({$path}) - " . count($links) . ' PDF');
            $order = (int) $category->documents()->max('sort_order');

            foreach ($links as $l) {
                $found++;
                if (Document::where('document_category_id', $category->id)->where('title', $l['title'])->exists()) {
                    continue;
                }
                if ($dry) {
                    $this->line("  + (dry) {$l['title']}");
                    $done++;
                    continue;
                }
                $stored = 'documents/' . $category->slug . '/' . md5($l['url']) . '.pdf';
                if ($this->download($l['url'], $stored)) {
                    Document::create([
                        'document_category_id' => $category->id,
                        'title' => $l['title'],
                        'file_path' => $stored,
                        'published_at' => now(),
                        'sort_order' => ++$order,
                        'is_published' => true,
                    ]);
                    $done++;
                }
            }
        }

        return [$found, $done];
    }

    private function importPageFiles(bool $dry): array
    {
        $found = 0;
        $done = 0;

        foreach ($this->pageMap as $path => $slug) {
            $page = Page::where('slug', $slug)->first();
            if (! $page) {
                continue;
            }
            $links = $this->pdfLinks($path);
            $this->line("\n<info>{$page->title}</info> ({$path}) - " . count($links) . ' PDF');
            if (empty($links)) {
                continue;
            }

            $items = '';
            foreach ($links as $l) {
                $found++;
                if ($dry) {
                    $this->line("  + (dry) {$l['title']}");
                    $done++;
                    continue;
                }
                $stored = 'page-files/' . $slug . '/' . md5($l['url']) . '.pdf';
                if ($this->download($l['url'], $stored)) {
                    $items .= '<li><a href="/storage/' . $stored . '" target="_blank" rel="noopener">'
                        . e($l['title']) . ' (PDF)</a></li>' . "\n";
                    $done++;
                }
            }

            if (! $dry && $items !== '') {
                $block = "<!--imported-files-->\n<h2>Документи для завантаження</h2>\n<ul>\n{$items}</ul>\n<!--/imported-files-->";
                // прибрати попередній блок (ідемпотентність)
                $body = preg_replace('/<!--imported-files-->.*?<!--\/imported-files-->/s', '', $page->body ?? '');
                $body = trim((string) $body);
                // якщо лишилась тільки заглушка - замінити коротким вступом
                if ($body === '' || Str::contains($body, 'наповнюється')) {
                    $body = '<p>Актуальні документи розділу доступні для завантаження нижче.</p>';
                }
                $page->body = $body . "\n" . $block;
                $page->save();
            }
        }

        return [$found, $done];
    }

    private function audit(): int
    {
        $total = 0;
        $this->info('АУДИТ PDF на старому сайті:');
        foreach (['ДОКУМЕНТИ' => $this->docMap, 'СТОРІНКИ' => $this->pageMap] as $group => $map) {
            $this->newLine();
            $this->line("<comment>== {$group} ==</comment>");
            foreach ($map as $path => $target) {
                $n = count($this->pdfLinks($path));
                $total += $n;
                $this->line(sprintf('  %3d PDF  %s', $n, $path));
            }
        }
        $this->newLine();
        $this->info("УСЬОГО PDF на охоплених сторінках: {$total}");

        return self::SUCCESS;
    }

    /** Знайти PDF-посилання на сторінці: [['url'=>.., 'title'=>..], ...]. */
    private function pdfLinks(string $pagePath): array
    {
        if ($this->exportRoot !== null) {
            // Локальне дзеркало: збережений HTML з _raw/html/ замість HTTP.
            $html = $this->exportRawHtml($pagePath);

            if ($html === null) {
                $this->error("  У дзеркалі немає збереженого HTML для {$pagePath}");

                return [];
            }
        } else {
            try {
                $html = Http::withoutVerifying()->timeout(30)->get($this->base . $pagePath)->body();
            } catch (\Throwable $e) {
                $this->error("  Сторінка недоступна {$pagePath}: " . $e->getMessage());

                return [];
            }
        }

        $titles = []; // url => title

        // 1) <a href="...pdf">текст</a> - найкращі назви з тексту посилання
        preg_match_all('/<a[^>]+href=["\']([^"\']+\.pdf[^"\']*)["\'][^>]*>(.*?)<\/a>/is', $html, $a, PREG_SET_ORDER);
        foreach ($a as $row) {
            $url = $this->absolute(html_entity_decode($row[1]), $pagePath);
            $title = Str::of(strip_tags($row[2]))->squish()->ltrim('-')->squish()->limit(240)->value();
            if ($title !== '' && ! isset($titles[$url])) {
                $titles[$url] = $title;
            }
        }

        // 2) будь-які href/src/data на .pdf (iframe, embed, object, кнопки) - назва з імені файлу
        preg_match_all('/(?:href|src|data)=["\']([^"\']+\.pdf[^"\']*)["\']/i', $html, $b);
        foreach (($b[1] ?? []) as $link) {
            $url = $this->absolute(html_entity_decode($link), $pagePath);
            if (! isset($titles[$url])) {
                $titles[$url] = Str::of(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME))
                    ->replace(['_', '-', '%20'], ' ')->squish()->ucfirst()->limit(240)->value() ?: 'Документ';
            }
        }

        $out = [];
        foreach ($titles as $url => $title) {
            $out[] = ['url' => $url, 'title' => $title];
        }

        return $out;
    }

    /** Завантажити файл у public-диск. */
    private function download(string $url, string $storedPath): bool
    {
        if ($this->exportRoot !== null) {
            // Локальне дзеркало: копія з content-export/files/ (або за links-map.csv).
            $file = $this->exportAssetFile($url);

            if ($file === null) {
                $this->warn('  ! немає у дзеркалі (пропущено): ' . $url);

                return false;
            }

            $bytes = (string) file_get_contents($file);

            if (strlen($bytes) < 200 || ! str_starts_with($bytes, '%PDF')) {
                $this->warn('  ! не PDF (пропущено): ' . $url);

                return false;
            }

            Storage::disk('public')->put($storedPath, $bytes);
            $this->info('  + ' . basename($storedPath));

            return true;
        }

        try {
            $bytes = Http::withoutVerifying()->timeout(90)->get($url)->body();
            // Зберігаємо лише справжні PDF (а не HTML-сторінки помилок).
            if (strlen($bytes) < 200 || ! str_starts_with($bytes, '%PDF')) {
                $this->warn('  ! не PDF (пропущено): ' . $url);

                return false;
            }
            Storage::disk('public')->put($storedPath, $bytes);
            $this->info('  + ' . basename($storedPath));

            return true;
        } catch (\Throwable $e) {
            $this->error("  ! помилка {$url}: " . $e->getMessage());

            return false;
        }
    }

    private function absolute(string $link, string $pagePath): string
    {
        // Старий сайт інколи використовує зворотні слеші у шляхах (.\files\x.pdf).
        $link = str_replace('\\', '/', $link);

        if (Str::startsWith($link, 'http')) {
            return $link;
        }
        if (Str::startsWith($link, '//')) {
            return 'https:' . $link;
        }
        if (Str::startsWith($link, '/')) {
            return $this->base . $link;
        }

        return $this->base . rtrim($pagePath, '/') . '/' . ltrim($link, './');
    }
}
