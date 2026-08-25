<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Section;

/**
 * Keeps a copied section root and its owned style targets on one identity.
 *
 * A reusable section moves from a durable source ID to a negative browser
 * preview ID and then to a new durable ID. Its HTML root, raw CSS, and
 * structured element styles must move together during each transition.
 */
final class SectionRootIdentityRemapper
{
    /**
     * Move every owned root reference from one section ID to another.
     *
     * Both IDs can be durable positive IDs or temporary negative IDs. Zero is
     * not a section identity, so a zero value leaves the content unchanged.
     */
    public static function remap(SectionContent $content, int $fromSectionId, int $toSectionId): SectionContent
    {
        if ($fromSectionId === 0 || $toSectionId === 0 || $fromSectionId === $toSectionId) {
            return $content;
        }

        $fromRoot = 'upb-section-' . $fromSectionId;
        $toRoot = 'upb-section-' . $toSectionId;
        $html = self::remapRootId($content->html(), $fromRoot, $toRoot);
        $css = (string) preg_replace(
            '/#' . preg_quote($fromRoot, '/') . '(?![A-Za-z0-9_-])/',
            '#' . $toRoot,
            $content->css(),
        );

        return new SectionContent(
            $html,
            $css,
            $content->elementStyles()->remapElementIds([$fromRoot => $toRoot]),
        );
    }

    /**
     * Read the canonical ID from the first authored element root.
     *
     * CSS can refer to another section, so it is not evidence that the current
     * section owns that identity. Null means that the root has no section ID.
     */
    public static function rootSectionId(SectionContent $content): ?int
    {
        $attribute = self::rootIdAttribute($content->html());
        if ($attribute !== null && preg_match('/^upb-section-(-?[1-9][0-9]*)$/', $attribute['value'], $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Replace a matching ID on the first authored element root.
     *
     * Leading whitespace and comments are valid source and remain unchanged.
     * An unrelated authored root ID is also preserved.
     */
    private static function remapRootId(string $html, string $fromRoot, string $toRoot): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $attribute = self::rootIdAttribute($html);
        if ($attribute === null || $attribute['value'] !== $fromRoot) {
            return $html;
        }

        return substr_replace($html, $toRoot, $attribute['offset'], $attribute['length']);
    }

    /**
     * Locate the ID value on the first element without parsing lookalikes from
     * another quoted attribute. The returned byte range excludes its quotes.
     *
     * @return array{value: string, offset: int, length: int}|null
     */
    private static function rootIdAttribute(string $html): ?array
    {
        $tag = self::firstElementOpeningTag($html);
        if ($tag === null) {
            return null;
        }

        $cursor = $tag['attributes'];
        while ($cursor < $tag['end']) {
            while ($cursor < $tag['end'] && ctype_space($html[$cursor])) {
                $cursor++;
            }
            if ($cursor >= $tag['end'] || $html[$cursor] === '/') {
                break;
            }

            $nameStart = $cursor;
            while (
                $cursor < $tag['end']
                && !ctype_space($html[$cursor])
                && !in_array($html[$cursor], ['=', '/', '>'], true)
            ) {
                $cursor++;
            }
            if ($cursor === $nameStart) {
                $cursor++;
                continue;
            }
            $name = strtolower(substr($html, $nameStart, $cursor - $nameStart));

            while ($cursor < $tag['end'] && ctype_space($html[$cursor])) {
                $cursor++;
            }
            if ($cursor >= $tag['end'] || $html[$cursor] !== '=') {
                continue;
            }
            $cursor++;
            while ($cursor < $tag['end'] && ctype_space($html[$cursor])) {
                $cursor++;
            }
            if ($cursor >= $tag['end']) {
                break;
            }

            $quote = in_array($html[$cursor], ['"', "'"], true) ? $html[$cursor] : null;
            if ($quote !== null) {
                $cursor++;
                $valueStart = $cursor;
                while ($cursor < $tag['end'] && $html[$cursor] !== $quote) {
                    $cursor++;
                }
            } else {
                $valueStart = $cursor;
                while (
                    $cursor < $tag['end']
                    && !ctype_space($html[$cursor])
                    && !in_array($html[$cursor], ['/', '>'], true)
                ) {
                    $cursor++;
                }
            }

            if ($name === 'id') {
                return [
                    'value' => substr($html, $valueStart, $cursor - $valueStart),
                    'offset' => $valueStart,
                    'length' => $cursor - $valueStart,
                ];
            }

            if ($quote !== null && $cursor < $tag['end']) {
                $cursor++;
            }
        }

        return null;
    }

    /**
     * Find the byte range of the first element opening tag.
     *
     * @return array{attributes: int, end: int}|null
     */
    private static function firstElementOpeningTag(string $html): ?array
    {
        $length = strlen($html);
        $cursor = 0;
        while ($cursor < $length) {
            while ($cursor < $length && ctype_space($html[$cursor])) {
                $cursor++;
            }
            if (substr($html, $cursor, 4) !== '<!--') {
                break;
            }
            $commentEnd = strpos($html, '-->', $cursor + 4);
            if ($commentEnd === false) {
                return null;
            }
            $cursor = $commentEnd + 3;
        }

        if ($cursor >= $length || $html[$cursor] !== '<' || !isset($html[$cursor + 1]) || !ctype_alpha($html[$cursor + 1])) {
            return null;
        }
        $cursor += 2;
        while ($cursor < $length && preg_match('/[A-Za-z0-9:_-]/', $html[$cursor]) === 1) {
            $cursor++;
        }
        $attributes = $cursor;
        $quote = null;
        while ($cursor < $length) {
            $character = $html[$cursor];
            if ($quote !== null) {
                if ($character === $quote) {
                    $quote = null;
                }
            } elseif ($character === '"' || $character === "'") {
                $quote = $character;
            } elseif ($character === '>') {
                return ['attributes' => $attributes, 'end' => $cursor];
            }
            $cursor++;
        }

        return null;
    }
}
