<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ReadsOtfkExport;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Імпорт реальних контактів старого сайту (content-export/contacts.md дзеркала)
 * у таблицю settings: адреса, телефон, e-mail, карта. Ключі — ті самі, що читає
 * Setting::map() і сидить SiteSeeder (contact_address, contact_phone,
 * contact_email, feedback_email, map_embed).
 *
 * Ідемпотентно (updateOrCreate за key) + Cache::forget('settings.map').
 */
class ImportOtfkContacts extends Command
{
    use ReadsOtfkExport;

    protected $signature = 'otfk:import-contacts
        {--from-export= : Шлях до локального дзеркала site-audit/… (обов\'язково)}
        {--dry-run : Показати знайдене, нічого не зберігати}';

    protected $description = 'Імпорт реальних контактів (адреса/телефони/email/карта) старого сайту у settings';

    public function handle(): int
    {
        if (blank($this->option('from-export')) || ! $this->initExport((string) $this->option('from-export'))) {
            $this->error('Потрібна опція --from-export=<шлях до site-audit/YYYY-MM-DD>.');

            return self::FAILURE;
        }

        $md = $this->parseExportMarkdown($this->exportPath('content-export/contacts.md'));

        if ($md === null) {
            $this->error('У дзеркалі немає content-export/contacts.md.');

            return self::FAILURE;
        }

        $body = $md['body'];

        // Адреса: рядок після «Поштова адреса …» виду «м.Одеса, вул. Балківська, 54, 65006,».
        $address = null;
        if (preg_match('/^м\.\s*[^\n,]+,\s*вул\.[^\n]+$/mu', $body, $a)) {
            $address = Str::squish(trim($a[0], " \t,;"));
        }

        // Телефони: пункти списку «- Посада (ПІБ): (048) 753-16-51».
        $phones = [];
        if (preg_match_all('/^-\s*(.+?):\s*([+\d ()\-]{7,})\s*$/mu', $body, $p, PREG_SET_ORDER)) {
            foreach ($p as $row) {
                $phones[] = ['label' => Str::squish($row[1]), 'phone' => Str::squish($row[2])];
            }
        }

        // E-mail та карта.
        $email = preg_match('/Електронна пошта:\s*\**([^\s*]+@[^\s*]+)\**/u', $body, $e) ? trim($e[1], '.,') : null;
        $map = preg_match('#\bhttps://www\.google\.com/maps/embed\?[^\s\)\]]+#u', $body, $m) ? $m[0] : null;

        $this->table(['Що', 'Значення'], array_filter([
            ['Адреса', $address ?? '—'],
            ['Телефон (основний)', $phones[0]['phone'] ?? '—'],
            ['Усього телефонів', (string) count($phones)],
            ['E-mail', $email ?? '—'],
            ['Карта (embed)', $map ? Str::limit($map, 60) : '—'],
        ]));

        if ($this->option('dry-run')) {
            $this->info('[dry] Нічого не збережено.');

            return self::SUCCESS;
        }

        $values = array_filter([
            'contact_address' => $address,
            'contact_phone' => $phones[0]['phone'] ?? null,
            'contact_email' => $email,
            'feedback_email' => $email,
            'map_embed' => $map,
        ]);

        if ($values === []) {
            $this->error('Не вдалося розпізнати жодного контакту — settings не змінено.');

            return self::FAILURE;
        }

        $groups = ['contact_address' => 'text', 'contact_phone' => 'text', 'contact_email' => 'text', 'feedback_email' => 'text', 'map_embed' => 'url'];

        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => 'contacts', 'type' => $groups[$key]]);
        }

        Cache::forget('settings.map');

        $this->info('Оновлено налаштувань: ' . count($values) . '. Кеш settings.map скинуто.');

        if (count($phones) > 1) {
            $this->comment('Додаткові телефони (у settings один основний — решту внесіть в адмінці за потреби):');
            foreach (array_slice($phones, 1) as $row) {
                $this->line("  {$row['label']}: {$row['phone']}");
            }
        }

        return self::SUCCESS;
    }
}
