<?php

namespace App\Filament\Resources\GalleryResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\GalleryResource;
use Filament\Actions;
use App\Filament\Support\ReordersBySwappingPositions;
use Filament\Resources\Pages\ListRecords;

class ListGalleries extends ListRecords
{
    use ReordersBySwappingPositions;

    protected static string $resource = GalleryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
