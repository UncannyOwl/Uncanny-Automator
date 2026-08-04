<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Editing;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use UncannyPageBuilder\Application\SectionService;
use UncannyPageBuilder\Domain\DesignStyles\StableSelector;
use UncannyPageBuilder\Domain\Section\Section;
use UncannyPageBuilder\Domain\Section\SectionCollection;

/**
 * Updates generated section HTML by selected node identity.
 *
 * This is the non-legacy Design Lens save path. Legacy `data-ai-editable`
 * updates stay in EditableUpdateService; arbitrary generated markup reaches this
 * service only after Page Builder has a section target and a previewed change.
 */
final class SectionNodeUpdateService
{
    private const DYNAMIC_SOURCE_ATTRIBUTE = 'data-ai-dynamic';
    /** @var string[] */
    private const UNSAFE_FRAGMENT_TAGS = ['script', 'style', 'template', 'iframe', 'object', 'embed'];
    /** @var string[] */
    private const STRUCTURAL_PLACEMENTS = ['before', 'after', 'prepend', 'append'];
    private readonly SectionNodeHtmlMutator $mutator;

    public function __construct(
        private readonly SectionService $sections,
        ?SectionNodeHtmlMutator $mutator = null,
    ) {
        $this->mutator = $mutator ?? new SectionNodeHtmlMutator();
    }

    /**
     * @param array<string, mixed> $target
     * @param array<int, array<string, mixed>> $changes
     * @return array<string, mixed>
     */
    public function update(
        int $pageId,
        int $sectionId,
        array $target,
        array $changes,
        ?SectionCollection $loadedSections = null,
    ): array {
        if ($pageId <= 0) {
            throw new \InvalidArgumentException('page_id is required.');
        }
        if ($sectionId <= 0) {
            throw new \InvalidArgumentException('section_id is required.');
        }
        if ($changes === []) {
            throw new \InvalidArgumentException('At least one node change is required.');
        }

        [$loadedSections, $section] = $this->loadSectionForWrite($pageId, $sectionId, $loadedSections);

        $oldHtml = $section->content()->html();
        $oldCss = $section->content()->css();

        $result = $this->mutator->apply($section, $target, $changes);
        $saved = $this->saveLoadedHtml($pageId, $loadedSections, $section, $result['html']);

        return [
            'section_id'       => $sectionId,
            'page_id'          => $pageId,
            'selector'         => $result['selector'],
            'promoted'         => $result['promoted'],
            'old_html'         => $oldHtml,
            'new_html'         => $saved->content()->html(),
            'old_css'          => $oldCss,
            'new_css'          => $saved->content()->css(),
        ];
    }

    /**
     * @param array<string, mixed> $target
     * @return array<string, mixed>
     */
    public function deleteElement(
        int $pageId,
        int $sectionId,
        array $target,
        ?SectionCollection $loadedSections = null,
    ): array {
        return $this->update(
            $pageId,
            $sectionId,
            $target,
            [['kind' => 'remove', 'name' => 'node', 'value' => '']],
            $loadedSections,
        );
    }

    /**
     * @param array<string, mixed> $target
     * @return array<string, mixed>
     */
    public function insertElement(
        int $pageId,
        int $sectionId,
        array $target,
        string $placement,
        string $html,
        ?SectionCollection $loadedSections = null,
    ): array {
        $placement = $this->normalizePlacement($placement);
        if (trim($html) === '') {
            throw new \InvalidArgumentException('html is required.');
        }

        [$loadedSections, $section] = $this->loadSectionForWrite($pageId, $sectionId, $loadedSections);
        $oldHtml = $section->content()->html();
        $oldCss = $section->content()->css();
        $resolved = $this->resolveTargetHtml($section, $target, $oldHtml);

        $doc = $this->loadDom($resolved['html']);
        $root = $this->firstElementChild($doc);
        if (!$root instanceof DOMElement) {
            throw new \InvalidArgumentException('Could not load section HTML.');
        }

        $targetElement = $this->findByStableSelector($root, $resolved['selector']);
        if (!$targetElement instanceof DOMElement) {
            throw new \InvalidArgumentException('Selected node no longer exists. Select it again.');
        }
        $this->assertCanPlaceRelativeToBinding(
            $targetElement,
            $root,
            $placement,
            'Cannot insert into content rendered by a dynamic binding. Edit the binding instead.',
        );

        if (($placement === 'before' || $placement === 'after') && $targetElement === $root) {
            throw new \InvalidArgumentException('Cannot insert before or after the section root.');
        }

        $inserted = $this->loadSingleSafeElementFragment($doc, $html);
        $this->placeElement($targetElement, $inserted, $placement);

        $saved = $this->saveLoadedHtml($pageId, $loadedSections, $section, $this->serialize($doc));

        return [
            'section_id'       => $sectionId,
            'page_id'          => $pageId,
            'selector'         => $resolved['selector'],
            'promoted'         => $resolved['promoted'],
            'old_html'         => $oldHtml,
            'new_html'         => $saved->content()->html(),
            'old_css'          => $oldCss,
            'new_css'          => $saved->content()->css(),
        ];
    }

