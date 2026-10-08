<?php

namespace App\Filament\Resources\StatItemResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\StatItemResource;
use App\Filament\Support\ViewOnSite;
use Filament\Actions;
use App\Filament\Support\ReordersBySwappingPositions;
use Filament\Resources\Pages\ListRecords;

class ListStatItems extends ListRecords
{
    use ReordersBySwappingPositions;

    protected static string $resource = StatItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewOnSite::header(route('home')),
            CreateAction::make(),
        ];
    }
}
