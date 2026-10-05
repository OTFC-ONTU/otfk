<?php

namespace App\Filament\Pages;

use App\Filament\Support\SettingsFormPage;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;

/**
 * Основні налаштування сайту: назва й логотип, опис для пошуковиків,
 * текст і позначка версії в підвалі. Перекладні ключі мають англійські поля.
 */
class GeneralSettings extends SettingsFormPage
{
    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Основні';

    protected static ?string $title = 'Основні налаштування';

    protected static ?string $slug = 'settings-general';

    protected static function keys(): array
    {
        return [
            'brand_short' => 'text',
            'brand_name' => 'text',
            'site_description' => 'textarea',
            'logo' => 'image',
            'favicon' => 'image',
            'footer_about' => 'textarea',
            'site_version_label' => 'text',
            'site_version_color' => 'text',
        ];
    }

    protected function fromSettings(array $state): array
    {
        $state['site_version_color'] = $state['site_version_color'] ?: 'gold';

        return $state;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Назва та логотип')
                    ->description('Показуються в шапці, підвалі, у вкладці браузера та при поширенні посилань.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('brand_short')->label('Коротка назва')->maxLength(255)
                            ->helperText('Великий напис біля логотипа, напр. «ВСП ОТФК ОНТУ».'),
                        static::englishField('brand_short', 'Коротка назва'),
                        Forms\Components\TextInput::make('brand_name')->label('Повна назва')->maxLength(255)
                            ->helperText('Другий рядок під короткою назвою.'),
                        static::englishField('brand_name', 'Повна назва'),
                        Forms\Components\FileUpload::make('logo')->label('Логотип')->image()->imageEditor()
                            ->directory('settings')->helperText('PNG з прозорим тлом. Порожнє — стандартна емблема.'),
                        Forms\Components\FileUpload::make('favicon')->label('Іконка вкладки (favicon)')->image()
                            ->directory('settings')->helperText('Квадратне зображення 64×64 або більше.'),
                    ]),
                Forms\Components\Section::make('Опис для пошуковиків')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Textarea::make('site_description')->label('Опис сайту')->rows(3)
                            ->helperText('1–2 речення: їх показують Google і соцмережі під назвою сайту.'),
                        static::englishField('site_description', 'Опис сайту', multiline: true),
                    ]),
                Forms\Components\Section::make('Підвал сайту')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Textarea::make('footer_about')->label('Текст «Про коледж»')->rows(3)
                            ->helperText('Абзац під логотипом у підвалі. Порожнє — абзац приховано.'),
                        static::englishField('footer_about', 'Текст «Про коледж»', multiline: true),
                        Forms\Components\TextInput::make('site_version_label')->label('Позначка версії')
                            ->helperText('Бейдж у нижньому рядку підвалу, напр. «Бета-версія». Порожнє — приховано.'),
                        static::englishField('site_version_label', 'Позначка версії'),
                        Forms\Components\Select::make('site_version_color')->label('Колір позначки')
                            ->options([
                                'gold' => 'Золотий',
                                'green' => 'Зелений',
                                'blue' => 'Синій',
                                'red' => 'Червоний',
                                'gray' => 'Сірий',
                            ])
                            ->selectablePlaceholder(false),
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
