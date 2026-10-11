<?php

namespace App\Console\Commands;

use App\Services\AutoTranslation\AutoTranslator;
use Illuminate\Console\Command;

class AutoTranslate extends Command
{
    protected $signature = 'otfk:auto-translate
        {--limit=0 : Скільки записів черги обробити за запуск (0 — значення з config/auto_translate.php)}
        {--missing : Спочатку поставити в чергу всі матеріали без актуального перекладу}
        {--retry-failed : Повернути в чергу записи зі статусом failed}';

    protected $description = 'Автопереклад на англійську через Claude API: обробка черги translation_jobs';

    public function handle(AutoTranslator $translator): int
    {
        if (! AutoTranslator::enabled()) {
            $this->line('Автопереклад вимкнено або не збережено ключ API.');

            return self::SUCCESS;
        }

        if ($this->option('retry-failed')) {
            $this->line('Повернуто в чергу: '.AutoTranslator::retryFailed());
        }
        if ($this->option('missing')) {
            $this->line('Додано в чергу: '.AutoTranslator::enqueueMissing());
        }

        $limit = (int) $this->option('limit') ?: (int) config('auto_translate.batch_limit', 5);
        $result = $translator->processQueue($limit);

        $this->line(sprintf(
            'Перекладено: %d, помилок: %d, пропущено: %d, відкладено: %d%s',
            $result['done'], $result['failed'], $result['skipped'], $result['retried'],
            $result['stopped'] ? ' (зупинено: '.$result['stopped'].')' : '',
        ));

        return self::SUCCESS;
    }
}
