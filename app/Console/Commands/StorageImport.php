<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class StorageImport extends Command
{
    protected $signature = 'otfk:storage-import
        {archive : Шлях до архіву, створеного otfk:storage-export}
        {--overwrite : Замінювати наявні файли з іншим вмістом (за замовчуванням — лише звіт)}';

    protected $description = 'Відновлення файлів публічного диска з архіву otfk:storage-export з перевіркою sha256 (нічого не видаляє)';

    public function handle(): int
    {
        $archive = (string) $this->argument('archive');
        $zip = new \ZipArchive;
        if (! is_file($archive) || $zip->open($archive) !== true) {
            $this->error("Не вдалося відкрити архів {$archive}");

            return self::FAILURE;
        }

        $manifest = json_decode((string) $zip->getFromName(StorageExport::MANIFEST), true);
        if (! is_array($manifest['files'] ?? null)) {
            $this->error('В архіві немає маніфесту '.StorageExport::MANIFEST);

            return self::FAILURE;
        }

        $disk = Storage::disk('public');
        $stats = ['written' => 0, 'same' => 0, 'conflict' => 0, 'bad' => 0];

        foreach ($manifest['files'] as $file) {
            $path = (string) ($file['path'] ?? '');
            if (! $this->safe($path)) {
                $this->warn("  небезпечний шлях пропущено: {$path}");
                $stats['bad']++;

                continue;
            }

            if ($disk->exists($path)) {
                if (hash_file('sha256', $disk->path($path)) === $file['sha256']) {
                    $stats['same']++;

                    continue;
                }
                if (! $this->option('overwrite')) {
                    $this->warn("  інший вміст, не замінено: {$path}");
                    $stats['conflict']++;

                    continue;
                }
            }

            $stream = $zip->getStream($path);
            if ($stream === false) {
                $this->warn("  немає в архіві: {$path}");
                $stats['bad']++;

                continue;
            }
            $disk->writeStream($path, $stream);
            fclose($stream);

            if (hash_file('sha256', $disk->path($path)) !== $file['sha256']) {
                $this->warn("  sha256 не збігся: {$path}");
                $stats['bad']++;

                continue;
            }
            $stats['written']++;
        }
        $zip->close();

        $this->info("Записано: {$stats['written']}, вже актуальні: {$stats['same']}, конфлікти: {$stats['conflict']}, помилки: {$stats['bad']}");

        return $stats['bad'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** Лише відносні шляхи без виходу за межі диска. */
    private function safe(string $path): bool
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\') || preg_match('/^[a-z]:/i', $path)) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }
}
