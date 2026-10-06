<?php

namespace App\Filament\Resources\NewsResource\Pages;

use App\Filament\Resources\NewsResource;
use App\Filament\Support\PreviewFormAction;
use App\Filament\Support\ViewOnSite;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNews extends EditRecord
{
    protected static string $resource = NewsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PreviewFormAction::make('news'),
            ViewOnSite::header(fn () => route('news.show', $this->record)),
            Actions\DeleteAction::make(),
        ];
    }
}
