<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Editing;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Promotes paragraph editables to wrapper elements when rich text includes
 * block children that cannot remain inside a `<p>`.
 *
 * The browser can submit valid editable fragments such as:
 * `copy<div><br></div><div>More</div>`.
 * Once block nodes appear, the original paragraph tag must be promoted to a
 * wrapper, but the submitted fragment structure should stay intact so the saved
 * HTML round-trips what the editor actually produced.
 */
final class RichTextBlockNormalizer
{
    /** @var string[] */
    private const BLOCK_TAGS = [
        'address',
        'article',
        'aside',
        'blockquote',
        'details',
        'div',
        'dl',
        'fieldset',
        'figcaption',
        'figure',
        'footer',
        'form',
        'h1',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        'header',
        'hr',
        'main',
        'nav',
        'ol',
        'p',
        'pre',
        'section',
        'table',
        'ul',
    ];

    public function replaceEditableHtml(DOMDocument $doc, DOMElement $target, string $html): bool
    {
        if (strtolower($target->tagName) !== 'p' || trim($html) === '') {
            return false;
        }

        $fragmentRoot = $this->loadFragment($html);
        if (!$this->containsBlockElement($fragmentRoot)) {
            return false;
        }

        $wrapper = $doc->createElement('div');
        $this->copyAttributes($target, $wrapper);
        $this->appendFragmentChildren($doc, $wrapper, $fragmentRoot);

        $parent = $target->parentNode;
        if (!$parent instanceof DOMNode) {
            return false;
        }

        $parent->replaceChild($wrapper, $target);

        return true;
    }

    public function containsBlockHtml(string $html): bool
    {
        if (trim($html) === '') {
            return false;
        }

        return $this->containsBlockElement($this->loadFragment($html));
    }

    private function appendFragmentChildren(DOMDocument $doc, DOMElement $wrapper, DOMElement $fragmentRoot): void
    {
        foreach ($this->childNodes($fragmentRoot) as $child) {
            $wrapper->appendChild($doc->importNode($child, true));
        }
    }

    private function containsBlockElement(DOMNode $node): bool
    {
        foreach ($this->childNodes($node) as $child) {
            if ($child instanceof DOMElement && $this->isBlockElement($child)) {
                return true;
            }

            if ($this->containsBlockElement($child)) {
                return true;
            }
        }

        return false;
    }

    private function isBlockElement(DOMElement $element): bool
    {
        return in_array(strtolower($element->tagName), self::BLOCK_TAGS, true);
    }

    private function copyAttributes(DOMElement $from, DOMElement $to): void
    {
        foreach ($from->attributes ?? [] as $attribute) {
            $to->setAttribute($attribute->nodeName, $attribute->nodeValue);
        }
    }

    /**
     * @return DOMNode[]
     */
    private function childNodes(DOMNode $node): array
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        return $children;
    }

    private function loadFragment(string $html): DOMElement
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?><div id="__upb_rich_text_fragment">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $root = $doc->getElementById('__upb_rich_text_fragment');

        return $root instanceof DOMElement ? $root : $doc->createElement('div');
    }
}
