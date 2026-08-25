<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Compiler;

use UncannyPageBuilder\Domain\DesignStyles\ElementStyleCssRenderer;
use UncannyPageBuilder\Domain\Section\SectionCollection;

final class ShadowCompiler
{
    /**
     * Zero-specificity canvas scope for generated section CSS.
     *
     * The editor chrome shares a document with the rendered canvas, so AI CSS
     * must be scoped without making generated rules heavier than their own
     * selectors. `:where()` keeps the scope from beating `.ai-*` classes.
     */
    public const CANVAS_SCOPE = ':where(#uncanny-pb-canvas)';

    private \UncannyPageBuilder\Infrastructure\Compiler\CssMinifier $minifier;

    public function __construct(\UncannyPageBuilder\Infrastructure\Compiler\CssMinifier $minifier)
    {
        $this->minifier = $minifier;
    }

    /**
     * Compile a SectionCollection into clean SEO HTML + minified CSS.
     * Pure function — no side effects.
     */
    public function compile(SectionCollection $sections): CompiledOutput
    {
        return $this->compileCollection($sections, false);
    }

    /**
     * Compile the rendered output for unsaved browser sections.
     *
     * Unsaved sections use negative IDs until persistence assigns durable
     * positive IDs. Preview compilation keeps those temporary IDs so the
     * generated selectors match the section identities painted in the editor.
     */
    public function compilePreview(SectionCollection $sections): CompiledOutput
    {
        return $this->compileCollection($sections, true);
    }

    private function compileCollection(SectionCollection $sections, bool $preview): CompiledOutput
    {
        $htmlParts = [];
        $cssParts  = [];

        foreach ($sections->all() as $section) {
            $sectionId = $section->id() ?? 0;
            $html = $section->content()->html();
            $css  = $section->content()->css();
            $elementCss = $preview
                ? ElementStyleCssRenderer::renderForPreviewSection($sectionId, $section->content()->elementStyles())
                : ElementStyleCssRenderer::renderForSection($sectionId, $section->content()->elementStyles());

            if ($html !== '') {
                $htmlParts[] = $this->compileSeoHtml($html, $sectionId);
            }

            if ($css !== '') {
                $cssParts[] = self::scopeCssToCanvas(self::repairCss($css));
            }
            if ($elementCss !== '') {
                $cssParts[] = self::scopeCssToCanvas(self::repairCss($elementCss));
            }
        }

        $minifiedCss = $this->minifier->minify(implode(' ', $cssParts));

        return new CompiledOutput(
            seoHtml: $this->wrapSeoHtmlSnapshot(implode("\n", $htmlParts), $minifiedCss),
            minifiedCss: $minifiedCss,
        );
    }

