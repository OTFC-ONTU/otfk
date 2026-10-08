<?php

namespace App\Filament\Widgets;

use App\Models\SiteVisit;
use Filament\Widgets\ChartWidget;

class VisitsChart extends ChartWidget
{
    protected ?string $heading = 'Відвідуваність за 30 днів';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $from = now()->subDays(29)->toDateString();

        $rows = SiteVisit::query()
            ->where('date', '>=', $from)
            ->selectRaw("date, sum(case when path = ? then hits else 0 end) as visits, sum(case when path != ? then hits else 0 end) as views", [SiteVisit::VISITS_PATH, SiteVisit::VISITS_PATH])
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy(fn ($r) => (string) $r->date);

        $labels = [];
        $views = [];
        $visits = [];

        for ($i = 29; $i >= 0; $i--) {
            $d = now()->subDays($i)->toDateString();
            $labels[] = now()->subDays($i)->format('d.m');
            $views[] = (int) ($rows[$d]->views ?? 0);
            $visits[] = (int) ($rows[$d]->visits ?? 0);
        }

        return [
            'labels' => $labels,
            'datasets' => [
                ['label' => 'Перегляди сторінок', 'data' => $views, 'borderColor' => '#2f59be', 'backgroundColor' => 'rgba(47,89,190,.12)', 'fill' => true, 'tension' => 0.35],
                ['label' => 'Відвідувачі (сесії)', 'data' => $visits, 'borderColor' => '#d98e1e', 'backgroundColor' => 'rgba(217,142,30,.10)', 'fill' => true, 'tension' => 0.35],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
