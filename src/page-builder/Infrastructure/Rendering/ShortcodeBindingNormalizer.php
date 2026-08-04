<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Rendering;

use UncannyPageBuilder\Application\DesignStyles\ShortcodeStyleTargetMaterializer;

/**
 * Converts raw WordPress shortcode text into Page Builder binding regions.
 *
 * Shortcodes are WordPress runtime behavior, but Page Builder needs the live
 * canvas to expose an honest owner when a user points at rendered shortcode
 * output. This adapter turns registered shortcode text into the existing
 * wp_shortcode dynamic binding before DynamicRenderer runs.
 */
final class ShortcodeBindingNormalizer
{
    private const BINDING_ID = 'wp_shortcode';

    /**
     * Wrap registered shortcode text nodes as wp_shortcode dynamic regions.
     */
    public function normalize(string $html): string
    {
        if ($html === '' || !str_contains($html, '[')) {
            return $html;
        }

        $pattern = $this->shortcodePattern();
        if ($pattern === null) {
            return $html;
        }

        // DOMDocument strips Alpine @ attributes; encode and restore them.
        $encoded = (string) preg_replace('/\s@([\w.:+-]+)=/', ' data-x-on-$1=', $html);

        $doc = new \DOMDocument();
        $previousErrors = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"><div id="__upb_shortcode_root">' . $encoded . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        $root = $doc->getElementById('__upb_shortcode_root');
        if (!$root instanceof \DOMElement) {
            return $html;
        }

        $xpath = new \DOMXPath($doc);
        $textNodes = [];
        foreach ($xpath->query('//text()[contains(., "[")]') as $node) {
            if ($node instanceof \DOMText) {
                $textNodes[] = $node;
            }
        }

        $changed = false;
        $shortcodeIndex = 0;
        foreach ($textNodes as $node) {
            if ($node->parentNode === null || $this->isIgnoredParent($node->parentNode)) {
                continue;
            }

            if ($this->replaceTextShortcodes($doc, $node, $pattern, $shortcodeIndex)) {
                $changed = true;
            }
        }

        if (!$changed) {
            return $html;
        }

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $doc->saveHTML($child);
        }

        return (string) preg_replace('/\sdata-x-on-([\w.:+-]+)=/', ' @$1=', $output);
    }

    private function shortcodePattern(): ?string
    {
        global $shortcode_tags;

        if (!is_array($shortcode_tags) || $shortcode_tags === []) {
            return null;
        }

        return ShortcodeStyleTargetMaterializer::shortcodePatternForTags(array_keys($shortcode_tags));
    }

    private function isIgnoredParent(\DOMNode $node): bool
    {
        if (!$node instanceof \DOMElement) {
            return false;
        }

        return ShortcodeStyleTargetMaterializer::isIgnoredTextParent($node->nodeName);
    }

    private function replaceTextShortcodes(\DOMDocument $doc, \DOMText $node, string $pattern, int &$shortcodeIndex): bool
    {
        $text = $node->wholeText;
        $matches = [];
        if (!preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
            return false;
        }

        if ($this->promoteParentForSingleShortcode($node, $matches, $shortcodeIndex)) {
            return true;
        }

        $fragment = $doc->createDocumentFragment();
        $offset = 0;
        $changed = false;

        foreach ($matches[0] as $index => $fullMatch) {
            $shortcode = $fullMatch[0];
            $position = $fullMatch[1];
            $matchGroups = array_column($matches, $index);
            if (ShortcodeStyleTargetMaterializer::isEscapedShortcodeMatch($matchGroups)) {
                continue;
            }

            if ($position > $offset) {
                $fragment->appendChild($doc->createTextNode(substr($text, $offset, $position - $offset)));
            }

            $wrapper = $doc->createElement('div');
            $wrapper->setAttribute('data-ai-dynamic', self::BINDING_ID);
            $wrapper->setAttribute('data-shortcode', $shortcode);
            $wrapper->setAttribute('data-shortcode-index', (string) $shortcodeIndex);
            $wrapper->setAttribute('data-upb-display', 'block');
            $fragment->appendChild($wrapper);

            $offset = $position + strlen($shortcode);
            ++$shortcodeIndex;
            $changed = true;
        }

        if (!$changed) {
            return false;
        }

        if ($offset < strlen($text)) {
            $fragment->appendChild($doc->createTextNode(substr($text, $offset)));
        }

        $node->parentNode?->replaceChild($fragment, $node);

        return true;
    }

    /**
     * Persisted shortcode style owners are stored as ordinary wrappers:
     * <div id="upb-el-shortcode-...">[gallery]</div>. At render time that same
     * wrapper must become the dynamic boundary so element CSS and Design Lens
     * selection keep one owner.
     *
     * @param array<int, mixed> $matches
     */
    private function promoteParentForSingleShortcode(\DOMText $node, array $matches, int &$shortcodeIndex): bool
    {
        if (!$node->parentNode instanceof \DOMElement) {
            return false;
        }

        $parent = $node->parentNode;
        if (strtolower($parent->nodeName) !== 'div' || trim($parent->getAttribute('id')) === '') {
            return false;
        }

        if (count($matches[0] ?? []) !== 1) {
            return false;
        }

        $fullMatch = $matches[0][0] ?? null;
        if (!is_array($fullMatch)) {
            return false;
        }

        $shortcode = (string) ($fullMatch[0] ?? '');
        $position = (int) ($fullMatch[1] ?? -1);
        if ($position < 0 || trim($node->wholeText) !== $shortcode) {
            return false;
        }

        $matchGroups = array_column($matches, 0);
        if (ShortcodeStyleTargetMaterializer::isEscapedShortcodeMatch($matchGroups)) {
            return false;
        }

        while ($parent->firstChild !== null) {
            $parent->removeChild($parent->firstChild);
        }

        $parent->setAttribute('data-ai-dynamic', self::BINDING_ID);
        $parent->setAttribute('data-shortcode', $shortcode);
        $parent->setAttribute('data-shortcode-index', (string) $shortcodeIndex);
        $parent->setAttribute('data-upb-display', 'block');
        ++$shortcodeIndex;

        return true;
    }
}
