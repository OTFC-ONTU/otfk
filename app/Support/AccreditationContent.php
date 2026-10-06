<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;

class AccreditationContent
{
    /** Відновлює вкладені панелі старого Markdown-імпорту, зберігаючи посилання та мову. */
    public static function restore(string $html): string
    {
        if (str_contains($html, '<details') || ! str_contains($html, 'imported-from:https://otfk.od.ua/licensing_and_accreditation')) {
            return $html;
        }
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="accreditation-content">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = (new DOMXPath($dom))->query('//*[@id="accreditation-content"]')->item(0);
        if (! $root instanceof DOMElement) {
            return $html;
        }
        $nodes = array_values(array_filter(iterator_to_array($root->childNodes), fn ($node) => $node instanceof DOMElement));
        if (count($nodes) < 6 || $nodes[0]->tagName !== 'p') {
            return $html;
        }

        $summary = fn (DOMElement $node) => '<summary>'.e(trim($node->textContent)).'</summary>';
        $result = '<details class="content-accordion">'.$summary($nodes[0]).'<div class="content-accordion__body">';
        $inAccreditation = true;
        $pairs = 0;
        for ($i = 1; $i < count($nodes); $i++) {
            $node = $nodes[$i];
            $next = $nodes[$i + 1] ?? null;
            $content = $nodes[$i + 2] ?? null;
            $pair = $node->tagName === 'p' && $next?->tagName === 'p'
                && trim($node->textContent) === trim($next->textContent) && $content;
            if ($pair) {
                $inner = $content->tagName === 'ol';
                if (! $inner && $inAccreditation) {
                    $result .= '</div></details>';
                    $inAccreditation = false;
                }
                $result .= '<details class="content-accordion">'.$summary($node).'<div class="content-accordion__body">'.$dom->saveHTML($content).'</div></details>';
                $i += 2;
                $pairs++;
            } else {
                $result .= $dom->saveHTML($node);
            }
        }
        if ($inAccreditation) {
            $result .= '</div></details>';
        }

        preg_match('~<!--imported-from:[^>]+-->~', $html, $marker);

        return $pairs >= 2 ? $result."\n".($marker[0] ?? '') : $html;
    }
}
