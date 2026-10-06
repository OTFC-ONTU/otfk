<?php

namespace App\Filament\Resources\SpecialtyResource\Pages;

use App\Filament\Resources\SpecialtyResource;
use App\Filament\Support\PreviewFormAction;
use App\Filament\Support\ViewOnSite;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSpecialty extends EditRecord
{
    protected static string $resource = SpecialtyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PreviewFormAction::make('specialty'),
            ViewOnSite::header(fn () => route('specialties.show', $this->record)),
            Actions\DeleteAction::make(),
        ];
    }
}
