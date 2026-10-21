<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * otfk:orphan-files: файл без жодного посилання в БД — «сирота»; посилання з HTML редактора
 * (URL-кодовані, з сутностями, абсолютні з хостом), JSON полів файлів і налаштувань його
 * рятують; файли старого сайту типово не чіпаються; без --apply нічого не змінюється,
 * --apply переносить у кошик поза вебом, --restore повертає (навіть без маніфесту).
 * Кошик тестів — тимчасовий каталог, щоб не зачепити справжній storage/app/private.
 */
class OrphanFilesTest extends TestCase
{
    use RefreshDatabase;

    private string $trash;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->trash = sys_get_temp_dir().'/otfk-orphan-test-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->trash);
        parent::tearDown();
    }

    private function putFile(string $path, int $ageDays = 3): void
    {
        $disk = Storage::disk('public');
        $disk->put($path, 'x');
        touch($disk->path($path), now()->subDays($ageDays)->getTimestamp());
    }

    private function seedFiles(): void
    {
        foreach ([
            'documents/vbudovani/used.pdf',
            'documents/vbudovani/Положення 1.pdf',
            'documents/vbudovani/a&b.pdf',
            'documents/vbudovani/abs.pdf',
            'documents/vbudovani/removed.pdf',
            'gallery/a.jpg',
            'gallery/a.webp',
            'gallery/Upper.JPG',
            'gallery/Upper.webp',
            'gallery/orphan.jpg',
            'gallery/orphan.webp',
            'settings/logo.png',
            'mirror/otfk.od.ua/old.pdf',
            'news/imported/old.jpg',
            'documents/zakonodavstvo/0123456789abcdef0123456789abcdef.pdf',
        ] as $path) {
            $this->putFile($path);
        }
        $this->putFile('documents/vbudovani/just-uploaded.pdf', 0);

        Page::create([
            'title' => 'Документи',
            'slug' => 'dokumenty-test',
            'body' => '<p>Текст</p><iframe src="/storage/documents/vbudovani/used.pdf"></iframe>'
                .'<p><a href="/storage/documents/vbudovani/'.rawurlencode('Положення 1.pdf').'">Положення</a></p>'
                .'<p><a href="/storage/documents/vbudovani/a&amp;b.pdf">A&amp;B</a></p>'
                .'<p><img src="https://otfk.od.ua/storage/gallery/Upper.JPG" alt=""></p>',
        ]);
        DB::table('settings')->insert(['key' => 'test_files', 'type' => 'text', 'value' => json_encode(['gallery/a.jpg', 'settings/logo.png'])]);
        DB::table('settings')->insert(['key' => 'test_link', 'type' => 'url', 'value' => 'https://otfk.od.ua/storage/documents/vbudovani/abs.pdf?v=2']);
    }

    public function test_report_lists_only_unreferenced_files_and_changes_nothing(): void
    {
        $this->seedFiles();

        $this->artisan('otfk:orphan-files', ['--trash-dir' => $this->trash])
            ->expectsOutputToContain('documents/vbudovani/removed.pdf')
            ->expectsOutputToContain('gallery/orphan.jpg')
            ->expectsOutputToContain('gallery/orphan.webp')
            ->doesntExpectOutputToContain('used.pdf')
            ->doesntExpectOutputToContain('Положення 1.pdf')
            ->doesntExpectOutputToContain('a&b.pdf')
            ->doesntExpectOutputToContain('abs.pdf')
            ->doesntExpectOutputToContain('gallery/a.')
            ->doesntExpectOutputToContain('Upper')
            ->doesntExpectOutputToContain('logo.png')
            ->doesntExpectOutputToContain('just-uploaded.pdf')
            ->doesntExpectOutputToContain('old.pdf')
            ->doesntExpectOutputToContain('old.jpg')
            ->doesntExpectOutputToContain('0123456789abcdef')
            ->expectsOutputToContain('без посилань: 3')
            ->assertSuccessful();

        Storage::disk('public')->assertExists(['documents/vbudovani/removed.pdf', 'gallery/orphan.jpg']);
        $this->assertDirectoryDoesNotExist($this->trash);
    }

    public function test_legacy_files_are_checked_only_on_request(): void
    {
        $this->seedFiles();

        $this->artisan('otfk:orphan-files', ['--include-legacy' => true])
            ->expectsOutputToContain('mirror/otfk.od.ua/old.pdf')
            ->expectsOutputToContain('news/imported/old.jpg')
            ->expectsOutputToContain('0123456789abcdef0123456789abcdef.pdf')
            ->expectsOutputToContain('без посилань: 6')
            ->assertSuccessful();
    }

    public function test_path_option_limits_the_scan(): void
    {
        $this->seedFiles();

        $this->artisan('otfk:orphan-files', ['--path' => 'documents/vbudovani'])
            ->expectsOutputToContain('removed.pdf')
            ->doesntExpectOutputToContain('gallery/orphan.jpg')
            ->assertSuccessful();

        $this->artisan('otfk:orphan-files', ['--path' => 'nema-takoho'])->assertFailed();
    }

    public function test_find_lists_where_a_file_is_still_referenced(): void
    {
        $this->seedFiles();

        $this->artisan('otfk:orphan-files', ['--find' => 'gallery/Upper.JPG'])
            ->expectsOutputToContain('pages.body: 1')
            ->assertSuccessful();

        $this->artisan('otfk:orphan-files', ['--find' => 'documents/vbudovani/removed.pdf'])
            ->expectsOutputToContain('немає')
            ->assertSuccessful();
    }

    public function test_apply_moves_orphans_to_trash_and_restore_brings_them_back(): void
    {
        $this->seedFiles();
        $disk = Storage::disk('public');

        $this->artisan('otfk:orphan-files', ['--apply' => true, '--trash-dir' => $this->trash])->assertSuccessful();

        $disk->assertMissing(['documents/vbudovani/removed.pdf', 'gallery/orphan.jpg', 'gallery/orphan.webp']);
        $disk->assertExists(['documents/vbudovani/used.pdf', 'documents/vbudovani/Положення 1.pdf', 'gallery/a.webp', 'gallery/Upper.webp', 'documents/vbudovani/just-uploaded.pdf', 'mirror/otfk.od.ua/old.pdf']);

        $batches = File::directories($this->trash);
        $this->assertCount(1, $batches);
        $this->assertFileExists($batches[0].'/files/documents/vbudovani/removed.pdf');
        $manifest = json_decode(File::get($batches[0].'/manifest.json'), true);
        $this->assertEqualsCanonicalizing(['documents/vbudovani/removed.pdf', 'gallery/orphan.jpg', 'gallery/orphan.webp'], $manifest['files']);

        // Конфлікт: файл з тим самим іменем уже повернули на диск — не перезаписується.
        $disk->put('gallery/orphan.jpg', 'new');
        $this->artisan('otfk:orphan-files', ['--restore' => basename($batches[0]), '--trash-dir' => $this->trash])->assertFailed();
        $this->assertSame('new', $disk->get('gallery/orphan.jpg'));
        $disk->assertExists(['documents/vbudovani/removed.pdf', 'gallery/orphan.webp']);

        $this->artisan('otfk:orphan-files', ['--restore' => '../x', '--trash-dir' => $this->trash])->assertFailed();
    }

    public function test_restore_works_without_manifest_after_an_interrupted_run(): void
    {
        $batch = $this->trash.'/20261021-120000-abcdef';
        File::ensureDirectoryExists($batch.'/files/documents/vbudovani');
        File::put($batch.'/files/documents/vbudovani/x.pdf', 'x');

        $this->artisan('otfk:orphan-files', ['--restore' => '20261021-120000-abcdef', '--trash-dir' => $this->trash])->assertSuccessful();

        Storage::disk('public')->assertExists('documents/vbudovani/x.pdf');
    }
}
