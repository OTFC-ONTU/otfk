<?php

namespace App\Filament\Resources\LegacyRedirectResource\Pages;

use App\Filament\Resources\LegacyRedirectResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLegacyRedirect extends EditRecord
{
    protected static string $resource = LegacyRedirectResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return LegacyRedirectResource::mutate($data);
    }

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
