<?php

namespace App\Filament\Forms\Components\RichEditor;

use Tiptap\Core\Extension;

/**
 * Інтервал і відступ блоків для серверного перетворення документа редактора: атрибути
 * data-spacing (абзаци, заголовки, списки) і data-indent (абзаци, списки) з пресетами,
 * а не розмірами — розміри для комп'ютера й телефона задає .prose-site (resources/css/app.css).
 * Клієнтська пара — public/js/admin/rich-editor-spacing.js (ті самі типи й значення).
 */
class BlockSpacingExtension extends Extension
{
    /** Інтервал: щільніше / просторіше; без атрибута — звичайний інтервал сайту. */
    public const SPACING = ['compact', 'loose'];

    /** Рівні відступу ліворуч. */
    public const INDENT = ['1', '2', '3'];

    public const SPACING_TYPES = ['paragraph', 'heading', 'bulletList', 'orderedList'];

    public const INDENT_TYPES = ['paragraph', 'bulletList', 'orderedList'];

    /** @var string */
    public static $name = 'blockSpacing';

    /** @return array<array<string, mixed>> */
    public function addGlobalAttributes(): array
    {
        return [
            ['types' => self::SPACING_TYPES, 'attributes' => ['spacing' => self::attribute('data-spacing', 'spacing', self::SPACING)]],
            ['types' => self::INDENT_TYPES, 'attributes' => ['indent' => self::attribute('data-indent', 'indent', self::INDENT)]],
        ];
    }

    /** @param  array<string>  $allowed */
    private static function attribute(string $html, string $key, array $allowed): array
    {
        return [
            'default' => null,
            'parseHTML' => fn ($DOMNode) => in_array($DOMNode->getAttribute($html), $allowed, true) ? $DOMNode->getAttribute($html) : null,
            'renderHTML' => fn ($attributes) => in_array($attributes->{$key} ?? null, $allowed, true) ? [$html => $attributes->{$key}] : null,
        ];
    }
}
