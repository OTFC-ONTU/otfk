<?php

namespace App\Filament\Resources\ProgramResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\ProgramResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPrograms extends ListRecords
{
    protected static string $resource = ProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
