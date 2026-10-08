<?php

namespace App\Filament\Resources\NotFoundLogResource\Pages;

use App\Filament\Resources\NotFoundLogResource;
use Filament\Resources\Pages\ListRecords;

class ListNotFoundLogs extends ListRecords
{
    protected static string $resource = NotFoundLogResource::class;
}
