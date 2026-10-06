<?php

namespace App\Filament\Widgets;

use App\Models\Document;
use App\Models\Event;
use App\Models\Gallery;
use App\Models\News;
use App\Models\Specialty;
use App\Models\Staff;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = -3;

    protected function getStats(): array
    {
        return [
            Stat::make('Події', Event::count())
                ->description('Опубліковано: ' . Event::published()->count())
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('success'),

            Stat::make('Новини', News::count())
                ->description('Опубліковано: ' . News::published()->count())
                ->descriptionIcon('heroicon-m-newspaper')
                ->color('info'),

            Stat::make('Спеціальності', Specialty::count())
                ->description('Напрями підготовки')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('primary'),

            Stat::make('Документи', Document::count())
                ->description('У публічній інформації')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('gray'),

            Stat::make('Персонал', Staff::count())
                ->description('Викладачі та працівники')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('gray'),

            Stat::make('Галереї', Gallery::count())
                ->description('Фотоальбоми')
                ->descriptionIcon('heroicon-m-photo')
                ->color('gray'),
        ];
    }
}
