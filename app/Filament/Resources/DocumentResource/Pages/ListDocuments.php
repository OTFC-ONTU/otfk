<?php

namespace App\Filament\Resources\DocumentResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\DocumentResource;
use Filament\Actions;
use App\Filament\Support\ReordersBySwappingPositions;
use Filament\Resources\Pages\ListRecords;

class ListDocuments extends ListRecords
{
    use ReordersBySwappingPositions;

    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
