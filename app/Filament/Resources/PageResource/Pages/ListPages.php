<?php

namespace App\Filament\Resources\PageResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\PageResource;
use Filament\Actions;
use App\Filament\Support\ReordersBySwappingPositions;
use Filament\Resources\Pages\ListRecords;

class ListPages extends ListRecords
{
    use ReordersBySwappingPositions;

    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
