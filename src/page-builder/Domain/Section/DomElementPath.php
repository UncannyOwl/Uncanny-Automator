<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Section;

/**
 * Builds the stable element path used by inspected binding instances.
 */
final class DomElementPath
{
    public static function fromRoot(\DOMElement $node, ?\DOMElement $root): string
    {
        $segments = [];
        $current = $node;

        while ($current instanceof \DOMElement) {
            $segments[] = strtolower($current->tagName) . '[' . self::indexWithinSiblingTag($current) . ']';

            if ($root instanceof \DOMElement && $current->isSameNode($root)) {
                break;
            }

            $parent = $current->parentNode;
            if (!$parent instanceof \DOMElement) {
                break;
            }

            $current = $parent;
        }

        return implode('/', array_reverse($segments));
    }

    private static function indexWithinSiblingTag(\DOMElement $node): int
    {
        $parent = $node->parentNode;
        if (!$parent instanceof \DOMNode) {
            return 1;
        }

        $index = 0;
        foreach ($parent->childNodes as $sibling) {
            if (!$sibling instanceof \DOMElement || strtolower($sibling->tagName) !== strtolower($node->tagName)) {
                continue;
            }

            $index++;
            if ($sibling->isSameNode($node)) {
                return $index;
            }
        }

        return 1;
    }
}
