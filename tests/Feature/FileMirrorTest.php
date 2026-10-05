<?php

namespace Tests\Feature;

use App\Console\Commands\StorageExport;
use App\Models\FileMirror;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileMirrorTest extends TestCase
{
    use RefreshDatabase;

    private const PDF = "%PDF-1.4\n% тестовий документ\n";

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_queued_file_is_downloaded_with_checksum_and_relative_url(): void
    {
        Http::fake(['https://otfk.od.ua/*' => fn () => Http::response(self::PDF, 200, ['Content-Type' => 'application/pdf'])]);
        $row = FileMirror::enqueue('https://otfk.od.ua/student/dorm/files/Положення 1.pdf');
        FileMirror::enqueue('https://otfk.od.ua/student/dorm/files/Положення 1.pdf');

        $this->artisan('otfk:mirror-files')->assertSuccessful();

        $row->refresh();
        $this->assertSame(1, FileMirror::count());
        $this->assertSame(FileMirror::DONE, $row->status);
        $this->assertSame('mirror/otfk.od.ua/student/dorm/files/Положення 1.pdf', $row->path);
        $this->assertSame(hash('sha256', self::PDF), $row->sha256);
        Storage::disk('public')->assertExists($row->path);
        $this->assertSame('/storage/mirror/otfk.od.ua/student/dorm/files/'.rawurlencode('Положення 1.pdf'), $row->publicUrl());
        Http::assertSent(fn ($request) => $request->url() === 'https://otfk.od.ua/student/dorm/files/'.rawurlencode('Положення 1.pdf'));
    }

    public function test_foreign_hosts_unsafe_paths_and_html_instead_of_file_are_rejected(): void
    {
        Http::fake([
            'https://otfk.od.ua/missing.pdf' => fn () => Http::response('<!DOCTYPE html><html>404</html>', 200),
            '*' => fn () => Http::response(self::PDF, 200),
        ]);
        $rows = [
            FileMirror::enqueue('https://evil.example/a.pdf'),
            FileMirror::enqueue('https://otfk.od.ua/a/%2e%2e/b.pdf'),
            FileMirror::enqueue('https://otfk.od.ua/page.html'),
            FileMirror::enqueue('https://otfk.od.ua/missing.pdf'),
        ];

        foreach (range(1, 3) as $run) {
            $this->artisan('otfk:mirror-files')->assertSuccessful();
        }

        foreach ($rows as $row) {
            $this->assertSame(FileMirror::FAILED, $row->refresh()->status, $row->source_url);
            $this->assertNull($row->publicUrl());
        }
        $this->assertSame([], Storage::disk('public')->allFiles());
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'evil.example'));

        $this->artisan('otfk:mirror-files --retry-failed')->assertSuccessful();
        $this->assertSame(4, FileMirror::where('status', FileMirror::PENDING)->count());
    }

    public function test_verify_restores_missing_files_from_old_hosting_with_checksum(): void
    {
        Http::fake(['https://otfk.od.ua/*' => fn () => Http::response(self::PDF, 200)]);
        $row = FileMirror::enqueue('https://otfk.od.ua/files/plan.pdf');
        $this->artisan('otfk:mirror-files')->assertSuccessful();
        Storage::disk('public')->delete($row->refresh()->path);

        // Новий хостинг: оригінал уже недоступний, файл береться зі старого хостингу за відомим шляхом.
        Http::fake([
            'https://old.example/storage/*' => fn () => Http::response(self::PDF, 200),
            'https://otfk.od.ua/*' => fn () => Http::response('', 404),
        ]);
        $this->artisan('otfk:mirror-files --verify --from=https://old.example')->assertSuccessful();

        $this->assertSame(FileMirror::DONE, $row->refresh()->status);
        Storage::disk('public')->assertExists($row->path);
        Http::assertSent(fn ($request) => $request->url() === 'https://old.example/storage/mirror/otfk.od.ua/files/plan.pdf');
    }

    public function test_old_hosting_copy_with_other_checksum_is_not_accepted(): void
    {
        Http::fake(['https://otfk.od.ua/*' => fn () => Http::response(self::PDF, 200)]);
        $row = FileMirror::enqueue('https://otfk.od.ua/files/plan.pdf');
        $this->artisan('otfk:mirror-files')->assertSuccessful();
        Storage::disk('public')->delete($row->refresh()->path);

        Http::fake(['old.example/*' => Http::response("%PDF-1.4\nінший файл", 200)]);
        $this->artisan('otfk:mirror-files --verify --from=https://old.example')->assertSuccessful();

        $this->assertSame(FileMirror::PENDING, $row->refresh()->status);
        $this->assertStringContainsString('sha256', $row->error);
        Storage::disk('public')->assertMissing($row->path);
    }

    public function test_storage_archive_round_trip_restores_files_and_keeps_existing(): void
    {
        $disk = Storage::disk('public');
        $disk->put('news/imported/1.jpg', "\xFF\xD8фото");
        $disk->put('mirror/otfk.od.ua/a b/док.pdf', self::PDF);

        $this->artisan('otfk:storage-export')->assertSuccessful();
        $archive = collect(glob(storage_path('app/backups/storage_*.zip')))->sortDesc()->first();
        $this->assertNotNull($archive);

        $zip = new \ZipArchive;
        $zip->open($archive);
        $manifest = json_decode($zip->getFromName(StorageExport::MANIFEST), true);
        $zip->close();
        $this->assertCount(2, $manifest['files']);

        $disk->delete('news/imported/1.jpg');
        $disk->put('mirror/otfk.od.ua/a b/док.pdf', 'змінено на новому хостингу');

        $this->artisan('otfk:storage-import', ['archive' => $archive])->assertSuccessful();
        $this->assertSame("\xFF\xD8фото", $disk->get('news/imported/1.jpg'));
        $this->assertSame('змінено на новому хостингу', $disk->get('mirror/otfk.od.ua/a b/док.pdf'));

        $this->artisan('otfk:storage-import', ['archive' => $archive, '--overwrite' => true])->assertSuccessful();
        $this->assertSame(self::PDF, $disk->get('mirror/otfk.od.ua/a b/док.pdf'));
        @unlink($archive);
    }

    public function test_archive_with_path_traversal_is_rejected(): void
    {
        $archive = storage_path('app/evil-test.zip');
        $zip = new \ZipArchive;
        $zip->open($archive, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('../escape.txt', 'x');
        $zip->addFromString(StorageExport::MANIFEST, json_encode(['files' => [['path' => '../escape.txt', 'size' => 1, 'sha256' => hash('sha256', 'x')]]]));
        $zip->close();

        $this->artisan('otfk:storage-import', ['archive' => $archive])->assertFailed();
        $this->assertFileDoesNotExist(storage_path('app/escape.txt'));
        @unlink($archive);
    }

    public function test_mirror_command_is_scheduled(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('otfk:mirror-files')->assertSuccessful();
    }
}
