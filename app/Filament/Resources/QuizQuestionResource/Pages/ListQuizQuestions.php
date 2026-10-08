<?php

namespace App\Filament\Resources\QuizQuestionResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\QuizQuestionResource;
use App\Filament\Support\ViewOnSite;
use Filament\Actions;
use App\Filament\Support\ReordersBySwappingPositions;
use Filament\Resources\Pages\ListRecords;

class ListQuizQuestions extends ListRecords
{
    use ReordersBySwappingPositions;

    protected static string $resource = QuizQuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewOnSite::header(route('quiz')),
            CreateAction::make(),
        ];
    }
}
