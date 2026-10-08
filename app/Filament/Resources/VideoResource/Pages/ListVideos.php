<?php

namespace App\Filament\Resources\VideoResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\VideoResource;
use App\Filament\Support\ViewOnSite;
use Filament\Actions;
use App\Filament\Support\ReordersBySwappingPositions;
use Filament\Resources\Pages\ListRecords;

class ListVideos extends ListRecords
{
    use ReordersBySwappingPositions;

    protected static string $resource = VideoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewOnSite::header(route('video.index')),
            CreateAction::make(),
        ];
    }
}
