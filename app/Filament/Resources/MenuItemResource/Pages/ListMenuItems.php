<?php

namespace App\Filament\Resources\MenuItemResource\Pages;

use App\Filament\Resources\MenuItemResource;
use App\Filament\Support\ViewOnSite;
use App\Models\MenuItem;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;

/**
 * Меню — двома рівнями замість однієї таблиці на всі ~80 пунктів:
 * вкладка «Верхній рівень» + окрема вкладка підпунктів на кожен головний
 * пункт. Перетягування порядку працює всередині відкритої вкладки,
 * «Створити» підставляє батьківський пункт активної вкладки.
 */
class ListMenuItems extends ListRecords
{
    protected static string $resource = MenuItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewOnSite::header(route('home')),
            Actions\CreateAction::make()->url(function (): string {
                $tab = (string) $this->activeTab;

                return MenuItemResource::getUrl('create', str_starts_with($tab, 'sub-')
                    ? ['parent' => (int) substr($tab, 4)]
                    : []);
            }),
        ];
    }

    public function getTabs(): array
    {
        $roots = MenuItem::whereNull('parent_id')->orderBy('sort_order')->orderBy('id')->get();
        $childCounts = MenuItem::whereNotNull('parent_id')
            ->selectRaw('parent_id, count(*) as total')
            ->groupBy('parent_id')
            ->pluck('total', 'parent_id');

        $tabs = [
            'top' => Tab::make('Верхній рівень')
                ->badge($roots->count())
                ->modifyQueryUsing(fn ($query) => $query->whereNull('parent_id')),
        ];

        foreach ($roots as $root) {
            $tabs['sub-' . $root->id] = Tab::make($root->label)
                ->badge($childCounts[$root->id] ?? 0)
                ->modifyQueryUsing(fn ($query) => $query->where('parent_id', $root->id));
        }

        return $tabs;
    }
}
