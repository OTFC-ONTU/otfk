<?php

namespace App\Filament\Pages;

use App\Filament\Support\SettingsFormPage;
use App\Support\Analytics;
use App\Support\StructuredData;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;

/**
 * Дані організації для структурованої розмітки (JSON-LD EducationalOrganization,
 * docs/seo-plan.md, етап 3): колишні назви/абревіатури (alternateName) та
 * офіційні профілі (sameAs). Порожні поля не виводяться. Також ідентифікатор
 * потоку Google Analytics 4 (App\Support\Analytics): порожній — аналітика
 * повністю вимкнена. Лише адміністратор (SettingsFormPage::canAccess), група
 * меню «SEO».
 */
class SeoSettings extends SettingsFormPage
{
    protected static ?string $navigationGroup = 'SEO';

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Розмітка та аналітика';

    protected static ?string $title = 'Розмітка організації та аналітика';

    protected static ?string $slug = 'settings-seo';

    protected static string $settingsGroup = 'seo';

    protected static function keys(): array
    {
        return [
            StructuredData::ALTERNATE_NAMES_KEY => 'textarea',
            StructuredData::SAME_AS_KEY => 'textarea',
            Analytics::MEASUREMENT_ID_KEY => 'text',
        ];
    }

    /** Зберігаємо рядки без пробілів по краях і без порожніх рядків; ID GA4 — великими літерами. */
    protected function toSettings(array $state): array
    {
        foreach ([StructuredData::ALTERNATE_NAMES_KEY, StructuredData::SAME_AS_KEY] as $key) {
            $state[$key] = implode("\n", StructuredData::lines($state[$key] ?? null));
        }
        $state[Analytics::MEASUREMENT_ID_KEY] = strtoupper(trim((string) ($state[Analytics::MEASUREMENT_ID_KEY] ?? '')));

        return $state;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Організація в пошукових системах')
                    ->description('Невидимі відвідувачам дані розмітки Schema.org: допомагають пошуковикам пов\'язати сайт із колишніми назвами та офіційними профілями коледжу. Вносьте лише перевірені відомості; порожнє поле не виводиться.')
                    ->schema([
                        Forms\Components\Textarea::make(StructuredData::ALTERNATE_NAMES_KEY)
                            ->label('Інші назви та абревіатури')
                            ->rows(4)
                            ->maxLength(2000)
                            ->helperText('По одній у рядку: офіційні колишні назви, скорочення (напр. «ОТФК ОНТУ»). Без ключових слів і рекламних фраз.'),
                        Forms\Components\Textarea::make(StructuredData::SAME_AS_KEY)
                            ->label('Офіційні профілі (sameAs)')
                            ->rows(5)
                            ->maxLength(4000)
                            ->helperText('По одній адресі https:// у рядку: офіційні сторінки соцмереж, сторінка саме коледжу на сайті ОНТУ, запис у ЄДЕБО. Не вказуйте головну університету чи сторонні каталоги.')
                            ->rules([
                                fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    foreach (StructuredData::lines(is_string($value) ? $value : null) as $line) {
                                        if (! StructuredData::isHttpsUrl($line)) {
                                            $fail('Кожен рядок має бути повною адресою, що починається з https:// (помилка: «'.mb_strimwidth($line, 0, 80, '…').'»).');

                                            return;
                                        }
                                    }
                                },
                            ]),
                    ]),
                Forms\Components\Section::make('Аналітика (Google Analytics 4)')
                    ->description('Порожнє поле — аналітика повністю вимкнена, банера немає. Із заповненим ідентифікатором відвідувачі бачать банер згоди (на тестовому домені теж — для перевірки вигляду), а тег Google завантажується лише на основному домені і лише після натискання «Прийняти». Користувачі, що увійшли до адмінки, банера не бачать і не враховуються.')
                    ->schema([
                        Forms\Components\TextInput::make(Analytics::MEASUREMENT_ID_KEY)
                            ->label('Ідентифікатор потоку (Measurement ID)')
                            ->placeholder('G-XXXXXXXXXX')
                            ->maxLength(22)
                            ->rules([
                                fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    $id = strtoupper(trim((string) $value));
                                    if ($id !== '' && preg_match(Analytics::MEASUREMENT_ID_PATTERN, $id) !== 1) {
                                        $fail('Ідентифікатор має вигляд G-XXXXXXXXXX (латинські літери й цифри після «G-»).');
                                    }
                                },
                            ])
                            ->helperText('GA4 → Адміністрування → Потоки даних → веб-потік. У потоці вимкніть «Розширені вимірювання → Завантаження файлів» (подію file_download надсилає сам сайт), Google Signals і рекламні інтеграції.'),
                    ]),
            ])
            ->statePath('data');
    }
}
