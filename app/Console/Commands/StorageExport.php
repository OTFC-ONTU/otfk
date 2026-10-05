<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class StorageExport extends Command
{
    protected $signature = 'otfk:storage-export
        {--keep=3 : Скільки останніх архівів зберігати}';

    protected $description = 'Архів усіх файлів публічного диска (storage/app/public) з маніфестом sha256 — для переїзду на інший хостинг';

    public const MANIFEST = '.otfk-storage-manifest.json';

    /** Вже стиснені формати додаються без повторного стиснення. */
    private const STORED = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'zip', 'rar', '7z', 'mp3', 'mp4', 'webm', 'docx', 'xlsx', 'pptx'];

    public function handle(): int
    {
        if (! class_exists(\ZipArchive::class)) {
            $this->error('Потрібне PHP-розширення zip.');

            return self::FAILURE;
        }

        $disk = Storage::disk('public');
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        $file = $dir.'/storage_'.now()->format('Y-m-d_His').'.zip';

        $zip = new \ZipArchive;
        if ($zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            $this->error("Не вдалося створити {$file}");

            return self::FAILURE;
        }

        $manifest = [];
        $bytes = 0;
        foreach ($disk->allFiles() as $path) {
            if (str_starts_with(basename($path), '.')) {
                continue; // .gitignore та службові файли
            }
            $full = $disk->path($path);
            $manifest[] = ['path' => $path, 'size' => filesize($full), 'sha256' => hash_file('sha256', $full)];
            $bytes += filesize($full);
            $zip->addFile($full, $path);
            if (in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::STORED, true)) {
                $zip->setCompressionName($path, \ZipArchive::CM_STORE);
            }
        }

        $zip->addFromString(self::MANIFEST, json_encode([
            'created_at' => now()->toIso8601String(),
            'app_url' => config('app.url'),
            'files' => $manifest,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        if (! $zip->close()) {
            @unlink($file);
            $this->error('Помилка запису архіву.');

            return self::FAILURE;
        }

        $this->info(sprintf('✓ %s: %d файлів, %.1f МБ даних', basename($file), count($manifest), $bytes / 1048576));
        $this->rotate($dir, (int) $this->option('keep'));

        return self::SUCCESS;
    }

    private function rotate(string $dir, int $keep): void
    {
        if ($keep <= 0) {
            return;
        }

        $files = collect(File::glob($dir.'/storage_*.zip'))->sortByDesc(fn ($f) => File::lastModified($f))->values();
        foreach ($files->slice($keep) as $old) {
            File::delete($old);
            $this->line('  видалено старий архів: '.basename($old));
        }
    }
}
