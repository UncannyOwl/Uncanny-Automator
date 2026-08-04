<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\DesignStyles;

/**
 * Materializes a rendered shortcode boundary back into stored shortcode source.
 *
 * The canvas exposes shortcode wrappers by the occurrence index assigned during
 * rendering. Save-time lookup must count the same shortcode candidates: matches
 * in rendered text nodes, excluding shortcode text whose direct parent is a raw
 * or code-like element. The final write still splices the original HTML string
 * so unrelated source bytes are left alone.
 */
final class ShortcodeStyleTargetMaterializer
{
    private const SHORTCODE_RENDER_SELECTOR_PATTERN = '/^\[data-section-id="(?P<section_id>[1-9][0-9]*)"\]\s+\[data-shortcode-index="(?P<index>[0-9]+)"\]$/';
    private const DEFAULT_TAGS = ['gallery', 'caption', 'audio', 'video', 'playlist', 'embed'];
    private const FALLBACK_SHORTCODE_REGEX_FORMAT = '/\\['
        . '(\\[?)'
        . '(%s)'
        . '(?![\\w-])'
        . '('
        .     '[^\\]\\/]*'
        .     '(?:'
        .         '\\/(?!\\])'
        .         '[^\\]\\/]*'
        .     ')*?'
        . ')'
        . '(?:'
        .     '(\\/)'
        .     '\\]'
        . '|'
        .     '\\]'
        .     '(?:'
        .         '('
        .             '[^\\[]*+'
        .             '(?:'
        .                 '\\[(?!\\/\\2\\])'
        .                 '[^\\[]*+'
        .             ')*+'
        .         ')'
        .         '\\[\\/\\2\\]'
        .     ')?'
        . ')'
        . '(\\]?)/s';
    private const IGNORED_TEXT_PARENT_TAGS = ['script', 'style', 'textarea', 'code', 'pre'];
    private const RAW_TEXT_TAGS = ['script', 'style', 'textarea'];
    private const VOID_TAGS = [
        'area',
        'base',
        'br',
        'col',
        'embed',
        'hr',
        'img',
        'input',
        'link',
        'meta',
        'param',
        'source',
        'track',
        'wbr',
    ];

    /**
     * @return array{html: string, element_id: string, promoted: bool}|null
     */
    public static function materialize(string $html, ?string $selector, int $sectionId): ?array
    {
        if (
            $sectionId <= 0
            || !is_string($selector)
            || preg_match(self::SHORTCODE_RENDER_SELECTOR_PATTERN, trim($selector), $matches) !== 1
        ) {
            return null;
        }

        if ((int) ($matches['section_id'] ?? 0) !== $sectionId) {
            return null;
        }

        $index = (int) ($matches['index'] ?? -1);
        if ($index < 0) {
            return null;
        }

        $elementId = 'upb-el-shortcode-' . $sectionId . '-' . $index;
        if (str_contains($html, 'id="' . $elementId . '"') || str_contains($html, "id='" . $elementId . "'")) {
            return ['html' => $html, 'element_id' => $elementId, 'promoted' => false];
        }

        $pattern = self::shortcodePattern();
        if ($pattern === null) {
            return null;
        }

        $match = self::shortcodeAtRenderedIndex($html, $pattern, $index);
        if ($match === null) {
            return null;
        }

        $replacement = '<div id="' . $elementId . '">' . $match['shortcode'] . '</div>';

        return [
            'html'       => substr_replace($html, $replacement, $match['position'], $match['length']),
            'element_id' => $elementId,
            'promoted'   => true,
        ];
    }

    public static function isIgnoredTextParent(string $nodeName): bool
    {
        return in_array(strtolower($nodeName), self::IGNORED_TEXT_PARENT_TAGS, true);
    }

    /**
     * @param array<int, mixed> $matches
     */
    public static function isEscapedShortcodeMatch(array $matches): bool
    {
        $openingEscape = is_array($matches[1] ?? null) ? ($matches[1][0] ?? '') : ($matches[1] ?? '');
        $closingEscape = is_array($matches[6] ?? null) ? ($matches[6][0] ?? '') : ($matches[6] ?? '');

        return $openingEscape === '[' || $closingEscape === ']';
    }

    /**
     * @param array<int|string, mixed> $tags
     */
    public static function shortcodePatternForTags(array $tags): ?string
    {
        $tags = array_values(array_filter(
            array_map(static fn(mixed $tag): string => is_scalar($tag) ? trim((string) $tag) : '', $tags),
            static fn(string $tag): bool => preg_match('/^[A-Za-z0-9_-]+$/', $tag) === 1,
        ));

        if ($tags === []) {
            return null;
        }

        if (\function_exists('get_shortcode_regex')) {
            return '/' . \get_shortcode_regex($tags) . '/s';
        }

        $tagPattern = implode('|', array_map(static fn(string $tag): string => preg_quote($tag, '/'), $tags));

        return sprintf(self::FALLBACK_SHORTCODE_REGEX_FORMAT, $tagPattern);
    }

    private static function shortcodePattern(): ?string
    {
        global $shortcode_tags;

        $tags = is_array($shortcode_tags) && $shortcode_tags !== []
            ? array_keys($shortcode_tags)
            : self::DEFAULT_TAGS;

        return self::shortcodePatternForTags($tags);
    }

