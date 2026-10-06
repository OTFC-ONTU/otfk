<?php

namespace App\Filament\Resources\StatItemResource\Pages;

use App\Filament\Resources\StatItemResource;
use App\Filament\Support\ViewOnSite;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStatItems extends ListRecords
{
    protected static string $resource = StatItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewOnSite::header(route('home')),
            Actions\CreateAction::make(),
        ];
    }
}
