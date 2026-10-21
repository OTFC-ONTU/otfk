<?php

namespace Tests\Feature;

use App\Console\Commands\OrphanFiles;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * otfk:orphan-files: файл без жодного посилання в БД — «сирота»; посилання з HTML редактора
 * (зокрема URL-кодовані), JSON полів файлів і налаштувань його рятують; без --apply нічого
 * не змінюється, --apply переносить у кошик поза вебом, --restore повертає.
 */
class OrphanFilesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        File::deleteDirectory(storage_path(OrphanFiles::TRASH_DIR));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path(OrphanFiles::TRASH_DIR));
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
            'documents/vbudovani/removed.pdf',
            'gallery/a.jpg',
            'gallery/a.webp',
            'gallery/orphan.jpg',
            'gallery/orphan.webp',
            'settings/logo.png',
        ] as $path) {
            $this->putFile($path);
        }
        $this->putFile('documents/vbudovani/just-uploaded.pdf', 0);

        Page::create([
            'title' => 'Документи',
            'slug' => 'dokumenty-test',
            'body' => '<p>Текст</p><iframe src="/storage/documents/vbudovani/used.pdf"></iframe>'
                .'<p><a href="/storage/documents/vbudovani/'.rawurlencode('Положення 1.pdf').'">Положення</a></p>',
        ]);
        DB::table('settings')->insert(['key' => 'test_files', 'type' => 'text', 'value' => json_encode(['gallery/a.jpg', 'settings/logo.png'])]);
    }

    public function test_report_lists_only_unreferenced_files_and_changes_nothing(): void
    {
        $this->seedFiles();

        $this->artisan('otfk:orphan-files')
            ->expectsOutputToContain('documents/vbudovani/removed.pdf')
            ->expectsOutputToContain('gallery/orphan.jpg')
            ->expectsOutputToContain('gallery/orphan.webp')
            ->doesntExpectOutputToContain('used.pdf')
            ->doesntExpectOutputToContain('Положення 1.pdf')
            ->doesntExpectOutputToContain('gallery/a.')
            ->doesntExpectOutputToContain('logo.png')
            ->doesntExpectOutputToContain('just-uploaded.pdf')
            ->expectsOutputToContain('без посилань: 3')
            ->assertSuccessful();

        Storage::disk('public')->assertExists(['documents/vbudovani/removed.pdf', 'gallery/orphan.jpg']);
        $this->assertDirectoryDoesNotExist(storage_path(OrphanFiles::TRASH_DIR));
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

    public function test_apply_moves_orphans_to_trash_and_restore_brings_them_back(): void
    {
        $this->seedFiles();
        $disk = Storage::disk('public');

        $this->artisan('otfk:orphan-files', ['--apply' => true])->assertSuccessful();

        $disk->assertMissing(['documents/vbudovani/removed.pdf', 'gallery/orphan.jpg', 'gallery/orphan.webp']);
        $disk->assertExists(['documents/vbudovani/used.pdf', 'documents/vbudovani/Положення 1.pdf', 'gallery/a.webp', 'documents/vbudovani/just-uploaded.pdf']);

        $batches = File::directories(storage_path(OrphanFiles::TRASH_DIR));
        $this->assertCount(1, $batches);
        $this->assertFileExists($batches[0].'/files/documents/vbudovani/removed.pdf');
        $manifest = json_decode(File::get($batches[0].'/manifest.json'), true);
        $this->assertEqualsCanonicalizing(['documents/vbudovani/removed.pdf', 'gallery/orphan.jpg', 'gallery/orphan.webp'], $manifest['files']);

        $this->artisan('otfk:orphan-files', ['--restore' => basename($batches[0])])->assertSuccessful();
        $disk->assertExists(['documents/vbudovani/removed.pdf', 'gallery/orphan.jpg', 'gallery/orphan.webp']);

        $this->artisan('otfk:orphan-files', ['--restore' => '../x'])->assertFailed();
    }
}
