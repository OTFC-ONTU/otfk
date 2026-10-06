<?php

namespace App\Console\Commands;

use App\Support\ImageOptimizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ConvertImagesToWebp extends Command
{
    protected $signature = 'images:webp {--dry-run : Лише показати файли без конвертації}';

    protected $description = 'Конвертувати наявні зображення в storage у WebP (поруч з оригіналом)';

    /** @var list<string> */
    private array $directories = [
        'banners',
        'news',
        'gallery',
        'pages',
        'specialties',
        'staff',
        'settings',
    ];

    public function handle(): int
    {
        if (! ImageOptimizer::canOptimize()) {
            $this->error('GD з підтримкою WebP недоступний на цьому сервері.');

            return self::FAILURE;
        }

        $disk = Storage::disk('public');
        $converted = 0;
        $skipped = 0;

        foreach ($this->directories as $dir) {
            if (! $disk->exists($dir)) {
                continue;
            }

            foreach ($disk->allFiles($dir) as $path) {
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

                if (in_array($ext, ['gif', 'svg', 'webp'], true)) {
                    $skipped++;

                    continue;
                }

                if (! in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                    continue;
                }

                if ($this->option('dry-run')) {
                    $this->line($path);
                    $converted++;

                    continue;
                }

                if (ImageOptimizer::toWebp($path)) {
                    $converted++;
                    $this->line("✓ {$path}");
                } else {
                    $skipped++;
                }
            }
        }

        $this->info("Готово: {$converted} конвертовано, {$skipped} пропущено.");

        return self::SUCCESS;
    }
}