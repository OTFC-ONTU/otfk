<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Program;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class LinkOppPrograms extends Command
{
    protected $signature = 'otfk:link-opp-programs {--force : Перепривʼязати навіть якщо file_path уже заповнено}';

    protected $description = 'Підвʼязати PDF з категорії документів «osvitno-profesiyni-prohramy» до записів Program (ОПП у картках спеціальностей)';

    /**
     * Та сама логіка, що в міграції import_specialties_content, але окремою
     * командою: на сервері документи ОПП імпортуються ПІСЛЯ міграцій
     * (otfk:import-docs потребує дзеркала), тож привʼязку треба вміти повторити.
     */
    public function handle(): int
    {
        $category = DocumentCategory::where('slug', 'osvitno-profesiyni-prohramy')->first();

        if (! $category || $category->documents()->count() === 0) {
            $this->error('Категорія «osvitno-profesiyni-prohramy» порожня або відсутня — спершу запустіть otfk:import-docs.');

            return self::FAILURE;
        }

        $documents = $category->documents()->get();
        $linked = 0;
        $skipped = 0;

        foreach (Program::with('specialty')->get() as $program) {
            if (! $this->option('force') && (filled($program->file_path) || filled($program->external_url))) {
                $skipped++;

                continue;
            }

            $doc = $this->match($documents, $program->title, (string) $program->specialty?->code);

            if ($doc) {
                $program->update(['file_path' => $doc->file_path]);
                $this->info('  + ' . $program->title . ' ← ' . Str::limit($doc->title, 80));
                $linked++;
            } else {
                $this->warn('  ! не знайдено PDF: ' . $program->title);
            }
        }

        $this->newLine();
        $this->info("Підв'язано: {$linked}, пропущено (вже мали файл): {$skipped}.");

        return self::SUCCESS;
    }

    /** Пошук PDF програми (перевага — файлам з новим літерним кодом, тобто 2025 року). */
    private function match($documents, string $programTitle, string $code): ?Document
    {
        // Нормалізація: назви різняться апострофами й дефісами
        // («холодильнокомпресорних» у файлі 2025 року — без дефіса).
        $normalize = fn (string $s) => mb_strtolower(str_replace(['’', '\'', '`', '-', '‑'], '', $s));
        $needle = $normalize($programTitle);

        $candidates = $documents->filter(fn (Document $d) => filled($d->file_path)
            && Str::startsWith($d->title, 'ОПП')
            && Str::contains($normalize($d->title), $needle));

        return $candidates->first(fn (Document $d) => $code !== '' && Str::contains($d->title, ': ' . $code . ' '))
            ?? $candidates->first();
    }
}