    /**
     * @param array<string, mixed> $source
     * @param array<string, mixed> $destination
     * @return array<string, mixed>
     */
    public function moveElement(
        int $pageId,
        int $sectionId,
        array $source,
        array $destination,
        string $placement,
        ?SectionCollection $loadedSections = null,
    ): array {
        $placement = $this->normalizePlacement($placement);
        [$loadedSections, $section] = $this->loadSectionForWrite($pageId, $sectionId, $loadedSections);
        $oldHtml = $section->content()->html();
        $oldCss = $section->content()->css();

        $sourceResolved = $this->resolveTargetHtml($section, $source, $oldHtml);
        $destinationResolved = $this->resolveTargetHtml($section, $destination, $sourceResolved['html']);

        $doc = $this->loadDom($destinationResolved['html']);
        $root = $this->firstElementChild($doc);
        if (!$root instanceof DOMElement) {
            throw new \InvalidArgumentException('Could not load section HTML.');
        }

        $sourceElement = $this->findByStableSelector($root, $sourceResolved['selector']);
        $destinationElement = $this->findByStableSelector($root, $destinationResolved['selector']);
        if (!$sourceElement instanceof DOMElement || !$destinationElement instanceof DOMElement) {
            throw new \InvalidArgumentException('Selected node no longer exists. Select it again.');
        }
        if ($sourceElement === $root) {
            throw new \InvalidArgumentException('Cannot move the section root.');
        }
        if ($this->isDynamicBindingRoot($sourceElement)) {
            throw new \InvalidArgumentException('Cannot move a dynamic binding root.');
        }
        $this->assertNodeIsNotBindingOwned(
            $sourceElement,
            $root,
            'Cannot move content rendered by a dynamic binding. Edit the binding instead.',
        );
        $this->assertCanPlaceRelativeToBinding(
            $destinationElement,
            $root,
            $placement,
            'Cannot move content into or around content rendered by a dynamic binding. Edit the binding instead.',
        );
        if ($sourceElement === $destinationElement || $this->nodeContains($sourceElement, $destinationElement)) {
            throw new \InvalidArgumentException('Cannot move a node into itself or its descendant.');
        }
        if (($placement === 'before' || $placement === 'after') && $destinationElement === $root) {
            throw new \InvalidArgumentException('Cannot move an element before or after the section root.');
        }

        $sourceParent = $sourceElement->parentNode;
        if (!$sourceParent instanceof DOMNode) {
            throw new \InvalidArgumentException('Selected node no longer exists. Select it again.');
        }

        $moved = $sourceParent->removeChild($sourceElement);
        $this->placeElement($destinationElement, $moved, $placement);

        $saved = $this->saveLoadedHtml($pageId, $loadedSections, $section, $this->serialize($doc));

        return [
            'section_id'       => $sectionId,
            'page_id'          => $pageId,
            'source_selector'  => $sourceResolved['selector'],
            'destination_selector' => $destinationResolved['selector'],
            'promoted'         => $sourceResolved['promoted'] || $destinationResolved['promoted'],
            'old_html'         => $oldHtml,
            'new_html'         => $saved->content()->html(),
            'old_css'          => $oldCss,
            'new_css'          => $saved->content()->css(),
        ];
    }

    private function loadInlineFragment(string $html): DOMDocument
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><div id="__upb_inline_fragment">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $this->removeProcessingInstructions($doc);

