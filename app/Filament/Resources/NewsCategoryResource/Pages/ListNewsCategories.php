<?php

namespace App\Filament\Resources\NewsCategoryResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\NewsCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNewsCategories extends ListRecords
{
    protected static string $resource = NewsCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
