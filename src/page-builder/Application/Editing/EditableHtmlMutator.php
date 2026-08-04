<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Editing;

use UncannyPageBuilder\Domain\Exception\EditableUpdateException;

/**
 * Applies one inline editable update to stored HTML.
 *
 * This mutator is pure application logic: it knows how Page Builder editable
 * fields patch HTML, but it does not know whether that HTML belongs to a page
 * section or to a global part source row.
 */
final class EditableHtmlMutator
{
    private const DYNAMIC_SOURCE_ATTRIBUTE = 'data-ai-dynamic';
    private readonly RichTextBlockNormalizer $richTextBlocks;

    public function __construct(?RichTextBlockNormalizer $richTextBlocks = null)
    {
        $this->richTextBlocks = $richTextBlocks ?? new RichTextBlockNormalizer();
    }

    /**
     * @param array<string, mixed> $values
     */
    public function apply(string $html, string $editableKey, string $editableType, array $values): string
    {
        $doc = $this->loadDom('<div id="__upb_editable_update_root">' . $html . '</div>');
        $root = $doc->getElementById('__upb_editable_update_root');
        if (!$root instanceof \DOMElement) {
            throw EditableUpdateException::keyNotFound($editableKey);
        }

        $target = $this->findEditable($root, $editableKey);
        $this->assertEditableIsNotBindingOwned($target, $root);
        $storedType = $target->getAttribute('data-ai-type') ?: 'text';
        if ($storedType !== $editableType) {
            throw EditableUpdateException::typeMismatch($editableKey, $storedType, $editableType);
        }

        match ($editableType) {
            'text', 'textarea' => $this->applyTextUpdate($doc, $target, $this->sanitizeHtml($this->stringValue($values, 'inner_html'))),
            'image' => $this->applyImageUpdate($target, $values),
            'link' => $this->applyLinkUpdate($doc, $target, $values),
            'bg-image' => $this->applyBackgroundImageUpdate($target, $values),
            default => throw new \InvalidArgumentException('editable_type is invalid.'),
        };

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $doc->saveHTML($child);
        }

