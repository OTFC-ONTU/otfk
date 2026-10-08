<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Support\SafeDeleteAction;
use App\Filament\Resources\PageResource;
use App\Filament\Support\PreviewFormAction;
use App\Filament\Support\ViewOnSite;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PreviewFormAction::make('page'),
            ViewOnSite::header(fn () => url('/' . $this->record->slug)),
            SafeDeleteAction::make(),
        ];
    }
}
