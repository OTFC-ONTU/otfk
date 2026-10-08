<?php

namespace App\Filament\Resources\BannerResource\Pages;

use Filament\Schemas\Schema;
use Filament\Actions\CreateAction;
use App\Filament\Resources\BannerResource;
use App\Filament\Support\ViewOnSite;
use App\Models\Setting;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Section;
use Filament\View\PanelsRenderHook;

class ListBanners extends ListRecords
{
    protected static string $resource = BannerResource::class;

    public ?array $overlay = [];

    public function mount(): void
    {
        parent::mount();

        $this->overlayForm->fill([
            'opacity' => min(100, max(0, (int) (Setting::get('banner_overlay_opacity') ?? 75))),
        ]);
    }

    /** Над таблицею — налаштування затемнення фото (зберігається одразу при виборі). */
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Затемнення фото')
                ->description('Наскільки затемнюється зображення під текстом на головній сторінці. Зміни застосовуються одразу після вибору значення.')
                ->schema([EmbeddedSchema::make('overlayForm')]),
            $this->getTabsContentComponent(),
            RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
            EmbeddedTable::make(),
            RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
        ]);
    }

    public function overlayForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('opacity')
                    ->label('Сила затемнення')
                    ->options(collect(range(0, 100, 5))->mapWithKeys(fn (int $value) => [$value => "{$value}%"])->all())
                    ->live()
                    ->afterStateUpdated(function (?int $state): void {
                        Setting::updateOrCreate(
                            ['key' => 'banner_overlay_opacity'],
                            [
                                'value' => (string) min(100, max(0, $state ?? 75)),
                                'group' => 'appearance',
                                'type' => 'number',
                            ],
                        );
                    })
                    ->helperText('0 — без затемнення (лише фото), 100 — максимальне затемнення для читабельного тексту.'),
            ])
            ->statePath('overlay');
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewOnSite::header(route('home')),
            CreateAction::make(),
        ];
    }
}