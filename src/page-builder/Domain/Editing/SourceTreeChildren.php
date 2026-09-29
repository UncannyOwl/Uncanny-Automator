<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Editing;

use DOMNode;

/** Counts source children with the same whitespace rules as Design Lens. */
final class SourceTreeChildren
{
    // Design Lens removes JavaScript whitespace before it counts a text node.
    // PHP trim() keeps non-breaking spaces. PHP Unicode \s also includes
    // characters that JavaScript keeps, such as U+0085. Use the exact set so
    // the same position cannot identify different elements in PHP and JS.
    private const WHITESPACE_ONLY_PATTERN = '/\A[\x{0009}-\x{000D}\x{0020}\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}]*\z/u';

    /** @return list<DOMNode> */
    public static function of(DOMNode $parent): array
    {
        $children = [];
        foreach ($parent->childNodes as $child) {
            // Elements and non-whitespace text each occupy one position.
            // Comments and whitespace remain in the source but take no slot.
            if (
                $child->nodeType === XML_ELEMENT_NODE
                || ($child->nodeType === XML_TEXT_NODE
                    && preg_match(self::WHITESPACE_ONLY_PATTERN, $child->textContent ?? '') !== 1)
            ) {
                $children[] = $child;
            }
        }

        return $children;
    }
}
