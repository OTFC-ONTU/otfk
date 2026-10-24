<?php

namespace App\Support\Sanitizer;

use App\Filament\Forms\Components\RichEditor\BlockSpacingExtension;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Пресети інтервалу й відступу з редактора (data-spacing, data-indent): лишаються лише відомі
 * значення (BlockSpacingExtension), решта відкидається — оформлення сайту знає тільки їх.
 */
class BlockSpacingAttributeSanitizer implements AttributeSanitizerInterface
{
    public function getSupportedElements(): ?array
    {
        return null;
    }

    public function getSupportedAttributes(): ?array
    {
        return ['data-spacing', 'data-indent'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $allowed = $attribute === 'data-spacing' ? BlockSpacingExtension::SPACING : BlockSpacingExtension::INDENT;

        return in_array(trim($value), $allowed, true) ? trim($value) : null;
    }
}
