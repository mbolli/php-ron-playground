<?php

declare(strict_types=1);

namespace RonPlayground;

use Mbolli\TempestHighlightRon\RonLanguage;
use Tempest\Highlight\Highlighter;

/**
 * Server-side syntax highlighting for the converted output pane.
 *
 * Wraps a single tempest/highlight Highlighter (RON support added via
 * mbolli/tempest-highlight-ron; JSON ships with tempest). parse() returns escaped
 * inner HTML — `<span class="hl-...">` tokens with all text content HTML-escaped —
 * so the result is safe to drop into the output `<pre>` with Twig's `|raw`. The
 * instance is reused across requests (the app is a long-running OpenSwoole process).
 */
final class OutputHighlighter {
    private static ?Highlighter $highlighter = null;

    /**
     * Highlight converted output. $toRon selects the language of the OUTPUT:
     * RON when converting JSON -> RON, JSON when converting RON -> JSON.
     */
    public static function render(string $output, bool $toRon): string {
        return self::highlighter()->parse($output, $toRon ? 'ron' : 'json');
    }

    private static function highlighter(): Highlighter {
        return self::$highlighter ??= (new Highlighter())->addLanguage(new RonLanguage());
    }
}