    /**
     * @return array{shortcode: string, position: int, length: int}|null
     */
    private static function shortcodeAtRenderedIndex(string $html, string $pattern, int $targetIndex): ?array
    {
        $current = -1;

        foreach (self::textSegments($html) as $segment) {
            if (self::isIgnoredTextParent($segment['parent']) || !str_contains($segment['text'], '[')) {
                continue;
            }

            $matches = [];
            if (!preg_match_all($pattern, $segment['text'], $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($matches[0] as $matchOffset => $match) {
                $matchGroups = array_column($matches, $matchOffset);
                if (self::isEscapedShortcodeMatch($matchGroups)) {
                    continue;
                }

                ++$current;
                if ($current !== $targetIndex) {
                    continue;
                }

                $shortcode = (string) ($match[0] ?? '');
                $position = (int) ($match[1] ?? 0);

                return [
                    'shortcode' => $shortcode,
                    'position'  => $segment['offset'] + $position,
                    'length'    => strlen($shortcode),
                ];
            }
        }

        return null;
    }

    /**
     * @return \Generator<int, array{text: string, offset: int, parent: string}>
     */
    private static function textSegments(string $html): \Generator
    {
        $length = strlen($html);
        $offset = 0;
        $stack = [];

        while ($offset < $length) {
            $tagStart = self::nextTagStart($html, $offset);
            if ($tagStart === null) {
                if ($offset < $length) {
                    yield [
                        'text' => substr($html, $offset),
                        'offset' => $offset,
                        'parent' => self::currentParent($stack),
                    ];
                }
                break;
            }

            if ($tagStart > $offset) {
                yield [
                    'text' => substr($html, $offset, $tagStart - $offset),
                    'offset' => $offset,
                    'parent' => self::currentParent($stack),
                ];
            }

            $tag = self::tagAt($html, $tagStart);
            if ($tag === null) {
                $offset = $tagStart + 1;
                continue;
            }

            if ($tag['kind'] === 'open' && !in_array($tag['name'], self::VOID_TAGS, true) && !$tag['self_closing']) {
                $stack[] = $tag['name'];
            } elseif ($tag['kind'] === 'close') {
                self::popTag($stack, $tag['name']);
            }

            $offset = $tag['next'];
        }
    }

    private static function nextTagStart(string $html, int $offset): ?int
    {
        $length = strlen($html);

        while ($offset < $length) {
            $tagStart = strpos($html, '<', $offset);
            if ($tagStart === false || $tagStart + 1 >= $length) {
                return null;
            }

            $next = $html[$tagStart + 1];
            if ($next === '/' || $next === '!' || $next === '?' || ctype_alpha($next)) {
                return $tagStart;
            }

            $offset = $tagStart + 1;
        }

        return null;
    }

    /**
     * @return array{kind: string, name: string, self_closing: bool, next: int}|null
     */
    private static function tagAt(string $html, int $start): ?array
    {
        if (str_starts_with(substr($html, $start, 4), '<!--')) {
            $end = strpos($html, '-->', $start + 4);
            return [
                'kind' => 'special',
                'name' => '',
                'self_closing' => true,
                'next' => $end === false ? strlen($html) : $end + 3,
            ];
        }

        $end = self::tagEnd($html, $start);
        if ($end === null) {
            return null;
        }

        $tagText = substr($html, $start, $end - $start + 1);
        if (preg_match('/^<\s*\/\s*([A-Za-z][A-Za-z0-9:-]*)/s', $tagText, $matches) === 1) {
            return [
                'kind' => 'close',
                'name' => strtolower($matches[1]),
                'self_closing' => true,
                'next' => $end + 1,
            ];
        }

        if (preg_match('/^<\s*([A-Za-z][A-Za-z0-9:-]*)\b/s', $tagText, $matches) !== 1) {
            return [
                'kind' => 'special',
                'name' => '',
                'self_closing' => true,
                'next' => $end + 1,
            ];
        }

        $tagName = strtolower($matches[1]);
        $selfClosing = preg_match('/\/\s*>$/', $tagText) === 1;
        if (!$selfClosing && in_array($tagName, self::RAW_TEXT_TAGS, true)) {
            return [
                'kind' => 'special',
                'name' => '',
                'self_closing' => true,
                'next' => self::rawTextTagEnd($html, $tagName, $end + 1),
            ];
        }

        return [
            'kind' => 'open',
            'name' => $tagName,
            'self_closing' => $selfClosing,
            'next' => $end + 1,
        ];
    }

    private static function tagEnd(string $html, int $start): ?int
    {
        $length = strlen($html);
        $quote = null;

        for ($index = $start + 1; $index < $length; ++$index) {
            $char = $html[$index];
            if ($quote !== null) {
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if ($char === '>') {
                return $index;
            }
        }

        return null;
    }

    private static function rawTextTagEnd(string $html, string $tagName, int $offset): int
    {
        $closingStart = stripos($html, '</' . $tagName, $offset);
        if ($closingStart === false) {
            return strlen($html);
        }

        $closingEnd = self::tagEnd($html, $closingStart);
        return $closingEnd === null ? strlen($html) : $closingEnd + 1;
    }

    /**
     * @param string[] $stack
     */
    private static function currentParent(array $stack): string
    {
        $key = array_key_last($stack);

        return $key === null ? '' : $stack[$key];
    }

    /**
     * @param string[] $stack
     */
    private static function popTag(array &$stack, string $tagName): void
    {
        for ($index = count($stack) - 1; $index >= 0; --$index) {
            if ($stack[$index] !== $tagName) {
                continue;
            }

            array_splice($stack, $index);
            return;
        }
    }
}
