<?php

namespace App\Filament\Resources\LegacyRedirectResource\Pages;

use App\Filament\Resources\LegacyRedirectResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLegacyRedirect extends CreateRecord
{
    protected static string $resource = LegacyRedirectResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return LegacyRedirectResource::mutate($data);
    }
}
