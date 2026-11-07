<?php

namespace App\Filament\Resources\PageLinkResource\Pages;

use App\Filament\Resources\PageLinkResource;
use App\Models\QuickLink;
use Filament\Resources\Pages\CreateRecord;

class CreatePageLink extends CreateRecord
{
    protected static string $resource = PageLinkResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['location'] = QuickLink::LOCATION_PAGE_LINK;

        return $data;
    }
}
