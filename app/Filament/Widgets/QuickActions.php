<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class QuickActions extends Widget
{
    protected string $view = 'filament.widgets.quick-actions';

    /** Кнопки мають зʼявлятися миттєво, без відкладеного Livewire-завантаження. */
    protected static bool $isLazy = false;

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';
}
