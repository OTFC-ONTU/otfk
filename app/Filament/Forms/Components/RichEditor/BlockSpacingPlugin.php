<?php

namespace App\Filament\Forms\Components\RichEditor;

use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Illuminate\Support\Facades\File;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Js;

/**
 * Кнопки інтервалу між абзацами / пунктами списку й відступу ліворуч, як вирівнювання тексту:
 * діють на виділені абзаци (або на весь список, якщо курсор у ньому). У HTML — лише пресет
 * (data-spacing, data-indent), розміри для комп'ютера й телефона задає оформлення сайту.
 */
class BlockSpacingPlugin implements RichContentPlugin
{
    /** @var array<string, array{0: string, 1: string}> назва кнопки => [підказка, контури SVG] */
    private const TOOLS = [
        'spacingCompact' => ['Щільніше: менший інтервал між виділеними абзацами або пунктами списку (повторне натискання — звичайний)', 'M9 10h12M9 14h12M5 3v5M3 6l2 2 2-2M5 21v-5M3 18l2-2 2 2'],
        'spacingLoose' => ['Просторіше: більший інтервал між виділеними абзацами або пунктами списку (повторне натискання — звичайний)', 'M9 5h12M9 19h12M5 8v8M3 10l2-2 2 2M3 14l2 2 2-2'],
        'indentDecrease' => ['Зменшити відступ ліворуч (абзац або весь список)', 'M3 5h18M11 10h10M11 14h10M3 19h18M7 9l-4 3 4 3'],
        'indentIncrease' => ['Збільшити відступ ліворуч (абзац або весь список; на телефоні відступ менший)', 'M3 5h18M11 10h10M11 14h10M3 19h18M3 9l4 3-4 3'],
    ];

    public static function make(): static
    {
        return new static;
    }

    /** @return array<string> */
    public static function toolNames(): array
    {
        return array_keys(self::TOOLS);
    }

    public function getTipTapPhpExtensions(): array
    {
        return [new BlockSpacingExtension];
    }

    public function getTipTapJsExtensions(): array
    {
        $file = public_path('js/admin/rich-editor-spacing.js');

        return [asset('js/admin/rich-editor-spacing.js').'?v='.(File::exists($file) ? File::lastModified($file) : 0)];
    }

    public function getEditorTools(): array
    {
        $tools = [];
        foreach (self::TOOLS as $name => [$label, $path]) {
            $spacing = match ($name) {
                'spacingCompact' => 'compact',
                'spacingLoose' => 'loose',
                default => null,
            };

            $tool = RichEditorTool::make($name)
                ->label($label)
                ->icon(new HtmlString('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:100%;height:100%"><path d="'.$path.'"/></svg>'));

            $tools[] = $spacing
                ? $tool->jsHandler('window.otfkHtmlEditor?.setBlockSpacing($getEditor(), '.Js::from($spacing).')')
                    ->activeJsExpression('window.otfkHtmlEditor?.blockSpacingIs($getEditor(), '.Js::from($spacing).')')
                : $tool->jsHandler('window.otfkHtmlEditor?.changeIndent($getEditor(), '.($name === 'indentIncrease' ? 1 : -1).')');
        }

        return $tools;
    }

    public function getEditorActions(): array
    {
        return [];
    }
}
