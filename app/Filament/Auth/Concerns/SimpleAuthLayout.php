<?php

namespace App\Filament\Auth\Concerns;

use Filament\Pages\Concerns\HasMaxWidth;
use Filament\Pages\Concerns\HasTopbar;

/**
 * Проста розмітка (як у сторінки входу) для маршрутизованих сторінок
 * Filament\Pages\Page: SimplePage не реєструє маршрути панелі, тому
 * сторінки другого фактора успадковують Page і беруть звідси лише
 * методи, яких очікує layout.simple (логотип, ширина, без topbar).
 */
trait SimpleAuthLayout
{
    use HasMaxWidth;
    use HasTopbar;

    protected function getLayoutData(): array
    {
        return [
            'hasTopbar' => $this->hasTopbar(),
            'maxWidth' => $this->getMaxWidth(),
        ];
    }

    public function hasLogo(): bool
    {
        return true;
    }
}
