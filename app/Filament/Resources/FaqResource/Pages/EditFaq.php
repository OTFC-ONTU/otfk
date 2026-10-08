<?php

namespace App\Filament\Resources\FaqResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\FaqResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFaq extends EditRecord
{
    protected static string $resource = FaqResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
