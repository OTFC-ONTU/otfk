<?php

namespace Tests\Feature;

use App\Models\DocumentCategory;
use App\Models\FileMirror;
use App\Models\LegacyRedirect;
use App\Models\News;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Збирання карти старих адрес otfk:legacy-map (docs/seo-plan.md, етап 1):
 * маркери імпорту → публічні адреси, зеркала → /storage/mirror, фото новин
 * лише з доведеним старим шляхом, звіти unmapped/conflicts і сумісність CSV
 * з otfk:legacy-redirects.
 */
class LegacyMapTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->dir = sys_get_temp_dir().'/otfk-legacy-map-'.uniqid();
        File::ensureDirectoryExists($this->dir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function marker(string $url): string
    {
        return "<p>Текст</p>\n<!--imported-from:{$url}-->";
    }

    private function build(array $options = []): array
    {
        $out = $this->dir.'/map.csv';
        $this->artisan('otfk:legacy-map', ['--out' => $out] + $options)->assertExitCode(0);

        return [
            'map' => $this->readCsv($out),
            'unmapped' => $this->readCsv($out.'.unmapped.csv'),
            'conflicts' => $this->readCsv($out.'.conflicts.csv'),
            'file' => $out,
        ];
    }

    /** @return list<array<string, string>> */
    private function readCsv(string $file): array
    {
        $rows = array_map(fn ($line) => str_getcsv($line, ',', '"', ''), array_filter(explode("\n", (string) file_get_contents($file))));
        $header = array_shift($rows);

        return array_map(fn ($row) => array_combine($header, $row), $rows);
    }

    private function targetOf(array $map, string $source): ?string
    {
        foreach ($map as $row) {
            if ($row['source'] === $source) {
                return $row['target'];
            }
        }

        return null;
    }

    public function test_markers_map_to_public_page_and_news_urls_and_unchanged_paths_are_skipped(): void
    {
        Page::create(['title' => 'Історія', 'slug' => 'istoriya-koledzhu', 'body' => $this->marker('https://otfk.od.ua/about/history/'), 'is_published' => true]);
        Page::create(['title' => 'Та сама адреса', 'slug' => 'bibliotek', 'body' => $this->marker('https://otfk.od.ua/bibliotek'), 'is_published' => true]);
        News::create(['title' => 'Новина', 'slug' => 'stara-novyna', 'body' => $this->marker('http://www.otfk.od.ua/news/Свято.html'), 'is_published' => true, 'published_at' => now()->subDay()]);

        $result = $this->build();

        $this->assertSame('/istoriya-koledzhu', $this->targetOf($result['map'], '/about/history'));
        $this->assertSame('/novyny/stara-novyna', $this->targetOf($result['map'], '/news/'.rawurlencode('Свято.html')));
        $this->assertNull($this->targetOf($result['map'], '/bibliotek'), 'збережена адреса не потребує редиректу');
        $this->assertSame('301', $result['map'][0]['code']);
    }

    public function test_unpublished_and_future_records_are_reported_not_mapped(): void
    {
        Page::create(['title' => 'Чернетка', 'slug' => 'chernetka', 'body' => $this->marker('https://otfk.od.ua/draft.html'), 'is_published' => false]);
        News::create(['title' => 'Майбутня', 'slug' => 'maibutnia', 'body' => $this->marker('https://otfk.od.ua/news/future'), 'is_published' => true, 'published_at' => now()->addWeek()]);

        $result = $this->build();

        $this->assertNull($this->targetOf($result['map'], '/draft.html'));
        $this->assertNull($this->targetOf($result['map'], '/news/future'));
        $reasons = collect($result['unmapped'])->pluck('reason', 'source');
        $this->assertSame('unpublished', $reasons['/draft.html']);
        $this->assertSame('unpublished', $reasons['/news/future']);
    }

    public function test_mirrors_map_to_storage_mirror_and_verify_checks_sha256(): void
    {
        $good = 'mirror/otfk.od.ua/uploads/docs/Наказ 1.pdf';
        Storage::disk('public')->put($good, '%PDF-1.4 good');
        FileMirror::create(['source_url' => 'https://otfk.od.ua/uploads/docs/Наказ 1.pdf', 'source_hash' => hash('sha256', 'a'), 'path' => $good,
            'status' => FileMirror::DONE, 'sha256' => hash('sha256', '%PDF-1.4 good')]);

        $bad = 'mirror/otfk.od.ua/files/changed.pdf';
        Storage::disk('public')->put($bad, '%PDF-1.4 changed');
        FileMirror::create(['source_url' => 'https://otfk.od.ua/files/changed.pdf', 'source_hash' => hash('sha256', 'b'), 'path' => $bad,
            'status' => FileMirror::DONE, 'sha256' => hash('sha256', 'original')]);

        FileMirror::create(['source_url' => 'https://otfk.od.ua/files/pending.pdf', 'source_hash' => hash('sha256', 'c'), 'status' => FileMirror::PENDING]);

        $result = $this->build(['--verify' => true]);

        $source = '/uploads/docs/'.rawurlencode('Наказ 1.pdf');
        $this->assertSame('/storage/mirror/otfk.od.ua/uploads/docs/'.rawurlencode('Наказ 1.pdf'), $this->targetOf($result['map'], $source));
        $this->assertNull($this->targetOf($result['map'], '/files/changed.pdf'));
        $this->assertNull($this->targetOf($result['map'], '/files/pending.pdf'));
        $this->assertSame('verify', collect($result['conflicts'])->firstWhere('source', '/files/changed.pdf')['type']);
    }

    public function test_news_photo_needs_known_old_path_and_basename_collision_is_a_conflict(): void
    {
        Storage::disk('public')->put('news/imported/one.jpg', 'jpg');
        Storage::disk('public')->put('news/imported/twin.jpg', 'jpg');
        Storage::disk('public')->put('news/imported/unknown.jpg', 'jpg');

        $list = $this->dir.'/urls.txt';
        File::put($list, implode("\n", [
            'https://otfk.od.ua/uploads/2019/one.jpg',
            '/uploads/2019/twin.jpg',
            '/uploads/2020/twin.jpg',
        ]));

        $result = $this->build(['--urls' => [$list]]);

        $this->assertSame('/storage/news/imported/one.jpg', $this->targetOf($result['map'], '/uploads/2019/one.jpg'));
        $this->assertNull($this->targetOf($result['map'], '/uploads/2019/twin.jpg'));
        $this->assertNull($this->targetOf($result['map'], '/uploads/2020/twin.jpg'));
        $collisions = collect($result['conflicts'])->where('type', 'basename-collision')->pluck('source')->sort()->values()->all();
        $this->assertSame(['/uploads/2019/twin.jpg', '/uploads/2020/twin.jpg'], $collisions);

        // Без доказів старого шляху — лише звіт; --guess-photos явно додає /uploads/<ім'я>.
        $this->assertSame('photo-unconfirmed', collect($result['unmapped'])->firstWhere('source', '/uploads/unknown.jpg')['reason']);
        $guessed = $this->build(['--urls' => [$list], '--guess-photos' => true]);
        $this->assertSame('/storage/news/imported/unknown.jpg', $this->targetOf($guessed['map'], '/uploads/unknown.jpg'));
    }

    public function test_sitemap_urls_without_mapping_are_reported_as_unmapped(): void
    {
        Page::create(['title' => 'Про нас', 'slug' => 'pro-nas-novyi', 'body' => $this->marker('https://otfk.od.ua/about.html'), 'is_published' => true]);
        Http::fake([
            'https://otfk.od.ua/sitemap.xml' => Http::response('<?xml version="1.0"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><sitemap><loc>https://otfk.od.ua/sitemap-pages.xml</loc></sitemap></sitemapindex>'),
            'https://otfk.od.ua/sitemap-pages.xml' => Http::response('<?xml version="1.0"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
                .'<url><loc>https://otfk.od.ua/about.html</loc></url>'
                .'<url><loc>https://www.otfk.od.ua/old/unknown.html?utm_source=x</loc></url>'
                .'<url><loc>https://example.com/elsewhere</loc></url>'
                .'</urlset>'),
        ]);

        $result = $this->build(['--sitemap' => ['https://otfk.od.ua/sitemap.xml']]);

        $this->assertSame('/pro-nas-novyi', $this->targetOf($result['map'], '/about.html'));
        $unmapped = collect($result['unmapped'])->where('reason', 'unmapped')->pluck('source')->all();
        $this->assertSame(['/old/unknown.html'], $unmapped, 'без автоматичних 410 і редиректу на головну');
    }

    public function test_generated_csv_round_trips_through_legacy_redirects_import(): void
    {
        // Значущий параметр умовної старої CMS (у справжнього старого сайту їх немає).
        config(['otfk.legacy.query_keys' => ['id']]);
        Page::create(['title' => 'Історія', 'slug' => 'istoriya-koledzhu', 'body' => $this->marker('https://otfk.od.ua/about/history.html'), 'is_published' => true]);
        News::create(['title' => 'Новина', 'slug' => 'novyna-1', 'body' => $this->marker('https://otfk.od.ua/news/?id=12'), 'is_published' => true, 'published_at' => now()->subDay()]);
        Storage::disk('public')->put('mirror/otfk.od.ua/uploads/План 2024.pdf', '%PDF');
        FileMirror::create(['source_url' => 'https://otfk.od.ua/uploads/План 2024.pdf', 'source_hash' => hash('sha256', 'p'), 'path' => 'mirror/otfk.od.ua/uploads/План 2024.pdf', 'status' => FileMirror::DONE]);

        $result = $this->build(['--verify' => true]);
        $this->assertCount(3, $result['map']);
        $this->assertSame([], $result['conflicts']);

        $archive = $this->dir.'/archive';
        $this->artisan('otfk:legacy-redirects', ['file' => $result['file'], '--archive-dir' => $archive])
            ->expectsTable(['Нових', 'Змінених', 'Без змін', 'Конфліктів'], [[3, 0, 0, 0]])
            ->assertExitCode(0);
        $this->artisan('otfk:legacy-redirects', ['file' => $result['file'], '--apply' => true, '--archive-dir' => $archive])->assertExitCode(0);

        $this->assertSame(3, LegacyRedirect::count());
        $this->get('/about/history.html')->assertStatus(301)->assertRedirect(url('/istoriya-koledzhu'));
        $this->get('/news/?id=12&utm_source=fb')->assertStatus(301)->assertRedirect(url('/novyny/novyna-1'));
        $this->get('/uploads/'.rawurlencode('План 2024.pdf'))->assertStatus(301);
    }

    public function test_section_page_maps_straight_to_document_category(): void
    {
        $page = Page::create(['title' => 'Кошторис', 'slug' => 'koshtorys', 'body' => $this->marker('https://otfk.od.ua/public_information/budget_of_the_college/'), 'is_published' => true]);
        DocumentCategory::create(['title' => 'Кошторис', 'slug' => 'koshtorys-dok', 'page_id' => $page->id]);

        $result = $this->build(['--verify' => true]);

        // Без проміжного переходу через /koshtorys, який сам переадресовує на розділ.
        $this->assertSame('/dokumenty/koshtorys-dok', $this->targetOf($result['map'], '/public_information/budget_of_the_college'));
    }
}
