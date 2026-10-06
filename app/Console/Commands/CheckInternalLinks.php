<?php

namespace App\Console\Commands;

use App\Support\LinkChecker;
use Illuminate\Console\Command;

/**
 * Звіт про биті внутрішні посилання у контенті. Той самий рушій використовує
 * сторінка адмінки «Биті посилання» (App\Filament\Pages\BrokenLinks).
 */
class CheckInternalLinks extends Command
{
    protected $signature = 'otfk:check-links';

    protected $description = 'Перевірити внутрішні посилання сторінок і новин на биті цілі';

    public function handle(LinkChecker $checker): int
    {
        $broken = $checker->scan();

        if ($broken === []) {
            $this->info('Битих внутрішніх посилань не знайдено.');

            return self::SUCCESS;
        }

        $this->table(
            ['Джерело', 'Матеріал', 'Посилання', 'Проблема'],
            array_map(fn (array $row) => [
                $row['source'],
                mb_strimwidth($row['title'], 0, 40, '…'),
                $row['url'],
                $row['reason'],
            ], $broken),
        );

        $this->warn('Знайдено битих посилань: ' . count($broken) . '.');

        return self::FAILURE;
    }
}
