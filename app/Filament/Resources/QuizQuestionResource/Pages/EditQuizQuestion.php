<?php

namespace App\Filament\Resources\QuizQuestionResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\QuizQuestionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditQuizQuestion extends EditRecord
{
    protected static string $resource = QuizQuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
