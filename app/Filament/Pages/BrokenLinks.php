<?php

namespace App\Filament\Pages;

use App\Support\LinkChecker;
use Filament\Actions\Action;
use Filament\Pages\Page as FilamentPage;
use Illuminate\Support\Facades\Cache;

/**
 * Звіт про биті внутрішні посилання у контенті. Рушій — App\Support\LinkChecker
 * (він же в artisan-команді otfk:check-links). Результат кешується на 10 хвилин,
 * кнопка «Перевірити заново» скидає кеш.
 */
class BrokenLinks extends FilamentPage
{
    protected static ?string $navigationIcon = 'heroicon-o-link-slash';

    protected static ?string $navigationLabel = 'Биті посилання';

    protected static ?string $title = 'Биті внутрішні посилання';

    protected static ?string $navigationGroup = 'Структура сайту';

    protected static ?int $navigationSort = 0;

    protected static string $view = 'filament.pages.broken-links';

    private const CACHE_KEY = 'broken_links.report';

    /** @return list<array{source: string, title: string, edit_url: string, url: string, reason: string}> */
    public function report(): array
    {
        return Cache::remember(self::CACHE_KEY, 600, fn () => app(LinkChecker::class)->scan());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recheck')
                ->label('Перевірити заново')
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => Cache::forget(self::CACHE_KEY)),
        ];
    }

    public function getSubheading(): ?string
    {
        return 'Сканує тіла сторінок і новин: посилання на неіснуючі сторінки й розділи, відсутні файли у сховищі, посилання на старий сайт. Звіт кешується на 10 хвилин.';
    }
}
