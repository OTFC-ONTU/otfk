<?php

namespace App\Filament\Resources\StatItemResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\StatItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStatItem extends EditRecord
{
    protected static string $resource = StatItemResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
