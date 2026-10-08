<?php

namespace App\Filament\Resources\SettingResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\SettingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSetting extends EditRecord
{
    protected static string $resource = SettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    // Для type=image показуємо наявний файл у завантажувачі.
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (($data['type'] ?? null) === 'image') {
            $data['image_value'] = $data['value'] ?? null;
        }

        return $data;
    }

    // Зберігаємо шлях завантаженого зображення назад у колонку value.
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['type'] ?? null) === 'image') {
            $data['value'] = $data['image_value'] ?? ($data['value'] ?? null);
        }
        unset($data['image_value']);

        return $data;
    }
}
