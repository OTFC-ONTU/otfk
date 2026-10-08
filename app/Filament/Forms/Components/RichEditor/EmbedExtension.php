<?php

namespace App\Filament\Forms\Components\RichEditor;

use Tiptap\Core\Node;

/**
 * Вузол TipTap «embed» для серверного перетворення документа редактора: <iframe> (PDF, відео,
 * документ Google, карта) зберігається з усіма атрибутами, які пропускає HtmlSanitizer,
 * зокрема типовими для імпорту class="w-full" style="min-height:24rem;border:0" loading="lazy".
 * Клієнтська пара — public/js/admin/rich-editor-embed.js (ті самі атрибути).
 */
class EmbedExtension extends Node
{
    /** Атрибути iframe, що переносяться без змін (спільний список із JS-розширенням). */
    public const ATTRIBUTES = ['src', 'title', 'width', 'height', 'class', 'style', 'loading', 'allow', 'allowfullscreen', 'frameborder', 'referrerpolicy'];

    /** @var string */
    public static $name = 'embed';

    /** @return array<array<string, mixed>> */
    public function parseHTML(): array
    {
        return [['tag' => 'iframe']];
    }

    /** @return array<string, array<string, mixed>> */
    public function addAttributes(): array
    {
        $attributes = [];
        foreach (self::ATTRIBUTES as $name) {
            $attributes[$name] = [
                'default' => null,
                // Булевий атрибут без значення (allowfullscreen) інакше зник би при виводі
                'parseHTML' => fn ($DOMNode) => $DOMNode->hasAttribute($name) ? ($DOMNode->getAttribute($name) === '' ? $name : $DOMNode->getAttribute($name)) : null,
                'renderHTML' => fn ($attributes) => isset($attributes->{$name}) ? [$name => $attributes->{$name}] : [],
            ];
        }

        return $attributes;
    }

    /**
     * @param  object  $node
     * @param  array<string, mixed>  $HTMLAttributes
     * @return array<mixed>
     */
    public function renderHTML($node, $HTMLAttributes = []): array
    {
        return ['iframe', $HTMLAttributes, 0];
    }
}