    /**
     * Sanitize CSS by parsing rule-by-rule and dropping any declaration
     * with invalid syntax (unbalanced parens, missing value, truncated).
     * Valid rules pass through untouched.
     */
    public static function repairCss(string $css): string
    {
        $css = self::stripCssComments($css);

        // Quick exit — if brackets are balanced, CSS is structurally sound.
        if (
            substr_count($css, '{') === substr_count($css, '}')
            && substr_count($css, '(') === substr_count($css, ')')
        ) {
            return $css;
        }

        // Parse top-level rule blocks: "selector { declarations }"
        // and @-rules like "@media (...) { nested-rules }"
        $output = '';
        $len = strlen($css);
        $i = 0;

        while ($i < $len) {
            // Skip whitespace.
            while ($i < $len && ctype_space($css[$i])) {
                $output .= $css[$i];
                $i++;
            }

            if ($i >= $len) {
                break;
            }

            // Find the opening brace for this rule.
            $bracePos = strpos($css, '{', $i);
            if ($bracePos === false) {
                // No more opening braces — remainder is a truncated selector/fragment. Drop it.
                break;
            }

            $selector = substr($css, $i, $bracePos - $i);

            // Find the matching closing brace (handles nesting for @media etc.).
            $bodyStart = $bracePos + 1;
            $depth = 1;
            $j = $bodyStart;
            while ($j < $len && $depth > 0) {
                if ($css[$j] === '{') {
                    $depth++;
                } elseif ($css[$j] === '}') {
                    $depth--;
                }
                $j++;
            }

            if ($depth > 0) {
                // Unclosed block — truncated. Salvage what we can.
                $partialBody = substr($css, $bodyStart);
                $trimmedSelector = ltrim($selector);
                if (str_starts_with($trimmedSelector, '@media') || str_starts_with($trimmedSelector, '@keyframes')) {
                    // Nested @-rule: recurse to salvage valid inner rules.
                    $cleanBody = self::repairCss($partialBody);
                } else {
                    // Regular rule: salvage valid declarations.
                    $cleanBody = self::sanitizeDeclarations($partialBody);
                }
                if (trim($cleanBody) !== '') {
                    $output .= $selector . '{' . $cleanBody . '}';
                }
                break;
            }

            $body = substr($css, $bodyStart, $j - $bodyStart - 1);

            // Check if this is a nested @-rule (e.g. @media, @keyframes).
            $trimmedSelector = ltrim($selector);
            if (str_starts_with($trimmedSelector, '@media') || str_starts_with($trimmedSelector, '@keyframes')) {
                // Recursively sanitize the nested body.
                $cleanBody = self::repairCss($body);
                if (trim($cleanBody) !== '') {
                    $output .= $selector . '{' . $cleanBody . '}';
                }
            } else {
                // Regular rule block — validate each declaration.
                $cleanBody = self::sanitizeDeclarations($body);
                if (trim($cleanBody) !== '') {
                    $output .= $selector . '{' . $cleanBody . '}';
                }
            }

            $i = $j;
        }

        return $output;
    }

    /**
     * Scope generated CSS to the canvas without increasing selector weight.
     *
     * AI-authored sections should not spend prompt tokens prefixing every
     * selector. The compiler owns that runtime boundary: regular selectors are
     * prefixed with `:where(#uncanny-pb-canvas)`, document selectors (`body`,
     * `html`, `:root`) are mapped to the canvas itself, and grouping at-rules
     * recurse into their nested rules. At-rules that define global primitives
     * (`@keyframes`, `@font-face`, `@property`, `@page`) are left untouched.
     */
    public static function scopeCssToCanvas(string $css, string $scope = self::CANVAS_SCOPE): string
    {
        $css = self::stripCssComments(self::repairCss($css));

        if (trim($css) === '') {
            return $css;
        }

        $output = '';
        $len = strlen($css);
        $i = 0;

        while ($i < $len) {
            $bracePos = strpos($css, '{', $i);
            if ($bracePos === false) {
                $output .= substr($css, $i);
                break;
            }

            $statementEnd = self::findTopLevelSemicolonBefore($css, $i, $bracePos);
            if ($statementEnd !== null) {
                $output .= substr($css, $i, $statementEnd - $i + 1);
                $i = $statementEnd + 1;
                continue;
            }

            $selector = substr($css, $i, $bracePos - $i);
            $closePos = self::findMatchingBrace($css, $bracePos);
            if ($closePos === null) {
                // Preserve repairCss()'s responsibility for malformed input.
                $output .= substr($css, $i);
                break;
            }

            $body = substr($css, $bracePos + 1, $closePos - $bracePos - 1);
            $trimmedSelector = ltrim($selector);

            if (str_starts_with($trimmedSelector, '@')) {
                $output .= $selector . '{' . self::scopeAtRuleBody($trimmedSelector, $body, $scope) . '}';
            } else {
                $output .= self::scopeSelectorList($selector, $scope) . '{' . $body . '}';
            }

            $i = $closePos + 1;
        }

        return $output;
    }

    /**
     * Scope nested rules only for grouping at-rules.
     */
    private static function scopeAtRuleBody(string $atRule, string $body, string $scope): string
    {
        $lower = strtolower($atRule);

        if (
            str_starts_with($lower, '@media')
            || str_starts_with($lower, '@supports')
            || str_starts_with($lower, '@container')
            || str_starts_with($lower, '@layer')
            || str_starts_with($lower, '@document')
        ) {
            return self::scopeCssToCanvas($body, $scope);
        }

        return $body;
    }

