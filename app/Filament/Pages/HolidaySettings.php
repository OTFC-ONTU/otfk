<?php

namespace App\Filament\Pages;

use App\Filament\Support\SettingsFormPage;
use App\Support\HolidayTheme;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Support\Carbon;

/**
 * Святкова тема сайту (ключі `holiday_theme` і `holiday_theme_until`,
 * довідник App\Support\HolidayTheme). Вибір теми одразу показує
 * прев'ю шапки й вітання (filament.holiday-preview).
 */
class HolidaySettings extends SettingsFormPage
{
    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Святкова тема';

    protected static ?string $title = 'Святкова тема';

    protected static ?string $slug = 'settings-holiday';

    protected static string $settingsGroup = 'appearance';

    protected static function keys(): array
    {
        return [
            'holiday_theme' => 'text',
            'holiday_theme_until' => 'text',
        ];
    }

    /** Бейдж у меню, поки тема діє на сайті. */
    public static function getNavigationBadge(): ?string
    {
        return HolidayTheme::active() ? 'увімк.' : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    protected function fromSettings(array $state): array
    {
        $state['holiday_theme'] = HolidayTheme::config($state['holiday_theme'] ?? null) ? $state['holiday_theme'] : '';

        return $state;
    }

    protected function toSettings(array $state): array
    {
        $state['holiday_theme_until'] = filled($state['holiday_theme'] ?? null) && filled($state['holiday_theme_until'] ?? null)
            ? Carbon::parse($state['holiday_theme_until'])->format('Y-m-d')
            : '';

        return $state;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Тема')
                    ->description('Прикраси на всіх сторінках: святкові кольори шапки й підвалу, гірлянда під меню, значок біля логотипа, вітання над підвалом і короткий «снігопад» при першому відкритті сайту (раз за сесію; вимикається, якщо відвідувач обрав у системі «зменшити рух»).')
                    ->schema([
                        Forms\Components\Radio::make('holiday_theme')
                            ->hiddenLabel()
                            ->options(HolidayTheme::options())
                            ->descriptions(HolidayTheme::descriptions())
                            ->columns(['sm' => 2, 'xl' => 3])
                            ->live(),
                        Forms\Components\DatePicker::make('holiday_theme_until')
                            ->label('Вимкнути автоматично після')
                            ->native(false)
                            ->displayFormat('d.m.Y')
                            ->closeOnDateSelection()
                            ->visible(fn (Forms\Get $get) => filled($get('holiday_theme')))
                            ->helperText('Тема діє до кінця цього дня (київський час), потім сайт сам повертається до звичайного вигляду. Порожнє — доки не вимкнете вручну.'),
                    ]),
                Forms\Components\Section::make('Попередній перегляд')
                    ->visible(fn (Forms\Get $get) => filled($get('holiday_theme')))
                    ->schema([
                        Forms\Components\Placeholder::make('preview')
                            ->hiddenLabel()
                            ->content(fn (Forms\Get $get) => view('filament.holiday-preview', [
                                'key' => $get('holiday_theme'),
                                'theme' => HolidayTheme::config($get('holiday_theme')),
                            ])),
                    ]),
            ])
            ->statePath('data');
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewSite')->label('Переглянути сайт')->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')->url(url('/'))->openUrlInNewTab(),
        ];
    }
}
