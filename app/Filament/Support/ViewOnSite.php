<?php

namespace App\Filament\Support;

use Closure;
use Filament\Actions\Action as PageAction;
use Filament\Tables\Actions\Action as TableAction;

/**
 * Спільна кнопка «Переглянути на сайті» для адмінки: відкриває публічну
 * сторінку запису (або розділ сайту) у новій вкладці. Використовується
 * і в шапках сторінок Filament, і як дія рядка таблиці.
 */
class ViewOnSite
{
    /** Дія для шапки сторінки (Edit/List/Page). */
    public static function header(string|Closure $url): PageAction
    {
        return PageAction::make('viewOnSite')
            ->label('Переглянути на сайті')
            ->icon('heroicon-o-arrow-top-right-on-square')
            ->color('gray')
            ->url($url)
            ->openUrlInNewTab();
    }

    /** Дія рядка таблиці. */
    public static function table(Closure $url): TableAction
    {
        return TableAction::make('viewOnSite')
            ->label('На сайті')
            ->icon('heroicon-o-arrow-top-right-on-square')
            ->url($url)
            ->openUrlInNewTab();
    }
}