    /**
     * Prefix each selector in a comma-separated selector list.
     */
    private static function scopeSelectorList(string $selectorList, string $scope): string
    {
        $selectors = self::splitSelectorList($selectorList);
        $scoped = [];

        foreach ($selectors as $selector) {
            $scopedSelector = self::scopeSingleSelector($selector, $scope);
            if ($scopedSelector !== null && $scopedSelector !== '') {
                $scoped[] = $scopedSelector;
            }
        }

        return implode(',', $scoped);
    }

    /**
     * Scope one selector while preserving AI-authored selector specificity.
     */
    private static function scopeSingleSelector(string $selector, string $scope): ?string
    {
        $selector = trim($selector);
        if ($selector === '') {
            return $selector;
        }

        if (str_starts_with($selector, $scope) || str_starts_with($selector, '#uncanny-pb-canvas')) {
            return $selector;
        }

        $selector = self::removeLeadingDocumentSelector($selector);
        if ($selector === null) {
            return null;
        }

        if ($selector === '') {
            return $scope;
        }

        return $scope . ' ' . $selector;
    }

    /**
     * Map document-level selectors to the canvas element.
     */
    private static function removeLeadingDocumentSelector(string $selector): ?string
    {
        $documentState = '';

        do {
            $previous = $selector;
            $selector = ltrim($selector);

            if (preg_match('/^(?:html|body|:root)\b/i', $selector, $matches) !== 1) {
                break;
            }

            [$offset, $documentState] = self::consumeDocumentSelectorQualifiers(
                $selector,
                strlen($matches[0]),
                $documentState
            );

            $selector = ltrim(substr($selector, $offset), " \t\n\r\0\x0B>+~");
        } while ($selector !== $previous);

        if ($documentState !== '') {
            return null;
        }

        return ltrim($selector, " \t\n\r\0\x0B>+~");
    }

    /**
     * Consume document selector qualifiers and detect descendant-state tests.
     *
     * Body classes, IDs, attrs, and ordinary pseudo-classes remain stripped to
     * preserve the old document-to-canvas mapping contract. `:has(...)` is
     * different: it tests descendants in the full generated document. That
     * cannot be mapped safely to the canvas without changing selector meaning,
     * so selectors carrying it are dropped instead of becoming unconditional.
     *
     * @return array{0: int, 1: string}
     */
    private static function consumeDocumentSelectorQualifiers(string $selector, int $offset, string $documentState): array
    {
        $len = strlen($selector);

        while ($offset < $len) {
            $char = $selector[$offset];

            if ($char === '#' || $char === '.') {
                $offset = self::consumeIdentifier($selector, $offset + 1);
                continue;
            }

            if ($char === '[') {
                $close = self::findMatchingSquareBracket($selector, $offset);
                if ($close === null) {
                    return [$offset, $documentState];
                }

                $offset = $close + 1;
                continue;
            }

            if ($char === ':') {
                [$nextOffset, $state] = self::consumeDocumentPseudoClass($selector, $offset);
                if ($nextOffset === $offset) {
                    return [$offset, $documentState];
                }

                if ($state !== '') {
                    $documentState .= $state;
                }

                $offset = $nextOffset;
                continue;
            }

            break;
        }

        return [$offset, $documentState];
    }

    private static function consumeIdentifier(string $selector, int $offset): int
    {
        $len = strlen($selector);

        while ($offset < $len && preg_match('/[a-zA-Z0-9_-]/', $selector[$offset]) === 1) {
            $offset++;
        }

        return $offset;
    }

    /**
     * @return array{0: int, 1: string}
     */
    private static function consumeDocumentPseudoClass(string $selector, int $offset): array
    {
        $start = $offset;
        $offset++;
        $nameStart = $offset;
        $offset = self::consumeIdentifier($selector, $offset);
        $name = strtolower(substr($selector, $nameStart, $offset - $nameStart));

        if ($name === '') {
            return [$start, ''];
        }

        if (($selector[$offset] ?? '') !== '(') {
            return [$offset, ''];
        }

        $close = self::findMatchingParenthesis($selector, $offset);
        if ($close === null) {
            return [$start, ''];
        }

        $pseudo = substr($selector, $start, $close - $start + 1);

        return [$close + 1, $name === 'has' ? $pseudo : ''];
    }

