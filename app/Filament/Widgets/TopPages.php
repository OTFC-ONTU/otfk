<?php

namespace App\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use App\Models\SiteVisit;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class TopPages extends TableWidget
{
    protected static ?string $heading = 'Топ сторінок за 30 днів';

    protected static ?int $sort = 4;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                SiteVisit::query()
                    ->where('date', '>=', now()->subDays(29)->toDateString())
                    ->where('path', '!=', SiteVisit::VISITS_PATH)
                    ->selectRaw('min(id) as id, path, sum(hits) as total')
                    ->groupBy('path')
                    ->orderByDesc('total')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('path')->label('Сторінка')
                    ->url(fn ($record) => url($record->path), shouldOpenInNewTab: true)
                    ->limit(60),
                TextColumn::make('total')->label('Перегляди')->numeric()->alignRight(),
            ])
            ->paginated(false);
    }
}
