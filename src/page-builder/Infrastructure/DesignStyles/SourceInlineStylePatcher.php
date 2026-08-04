<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\DesignStyles;

use UncannyPageBuilder\Application\DesignStyles\InlineStylePatch;
use UncannyPageBuilder\Application\DesignStyles\InlineStylePatcherInterface;

/**
 * Removes declarations from one identified opening tag without serializing the
 * surrounding HTML or unrelated inline declarations.
 */
final class SourceInlineStylePatcher implements InlineStylePatcherInterface
{
    public function removeFromElement(string $html, string $elementId, array $properties): InlineStylePatch
    {
        $properties = array_values(array_unique(array_filter(array_map(
            static fn(mixed $property): string => strtolower(trim(is_string($property) ? $property : '')),
            $properties,
        ))));
        if ($html === '' || $elementId === '' || $properties === []) {
            return InlineStylePatch::success($html);
        }

        $tags = $this->matchingOpeningTags($html, $elementId);
        if (count($tags) !== 1) {
            return InlineStylePatch::unsafe($html, count($tags) === 0 ? 'element_not_found' : 'duplicate_element_id');
        }

        $tag = $tags[0];
        $styleMatches = [];
        preg_match_all(
            '/\s+style\s*=\s*(["\'])(.*?)\1/is',
            $tag['source'],
            $styleMatches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
        );
        if ($styleMatches === []) {
            return preg_match('/(?:^|\s)style\s*=/i', $tag['source']) === 1
                ? InlineStylePatch::unsafe($html, 'unquoted_style_attribute')
                : InlineStylePatch::success($html);
        }
        if (count($styleMatches) !== 1) {
            return InlineStylePatch::unsafe($html, 'duplicate_style_attribute');
        }

        $style = (string) $styleMatches[0][2][0];
        $parsed = $this->parseDeclarations($style);
        if (!$parsed['safe']) {
            return InlineStylePatch::unsafe($html, 'unsafe_inline_declaration');
        }

        $propertyLookup = array_fill_keys($properties, true);
        $removed = [];
        $ranges = [];
        foreach ($parsed['declarations'] as $declaration) {
            if (!isset($propertyLookup[$declaration['property']])) {
                continue;
            }
            $property = $declaration['property'];
            $existing = $removed[$property] ?? null;
            if (
                $existing === null
                || $this->isImportant($declaration['value'])
                || !$this->isImportant($existing)
            ) {
                // CSS selects the last declaration unless an earlier !important
                // declaration outranks a later normal declaration.
                $removed[$property] = $declaration['value'];
            }
            $ranges[] = ['start' => $declaration['start'], 'length' => $declaration['length']];
        }
        if ($ranges === []) {
            return InlineStylePatch::success($html);
        }

        $patchedStyle = $style;
        foreach (array_reverse($ranges) as $range) {
            $patchedStyle = substr_replace($patchedStyle, '', $range['start'], $range['length']);
        }

        $styleAttribute = (string) $styleMatches[0][0][0];
        $styleAttributeOffset = (int) $styleMatches[0][0][1];
        if (trim($patchedStyle, "; \t\n\r\0\x0B") === '') {
            $patchedTag = substr_replace($tag['source'], '', $styleAttributeOffset, strlen($styleAttribute));
        } else {
            $valueOffset = (int) $styleMatches[0][2][1];
            $patchedTag = substr_replace($tag['source'], $patchedStyle, $valueOffset, strlen($style));
        }

        $patchedHtml = substr_replace($html, $patchedTag, $tag['start'], $tag['length']);

        return InlineStylePatch::success($patchedHtml, $removed);
    }

    private function isImportant(string $value): bool
    {
        return preg_match('/!important\s*$/i', trim($value)) === 1;
    }

    /**
     * @return list<array{start: int, length: int, source: string}>
     */
    private function matchingOpeningTags(string $html, string $elementId): array
    {
        $matches = [];
        $length = strlen($html);
        for ($start = 0; $start < $length; $start++) {
            if ($html[$start] !== '<' || ($html[$start + 1] ?? '') === '/') {
                continue;
            }
            if (substr($html, $start, 4) === '<!--') {
                $commentEnd = strpos($html, '-->', $start + 4);
                $start = $commentEnd === false ? $length : $commentEnd + 2;
                continue;
            }

            $end = $this->openingTagEnd($html, $start);
            if ($end === null) {
                break;
            }
            $source = substr($html, $start, $end - $start + 1);
            $idMatches = [];
            preg_match_all('/(?:^|\s)id\s*=\s*(["\'])(.*?)\1/is', $source, $idMatches, PREG_SET_ORDER);
            if (count($idMatches) === 1 && html_entity_decode((string) $idMatches[0][2], ENT_QUOTES | ENT_HTML5) === $elementId) {
                $matches[] = ['start' => $start, 'length' => strlen($source), 'source' => $source];
            }
            $start = $end;
        }

        return $matches;
    }

    private function openingTagEnd(string $html, int $start): ?int
    {
        $quote = '';
        $length = strlen($html);
        for ($index = $start + 1; $index < $length; $index++) {
            $character = $html[$index];
            if ($quote !== '') {
                if ($character === $quote) {
                    $quote = '';
                }
                continue;
            }
            if ($character === '"' || $character === "'") {
                $quote = $character;
                continue;
            }
            if ($character === '>') {
                return $index;
            }
        }

        return null;
    }

    /**
     * @return array{
     *   safe: bool,
     *   declarations: list<array{property: string, value: string, start: int, length: int}>
     * }
     */
    private function parseDeclarations(string $style): array
    {
        $declarations = [];
        $start = 0;
        $quote = '';
        $parentheses = 0;
        $inComment = false;
        $length = strlen($style);

        for ($index = 0; $index <= $length; $index++) {
            $character = $index < $length ? $style[$index] : ';';
            $next = $index + 1 < $length ? $style[$index + 1] : '';

            if ($inComment) {
                if ($character === '*' && $next === '/') {
                    $inComment = false;
                    $index++;
                }
                continue;
            }
            if ($quote !== '') {
                if ($character === '\\') {
                    $index++;
                    continue;
                }
                if ($character === $quote) {
                    $quote = '';
                }
                continue;
            }
            if ($character === '/' && $next === '*') {
                $inComment = true;
                $index++;
                continue;
            }
            if ($character === '"' || $character === "'") {
                $quote = $character;
                continue;
            }
            if ($character === '(') {
                $parentheses++;
                continue;
            }
            if ($character === ')') {
                if ($parentheses === 0) {
                    return ['safe' => false, 'declarations' => []];
                }
                $parentheses--;
                continue;
            }
            if ($character !== ';' || $parentheses > 0) {
                continue;
            }

            $hasSemicolon = $index < $length;
            $declarationLength = $index - $start + ($hasSemicolon ? 1 : 0);
            $source = substr($style, $start, $index - $start);
            if (trim($source) !== '') {
                if (preg_match('/^\s*([-a-zA-Z][\w-]*)\s*:(.*)$/s', $source, $parts) !== 1) {
                    return ['safe' => false, 'declarations' => []];
                }
                $declarations[] = [
                    'property' => strtolower($parts[1]),
                    'value'    => trim($parts[2]),
                    'start'    => $start,
                    'length'   => $declarationLength,
                ];
            }
            $start = $index + 1;
        }

        if ($quote !== '' || $inComment || $parentheses !== 0) {
            return ['safe' => false, 'declarations' => []];
        }

        return ['safe' => true, 'declarations' => $declarations];
    }
}