    private static function findMatchingSquareBracket(string $selector, int $openPos): ?int
    {
        return self::findMatchingDelimiter($selector, $openPos, '[', ']');
    }

    private static function findMatchingParenthesis(string $selector, int $openPos): ?int
    {
        return self::findMatchingDelimiter($selector, $openPos, '(', ')');
    }

    private static function findMatchingDelimiter(string $value, int $openPos, string $open, string $close): ?int
    {
        $depth = 1;
        $quote = null;
        $len = strlen($value);

        for ($i = $openPos + 1; $i < $len; $i++) {
            $char = $value[$i];

            if ($quote !== null) {
                if ($char === '\\') {
                    $i++;
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if ($char === $open) {
                $depth++;
            } elseif ($char === $close) {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    /**
     * Split selector lists without splitting commas inside functions/attrs.
     *
     * @return array<int, string>
     */
    private static function splitSelectorList(string $selectorList): array
    {
        $selectors = [];
        $current = '';
        $parenDepth = 0;
        $bracketDepth = 0;
        $quote = null;
        $len = strlen($selectorList);

        for ($i = 0; $i < $len; $i++) {
            $char = $selectorList[$i];

            if ($quote !== null) {
                $current .= $char;
                if ($char === '\\' && $i + 1 < $len) {
                    $current .= $selectorList[++$i];
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $current .= $char;
                continue;
            }

            if ($char === '(') {
                $parenDepth++;
            } elseif ($char === ')' && $parenDepth > 0) {
                $parenDepth--;
            } elseif ($char === '[') {
                $bracketDepth++;
            } elseif ($char === ']' && $bracketDepth > 0) {
                $bracketDepth--;
            }

            if ($char === ',' && $parenDepth === 0 && $bracketDepth === 0) {
                $selectors[] = $current;
                $current = '';
                continue;
            }

            $current .= $char;
        }

        $selectors[] = $current;

        return $selectors;
    }

    /**
     * Find an at-rule statement before the next block.
     */
    private static function findTopLevelSemicolonBefore(string $css, int $start, int $before): ?int
    {
        $quote = null;
        $parenDepth = 0;

        for ($i = $start; $i < $before; $i++) {
            $char = $css[$i];

            if ($quote !== null) {
                if ($char === '\\') {
                    $i++;
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if ($char === '(') {
                $parenDepth++;
                continue;
            }

            if ($char === ')' && $parenDepth > 0) {
                $parenDepth--;
                continue;
            }

            if ($char === ';' && $parenDepth === 0) {
                $statement = self::stripLeadingCssCommentsAndWhitespace(substr($css, $start, $i - $start + 1));
                if (str_starts_with($statement, '@')) {
                    return $i;
                }
            }
        }

        return null;
    }

    /**
     * Trim CSS whitespace and leading comments before checking a statement.
     */
    private static function stripLeadingCssCommentsAndWhitespace(string $css): string
    {
        return (string) preg_replace('/^(?:\s|\/\*.*?\*\/)*/s', '', $css);
    }

    /**
     * Drop CSS comments before repair/scoping so comments cannot skew parsing.
     */
    private static function stripCssComments(string $css): string
    {
        return (string) preg_replace('/\/\*.*?\*\//s', '', $css);
    }

    /**
     * Find a rule block's closing brace while ignoring braces inside strings.
     */
    private static function findMatchingBrace(string $css, int $openPos): ?int
    {
        $depth = 1;
        $quote = null;
        $len = strlen($css);

        for ($i = $openPos + 1; $i < $len; $i++) {
            $char = $css[$i];

            if ($quote !== null) {
                if ($char === '\\') {
                    $i++;
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    /**
     * Validate individual CSS declarations within a rule body.
     * Drop any declaration with unbalanced parentheses or missing colon.
     */
    private static function sanitizeDeclarations(string $body): string
    {
        $declarations = explode(';', $body);
        $valid = [];

        foreach ($declarations as $decl) {
            $decl = trim($decl);
            if ($decl === '') {
                continue;
            }

            // Must have a colon separating property and value.
            if (strpos($decl, ':') === false) {
                continue;
            }

            // Parentheses must be balanced (catches truncated gradients, calc, etc.).
            if (substr_count($decl, '(') !== substr_count($decl, ')')) {
                continue;
            }

            $valid[] = $decl;
        }

        return $valid !== [] ? implode(';', $valid) . ';' : '';
    }

    /**
     * Build a static, non-authoritative HTML snapshot for published artifacts.
     *
     * Runtime-only dynamic regions are removed instead of saving unbound
     * placeholder templates into exports or compiled page artifacts. Live page
     * rendering still uses the persisted section source through CanvasRenderer.
     */
    private function compileSeoHtml(string $html, int $sectionId): string
    {
        return $this->stripAiAttributes($this->removeDynamicRegions($this->ensureSectionRootId($html, $sectionId)));
    }

    private function ensureSectionRootId(string $html, int $sectionId): string
    {
        if ($sectionId <= 0 || trim($html) === '') {
            return $html;
        }

        if (preg_match('/^<[a-z][a-z0-9]*\b[^>]*\sid\s*=/i', $html) === 1) {
            return preg_replace(
                '/^(<[a-z][a-z0-9]*\b[^>]*\sid\s*=\s*)(["\'])(.*?)\2/i',
                '$1$2upb-section-' . $sectionId . '$2',
                $html,
                1,
            ) ?? $html;
        }

        return preg_replace(
            '/^(<[a-z][a-z0-9]*)(\s|>)/i',
            '$1 id="upb-section-' . $sectionId . '"$2',
            $html,
            1,
        ) ?? $html;
    }

    /**
     * Wrap static section HTML as a canvas snapshot.
     *
     * Public live rendering still reads section rows and compiled CSS directly.
     * Published artifacts carry the same canvas id required by scoped CSS
     * instead of exposing unstyled section fragments.
     */
    private function wrapSeoHtmlSnapshot(string $html, string $minifiedCss): string
    {
        if (trim($html) === '') {
            return '';
        }

        $style = $minifiedCss !== ''
            ? '<style id="uncanny-page-builder-snapshot-css">' . $this->escapeStyleText($minifiedCss) . '</style>'
            : '';

        return $style . '<div id="uncanny-pb-canvas">' . $html . '</div>';
    }

    private function escapeStyleText(string $css): string
    {
        return str_ireplace('</style', '<\\/style', $css);
    }

    /**
     * Remove data-ai-dynamic regions from the SEO snapshot.
     */
    private function removeDynamicRegions(string $html): string
    {
        if (!str_contains($html, 'data-ai-dynamic')) {
            return $html;
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8"><html><body><div data-upb-seo-wrapper="1">' . $html . '</div></body></html>',
            LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return $html;
        }

        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query('//*[@data-ai-dynamic]');
        if ($nodes !== false) {
            foreach (iterator_to_array($nodes) as $node) {
                if ($node instanceof \DOMNode && $node->parentNode instanceof \DOMNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }

        $wrapper = $xpath->query('//*[@data-upb-seo-wrapper="1"]')->item(0);
        if (!$wrapper instanceof \DOMNode) {
            return $html;
        }

        $snapshot = '';
        foreach ($wrapper->childNodes as $child) {
            $snapshot .= $dom->saveHTML($child) ?: '';
        }

        return $snapshot;
    }

    /**
     * Strip data-ai-* and Alpine.js attributes for clean SEO output.
     */
    private function stripAiAttributes(string $html): string
    {
        return preg_replace(
            '/\s+(data-ai-[a-z-]+|x-[a-z-]+|@[a-z][a-z-]*)(?:\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+))?/i',
            '',
            $html
        ) ?? $html;
    }
}