        return $doc;
    }

    private function isUnsafeHref(string $href): bool
    {
        $trimmed = strtolower(trim($href));
        if ($trimmed === '' || str_starts_with($trimmed, '#') || str_starts_with($trimmed, '/')) {
            return false;
        }

        return !preg_match('/^(https?:|mailto:|tel:)/', $trimmed);
    }

    /** @return array{SectionCollection, Section} */
    private function loadSectionForWrite(
        int $pageId,
        int $sectionId,
        ?SectionCollection $loadedSections = null,
    ): array {
        if ($pageId <= 0) {
            throw new \InvalidArgumentException('page_id is required.');
        }
        if ($sectionId <= 0) {
            throw new \InvalidArgumentException('section_id is required.');
        }

        $loadedSections ??= $this->sections->loadSections($pageId);
        $section = $loadedSections->getById($sectionId);
        if ($section->pageId() !== $pageId) {
            throw new \InvalidArgumentException('The loaded section does not belong to page_id.');
        }

        return [$loadedSections, $section];
    }

    private function saveLoadedHtml(
        int $pageId,
        SectionCollection $loadedSections,
        Section $section,
        string $html,
    ): Section {
        $sectionId = $section->id() ?? 0;
        $this->sections->replaceLoadedSectionSource(
            pageId: $pageId,
            sections: $loadedSections,
            sectionId: $sectionId,
            sectionName: $section->name(),
            content: [
                'html' => $html,
                'css' => $section->content()->css(),
                'element_styles' => $section->content()->elementStyles()->toArray(),
            ],
            normalizePatchedHtml: true,
        );

        return $loadedSections->getById($sectionId);
    }

    /**
     * @param array<string, mixed> $target
     * @return array{html: string, selector: string, promoted: bool}
     */
    private function resolveTargetHtml(Section $section, array $target, string $html): array
    {
        $sourcePath = $this->stringValue($target, 'source_path');
        $selectorResult = StableSelector::resolve(
            html: $html,
            selector: $this->nullableString($target, 'selector'),
            identity: $this->nullableString($target, 'identity'),
            sourcePath: $sourcePath,
            seed: $section->id() . '|' . $sourcePath,
            expectedTag: $this->nullableString($target, 'tag'),
        );

        if (!$selectorResult->isResolved() || $selectorResult->selector() === null) {
            throw new \InvalidArgumentException('Could not resolve a stable target for this node.');
        }

        return [
            'html' => $selectorResult->html(),
            'selector' => $selectorResult->selector(),
            'promoted' => $selectorResult->wasPromoted(),
        ];
    }

    private function normalizePlacement(string $placement): string
    {
        $placement = strtolower(trim($placement));
        if (!in_array($placement, self::STRUCTURAL_PLACEMENTS, true)) {
            throw new \InvalidArgumentException('Placement must be before, after, prepend, or append.');
        }

        return $placement;
    }

    private function placeElement(DOMElement $target, DOMNode $element, string $placement): void
    {
        if ($placement === 'prepend') {
            $target->insertBefore($element, $target->firstChild);
            return;
        }
        if ($placement === 'append') {
            $target->appendChild($element);
            return;
        }

        $parent = $target->parentNode;
        if (!$parent instanceof DOMNode) {
            throw new \InvalidArgumentException('Selected node no longer exists. Select it again.');
        }

        if ($placement === 'before') {
            $parent->insertBefore($element, $target);
            return;
        }

        $parent->insertBefore($element, $target->nextSibling);
    }

    private function loadSingleSafeElementFragment(DOMDocument $targetDoc, string $html): DOMElement
    {
        $fragmentDoc = $this->loadInlineFragment($html);
        $container = $fragmentDoc->getElementById('__upb_inline_fragment');
        if (!$container instanceof DOMElement) {
            throw new \InvalidArgumentException('HTML fragment could not be parsed.');
        }

        $elementChildren = [];
        foreach ($container->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE && trim($child->textContent ?? '') === '') {
                continue;
            }
            if (!$child instanceof DOMElement) {
                throw new \InvalidArgumentException('HTML fragment must contain one root element.');
            }
            $elementChildren[] = $child;
        }

        if (count($elementChildren) !== 1) {
            throw new \InvalidArgumentException('HTML fragment must contain one root element.');
        }

        $this->assertSafeElementTree($elementChildren[0]);
        $imported = $targetDoc->importNode($elementChildren[0], true);
        if (!$imported instanceof DOMElement) {
            throw new \InvalidArgumentException('HTML fragment could not be imported.');
        }

        return $imported;
    }

    private function assertSafeElementTree(DOMElement $element): void
    {
        $tag = strtolower($element->tagName);
        if (in_array($tag, self::UNSAFE_FRAGMENT_TAGS, true)) {
            throw new \InvalidArgumentException('HTML fragment contains an unsafe tag.');
        }

        foreach ($element->attributes ?? [] as $attribute) {
            $name = strtolower($attribute->name);
            if (str_starts_with($name, 'on')) {
                throw new \InvalidArgumentException('HTML fragment contains an unsafe event attribute.');
            }
            if (in_array($name, ['href', 'src'], true) && $this->isUnsafeHref($attribute->value)) {
                throw new \InvalidArgumentException('HTML fragment contains an unsafe URL.');
            }
        }

        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $this->assertSafeElementTree($child);
            }
        }
    }

    private function isDynamicBindingRoot(DOMElement $element): bool
    {
        return trim($element->getAttribute(self::DYNAMIC_SOURCE_ATTRIBUTE)) !== '';
    }

    private function assertNodeIsNotBindingOwned(DOMNode $node, DOMElement $root, string $message): void
    {
        if ($this->isInsideDynamicBindingSubtree($node, $root)) {
            throw new \InvalidArgumentException($message);
        }
    }

    private function assertCanPlaceRelativeToBinding(DOMElement $target, DOMElement $root, string $placement, string $message): void
    {
        if ($this->isInsideDynamicBindingSubtree($target, $root)) {
            throw new \InvalidArgumentException($message);
        }

        if (in_array($placement, ['prepend', 'append'], true) && $this->isDynamicBindingRoot($target)) {
            throw new \InvalidArgumentException($message);
        }
    }

    private function isInsideDynamicBindingSubtree(DOMNode $node, DOMElement $root): bool
    {
        $current = $node;

        while ($current instanceof DOMNode) {
            if ($current instanceof DOMElement && $this->isDynamicBindingRoot($current)) {
                return $current !== $node;
            }

            if ($current === $root) {
                break;
            }

            $current = $current->parentNode;
        }

        return false;
    }

    private function findByStableSelector(DOMElement $root, string $selector): ?DOMElement
    {
        if (preg_match('/^#([A-Za-z][\w-]*)$/', $selector, $matches) === 1) {
            return $this->singleElementByAttribute($root, 'id', $matches[1]);
        }

        if (preg_match('/^\[data-upb-lens-id="([A-Za-z0-9_-]+)"\]$/', $selector, $matches) === 1) {
            return $this->singleElementByAttribute($root, StableSelector::PROMOTED_ATTRIBUTE, $matches[1]);
        }

        return null;
    }

    private function singleElementByAttribute(DOMElement $root, string $attribute, string $value): ?DOMElement
    {
        $xpath = new DOMXPath($root->ownerDocument);
        $literal = $this->xpathLiteral($value);
        $nodes = $xpath->query('self::*[@' . $attribute . ' = ' . $literal . '] | .//*[@' . $attribute . ' = ' . $literal . ']', $root);
        if ($nodes === false || $nodes->length !== 1) {
            return null;
        }

        $node = $nodes->item(0);
        return $node instanceof DOMElement ? $node : null;
    }

    private function nodeContains(DOMElement $parent, DOMNode $child): bool
    {
        $current = $child->parentNode;
        while ($current instanceof DOMNode) {
            if ($current === $parent) {
                return true;
            }
            $current = $current->parentNode;
        }

        return false;
    }

    private function loadDom(string $html): DOMDocument
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $this->removeProcessingInstructions($doc);

        return $doc;
    }

    private function removeProcessingInstructions(DOMDocument $doc): void
    {
        $remove = [];
        foreach ($doc->childNodes as $child) {
            if ($child->nodeType === XML_PI_NODE) {
                $remove[] = $child;
            }
        }
        foreach ($remove as $node) {
            $doc->removeChild($node);
        }
    }

    private function firstElementChild(DOMDocument $doc): ?DOMElement
    {
        foreach ($doc->childNodes as $child) {
            if ($child instanceof DOMElement) {
                return $child;
            }
        }

        return null;
    }

    private function serialize(DOMDocument $doc): string
    {
        $html = '';
        foreach ($doc->childNodes as $child) {
            $serialized = $doc->saveHTML($child);
            if (is_string($serialized)) {
                $html .= $serialized;
            }
        }

        return trim($html);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function stringValue(array $values, string $key): string
    {
        $value = $values[$key] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @param array<string, mixed> $values
     */
    private function nullableString(array $values, string $key): ?string
    {
        $value = $this->stringValue($values, $key);

        return $value === '' ? null : $value;
    }

    private function xpathLiteral(string $value): string
    {
        if (!str_contains($value, "'")) {
            return "'" . $value . "'";
        }
        if (!str_contains($value, '"')) {
            return '"' . $value . '"';
        }

        $parts = array_map(
            static fn (string $part): string => "'" . $part . "'",
            explode("'", $value),
        );

        return 'concat(' . implode(', "\'", ', $parts) . ')';
    }
}