        return trim($output);
    }

    private function assertEditableIsNotBindingOwned(\DOMElement $editable, \DOMElement $root): void
    {
        if ($this->isInsideDynamicBindingSubtree($editable, $root)) {
            throw new \InvalidArgumentException('Cannot edit content rendered by a dynamic binding. Edit the binding instead.');
        }
    }

    private function findEditable(\DOMElement $root, string $editableKey): \DOMElement
    {
        $xpath = new \DOMXPath($root->ownerDocument);
        $nodes = $xpath->query('//*[@data-ai-editable]');
        if ($nodes === false) {
            throw EditableUpdateException::keyNotFound($editableKey);
        }

        $matches = [];
        foreach ($nodes as $node) {
            if ($node instanceof \DOMElement && $node->getAttribute('data-ai-editable') === $editableKey) {
                $matches[] = $node;
            }
        }

        if ($matches === []) {
            throw EditableUpdateException::keyNotFound($editableKey);
        }
        if (count($matches) > 1) {
            throw EditableUpdateException::duplicateKey($editableKey);
        }

        return $matches[0];
    }

    /** @param array<string, mixed> $values */
    private function applyImageUpdate(\DOMElement $target, array $values): void
    {
        $image = strtolower($target->tagName) === 'img'
            ? $target
            : $this->firstDescendantByTag($target, 'img');

        if (!$image instanceof \DOMElement) {
            throw EditableUpdateException::keyNotFound($target->getAttribute('data-ai-editable'));
        }

        $image->setAttribute('src', $this->stringValue($values, 'src'));
        $image->setAttribute('alt', $this->stringValue($values, 'alt'));
    }

    /** @param array<string, mixed> $values */
    private function applyLinkUpdate(\DOMDocument $doc, \DOMElement $target, array $values): void
    {
        $link = strtolower($target->tagName) === 'a'
            ? $target
            : $this->firstDescendantByTag($target, 'a');

        if (!$link instanceof \DOMElement) {
            throw EditableUpdateException::keyNotFound($target->getAttribute('data-ai-editable'));
        }

        $link->setAttribute('href', $this->stringValue($values, 'href'));
        if (array_key_exists('target', $values)) {
            $this->setOptionalAttribute($link, 'target', $this->stringValue($values, 'target'));
        }
        if (array_key_exists('rel', $values)) {
            $this->setOptionalAttribute($link, 'rel', $this->stringValue($values, 'rel'));
        }
        if (array_key_exists('aria_label', $values)) {
            $this->setOptionalAttribute($link, 'aria-label', $this->stringValue($values, 'aria_label'));
        }
        $this->replaceInnerHtml($doc, $link, $this->sanitizeHtml($this->stringValue($values, 'link_html')));
    }

    private function applyTextUpdate(\DOMDocument $doc, \DOMElement $target, string $html): void
    {
        if ($this->richTextBlocks->replaceEditableHtml($doc, $target, $html)) {
            return;
        }

        $this->replaceInnerHtml($doc, $target, $html);
    }

    private function setOptionalAttribute(\DOMElement $target, string $name, string $value): void
    {
        if ($value === '') {
            $target->removeAttribute($name);
            return;
        }

        $target->setAttribute($name, $value);
    }

    /** @param array<string, mixed> $values */
    private function applyBackgroundImageUpdate(\DOMElement $target, array $values): void
    {
        $bgImage = $this->stringValue($values, 'bg_image');
        $style = trim($target->getAttribute('style'));
        $declarations = $style === ''
            ? []
            : array_values(array_filter(array_map('trim', explode(';', $style)), static fn(string $item): bool => $item !== ''));

        $result = [];
        $replaced = false;
        foreach ($declarations as $declaration) {
            if (str_starts_with($declaration, '--bg-image')) {
                if ($bgImage !== '') {
                    $result[] = '--bg-image: ' . $bgImage;
                }
                $replaced = true;
                continue;
            }

            $result[] = $declaration;
        }

        if (!$replaced && $bgImage !== '') {
            $result[] = '--bg-image: ' . $bgImage;
        }

        if ($result === []) {
            $target->removeAttribute('style');
            return;
        }

        $target->setAttribute('style', implode('; ', $result));
    }

    private function firstDescendantByTag(\DOMElement $target, string $tagName): ?\DOMElement
    {
        $nodes = $target->getElementsByTagName($tagName);
        $node = $nodes->item(0);

        return $node instanceof \DOMElement ? $node : null;
    }

    private function replaceInnerHtml(\DOMDocument $doc, \DOMElement $target, string $html): void
    {
        while ($target->firstChild) {
            $target->removeChild($target->firstChild);
        }

        if ($html === '') {
            return;
        }

        $fragmentDoc = $this->loadDom('<div id="__upb_fragment_root">' . $html . '</div>');
        $fragmentRoot = $fragmentDoc->getElementById('__upb_fragment_root');
        if (!$fragmentRoot instanceof \DOMElement) {
            $target->appendChild($doc->createTextNode($html));
            return;
        }

        foreach ($fragmentRoot->childNodes as $child) {
            $target->appendChild($doc->importNode($child, true));
        }
    }

    /** @param array<string, mixed> $values */
    private function stringValue(array $values, string $key): string
    {
        $value = $values[$key] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }

    private function loadDom(string $html): \DOMDocument
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $doc;
    }

    private function sanitizeHtml(string $html): string
    {
        return $html;
    }

    private function isInsideDynamicBindingSubtree(\DOMNode $node, \DOMElement $root): bool
    {
        $current = $node;

        while ($current instanceof \DOMNode) {
            if (
                $current instanceof \DOMElement
                && trim($current->getAttribute(self::DYNAMIC_SOURCE_ATTRIBUTE)) !== ''
            ) {
                return true;
            }

            if ($current === $root) {
                break;
            }

            $current = $current->parentNode;
        }

        return false;
    }
}
