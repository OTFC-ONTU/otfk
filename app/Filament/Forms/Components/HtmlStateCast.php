<?php

namespace App\Filament\Forms\Components;

use Filament\Schemas\Components\StateCasts\Contracts\StateCast;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Стан HtmlRichEditor: HTML лишається рядком; документ TipTap (правка у візуальному режимі)
 * перетворюється на HTML під час збереження.
 */
class HtmlStateCast implements StateCast
{
    public function __construct(protected HtmlRichEditor $editor) {}

    public function get(mixed $state): mixed
    {
        return is_array($state) ? $this->editor->documentToHtml($state) : $state;
    }

    public function set(mixed $state): mixed
    {
        return $state instanceof Htmlable ? $state->toHtml() : $state;
    }
}
