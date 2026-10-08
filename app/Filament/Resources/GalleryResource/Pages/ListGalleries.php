<?php

namespace App\Filament\Resources\GalleryResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\GalleryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGalleries extends ListRecords
{
    protected static string $resource = GalleryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
