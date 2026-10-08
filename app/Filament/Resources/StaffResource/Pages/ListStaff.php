<?php

namespace App\Filament\Resources\StaffResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\StaffResource;
use Filament\Actions;
use App\Filament\Support\ReordersBySwappingPositions;
use Filament\Resources\Pages\ListRecords;

class ListStaff extends ListRecords
{
    use ReordersBySwappingPositions;

    protected static string $resource = StaffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
