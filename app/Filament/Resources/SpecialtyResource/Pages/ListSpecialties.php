<?php

namespace App\Filament\Resources\SpecialtyResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\SpecialtyResource;
use Filament\Actions;
use App\Filament\Support\ReordersBySwappingPositions;
use Filament\Resources\Pages\ListRecords;

class ListSpecialties extends ListRecords
{
    use ReordersBySwappingPositions;

    protected static string $resource = SpecialtyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
