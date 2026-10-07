<?php

namespace App\Filament\Pages;

use App\Filament\Support\SettingsFormPage;
use App\Filament\Support\ViewOnSite;
use App\Models\QuickLink;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;

/**
 * Вигляд підвалу в одному місці. Святковими темами керує HolidaySettings.
 * Далі — все, що показує підвал сайту: текст «Про коледж»,
 * посилання-партнери (рядки quick_links з location=footer_partner —
 * редагуються репітером і синхронізуються при збереженні) та позначка
 * версії сайту. Контакти й соцмережі підвал бере зі сторінки «Контакти
 * та соцмережі». Затемнення фото банерів сюди свідомо не винесено — воно
 * редагується в розділі «Банери» (одне місце керування).
 */
class AppearanceSettings extends SettingsFormPage
{
    protected static ?string $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Підвал і вигляд';

    protected static ?string $title = 'Підвал і вигляд';

    protected static string $settingsGroup = 'appearance';

    protected static function keys(): array
    {
        return ['footer_about' => 'textarea', 'site_version_label' => 'text', 'site_version_color' => 'text'];
    }

    protected function fromSettings(array $state): array
    {
        $state['site_version_color'] = $state['site_version_color'] ?: 'gold';
        $state['partners'] = $this->partnerState();

        return $state;
    }

    /** @return array<int, array<string, mixed>> рядки footer_partner для репітера */
    private function partnerState(): array
    {
        return QuickLink::location('footer_partner')->ordered()->get()
            ->map(fn (QuickLink $link) => [
                'id' => $link->id,
                'title' => $link->title,
                'title_en' => $link->title_en,
                'url' => $link->url,
                'open_new_tab' => $link->open_new_tab,
                'is_visible' => $link->is_visible,
            ])
            ->all();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Підвал сайту')
                    ->schema([
                        Forms\Components\Textarea::make('footer_about')
                            ->label('Текст «Про коледж»')
                            ->rows(3)
                            ->helperText('Абзац під назвою коледжу в підвалі. Контакти та соцмережі підвал бере зі сторінки «Контакти та соцмережі».'),
                        static::englishField('footer_about', 'Текст «Про коледж»', multiline: true),
                    ]),
                Forms\Components\Section::make('Партнери в підвалі')
                    ->description('Колонка «Партнери» в підвалі кожної сторінки. Порядок міняється перетягуванням. Якщо список порожній, сайт показує стандартні посилання (ОНТУ, МОН).')
                    ->schema([
                        Forms\Components\Repeater::make('partners')
                            ->hiddenLabel()
                            ->schema([
                                Forms\Components\Hidden::make('id'),
                                Forms\Components\TextInput::make('title')->label('Назва')->required()->maxLength(255),
                                Forms\Components\TextInput::make('title_en')->label('Назва англійською')->maxLength(255),
                                Forms\Components\TextInput::make('url')->label('Посилання')->required()->maxLength(255)
                                    ->placeholder('https://...'),
                                Forms\Components\Toggle::make('open_new_tab')->label('У новій вкладці')->default(true)->inline(false),
                                Forms\Components\Toggle::make('is_visible')->label('Показувати')->default(true)->inline(false),
                            ])
                            ->columns(['sm' => 2, 'lg' => 4])
                            ->reorderable()
                            ->addActionLabel('Додати партнера')
                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                            ->defaultItems(0),
                    ]),
                Forms\Components\Section::make('Позначка версії сайту')
                    ->description('Маленький бейдж у нижньому рядку підвалу (напр. «Альфа-версія»). Порожній напис — бейдж приховано.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('site_version_label')
                            ->label('Напис'),
                        static::englishField('site_version_label', 'Позначка версії'),
                        Forms\Components\Select::make('site_version_color')
                            ->label('Колір')
                            ->options([
                                'gold' => 'Золотий',
                                'green' => 'Зелений',
                                'blue' => 'Синій',
                                'red' => 'Червоний',
                                'gray' => 'Сірий',
                            ])
                            ->selectablePlaceholder(false),
                    ]),
                Forms\Components\Section::make('Банери')
                    ->schema([
                        Forms\Components\Placeholder::make('banner_note')
                            ->hiddenLabel()
                            ->content('Затемнення фото банерів головної редагується в розділі «Контент → Банери» (поле «Сила затемнення» над таблицею).'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $this->syncPartners($this->form->getState()['partners'] ?? []);

        parent::save();

        // Повторне наповнення: репітер отримує свіжі id щойно створених рядків.
        $this->mount();
    }

    /**
     * Синхронізація репітера з таблицею quick_links (location=footer_partner):
     * наявні рядки оновлюються за прихованим id, нові створюються, прибрані
     * з форми — видаляються; порядок = позиція в репітері.
     *
     * @param  array<int, array<string, mixed>>  $partners
     */
    private function syncPartners(array $partners): void
    {
        $keptIds = [];

        foreach (array_values($partners) as $index => $partner) {
            $link = QuickLink::location('footer_partner')->find($partner['id'] ?? null)
                ?? new QuickLink(['location' => 'footer_partner']);

            $oldEnglish = $link->title_en;
            $link->fill([
                'title' => (string) $partner['title'],
                'title_en' => $partner['title_en'] ?? null,
                'url' => (string) $partner['url'],
                'open_new_tab' => (bool) ($partner['open_new_tab'] ?? true),
                'is_visible' => (bool) ($partner['is_visible'] ?? true),
                'sort_order' => $index,
            ]);
            if ($oldEnglish !== $link->title_en) {
                $link->translation_published = $link->hasCompleteEnglishTranslation();
            }
            $link->save();

            $keptIds[] = $link->id;
        }

        QuickLink::location('footer_partner')->whereNotIn('id', $keptIds)->delete();
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            ViewOnSite::header(url('/')),
        ];
    }
}
