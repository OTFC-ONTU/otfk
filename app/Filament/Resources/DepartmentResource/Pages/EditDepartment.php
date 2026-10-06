<?php

namespace App\Filament\Resources\DepartmentResource\Pages;

use App\Filament\Resources\DepartmentResource;
use App\Filament\Support\PreviewFormAction;
use App\Filament\Support\ViewOnSite;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDepartment extends EditRecord
{
    protected static string $resource = DepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PreviewFormAction::make('department'),
            ViewOnSite::header(fn () => route('structure.show', $this->record)),
            Actions\DeleteAction::make(),
        ];
    }
}
