<?php

namespace App\Filament\Resources\QuickLinkResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\QuickLinkResource;
use App\Filament\Support\ViewOnSite;
use Filament\Actions;
use App\Filament\Support\ReordersBySwappingPositions;
use Filament\Resources\Pages\ListRecords;

class ListQuickLinks extends ListRecords
{
    use ReordersBySwappingPositions;

    protected static string $resource = QuickLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewOnSite::header(route('home')),
            CreateAction::make(),
        ];
    }
}
