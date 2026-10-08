<?php

namespace App\Filament\Resources\LegacyRedirectResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\LegacyRedirectResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLegacyRedirects extends ListRecords
{
    protected static string $resource = LegacyRedirectResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
