<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Щотижнева резервна копія бази (щонеділі о 03:30). Зберігаємо 14 останніх копій.
// Потрібно лише якщо на хостингу cron викликає «php artisan schedule:run» щохвилини.
// Якщо ж налаштовано прямий cron на «artisan otfk:backup» — періодичність задає сам cron.
Schedule::command('otfk:backup')
    ->weeklyOn(0, '03:30')
    ->withoutOverlapping();

// Черга дзеркалювання файлів старого сайту (file_mirrors): щохвилини невеликими порціями.
// Без записів у черзі команда лише перевіряє її й завершується.
Schedule::command('otfk:mirror-files --limit=30')
    ->everyMinute()
    ->withoutOverlapping(30);

// Автопереклад на англійську (Claude API): страховка для черги translation_jobs. Основний шлях —
// afterResponse після збереження; планувальник добирає те, що той не встиг (обрив за max_execution_time,
// повтор після 429/5xx, масове «Перекласти все неперекладене»). Без автоперекладу команда одразу завершується.
Schedule::command('otfk:auto-translate')
    ->everyMinute()
    ->withoutOverlapping(30);

// Чистимо статистику відвідувань, старішу за пів року (агрегати займають місце дарма)
Schedule::call(fn () => \App\Models\SiteVisit::where('date', '<', now()->subDays(180)->toDateString())->delete())
    ->weeklyOn(0, '04:00')
    ->name('prune-site-visits');

// Журнал 404 (not_found_logs): адреси без звернень понад 90 днів видаляються.
Schedule::command('model:prune', ['--model' => [\App\Models\NotFoundLog::class]])
    ->weeklyOn(0, '04:15');
