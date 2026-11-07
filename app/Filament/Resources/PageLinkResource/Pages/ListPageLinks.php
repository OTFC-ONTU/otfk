<?php

namespace App\Filament\Resources\PageLinkResource\Pages;

use App\Filament\Resources\PageLinkResource;
use App\Filament\Support\ReordersBySwappingPositions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPageLinks extends ListRecords
{
    use ReordersBySwappingPositions;

    protected static string $resource = PageLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
