<?php

namespace App\Filament\Pages;

use App\Filament\Support\SettingsFormPage;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Support\HtmlString;

/**
 * Термінове оголошення — кольорова смуга над шапкою на всіх сторінках.
 * Кольори прев'ю продубльовано з app.blade.php (brand-700 / gold-500 /
 * red-600), бо стилів публічного сайту в адмінці немає.
 */
class AnnouncementSettings extends SettingsFormPage
{
    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Оголошення';

    protected static ?string $title = 'Оголошення';

    protected static ?string $slug = 'settings-announcement';

    /** Кольори смуги для прев'ю (як на сайті). */
    private const PREVIEW_COLORS = [
        'info' => '#284aaa',
        'warning' => '#d98e1e',
        'danger' => '#dc2626',
    ];

    protected static function keys(): array
    {
        return [
            'announcement_text' => 'textarea',
            'announcement_type' => 'text',
            'announcement_url' => 'url',
        ];
    }

    protected function fromSettings(array $state): array
    {
        $state['announcement_type'] = $state['announcement_type'] ?: 'info';

        return $state;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Смуга оголошення')
                    ->description('Кольорова смуга над шапкою на всіх сторінках. Порожній текст — смуги немає. Відвідувач може закрити смугу; після зміни тексту вона з’явиться знову.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Textarea::make('announcement_text')->label('Текст')->rows(3)->live(debounce: 400),
                        static::englishField('announcement_text', 'Текст', multiline: true),
                        Forms\Components\Select::make('announcement_type')->label('Колір смуги')
                            ->options([
                                'info' => 'Синя — інформація',
                                'warning' => 'Золота — важливо',
                                'danger' => 'Червона — терміново',
                            ])
                            ->selectablePlaceholder(false)
                            ->live(),
                        Forms\Components\TextInput::make('announcement_url')->label('Посилання (необов’язково)')
                            ->helperText('Куди веде клік по оголошенню, напр. /novyny/... або повна адреса.'),
                        Forms\Components\Placeholder::make('preview')
                            ->label('Попередній перегляд')
                            ->columnSpanFull()
                            ->visible(fn (Forms\Get $get) => filled(trim((string) $get('announcement_text'))))
                            ->content(fn (Forms\Get $get) => new HtmlString(
                                '<div style="background:'.(self::PREVIEW_COLORS[$get('announcement_type')] ?? self::PREVIEW_COLORS['info'])
                                .';color:#fff;padding:10px 16px;border-radius:8px;text-align:center;font-size:14px;font-weight:500;">'
                                .e(trim((string) $get('announcement_text')))
                                .'</div>'
                            )),
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
