<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Editing;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use UncannyPageBuilder\Domain\DesignStyles\StableSelector;
use UncannyPageBuilder\Domain\DesignStyles\StableSelectorResult;
use UncannyPageBuilder\Domain\Section\Section;

/**
 * Applies generated-node content changes to stored HTML.
 *
 * This mutator owns stable-selector resolution and safe DOM patching, but it
 * does not know whether the patched HTML belongs to a page section or to a
 * global part source row.
 */
final class SectionNodeHtmlMutator
{
    private const DYNAMIC_SOURCE_ATTRIBUTE = 'data-ai-dynamic';
    /** @var string[] */
    private const BLOCK_SAFE_HTML_HOST_TAGS = [
        'article',
        'aside',
        'blockquote',
        'dd',
        'div',
        'dt',
        'figcaption',
        'footer',
        'form',
        'header',
        'li',
        'main',
        'nav',
        'p',
        'section',
        'td',
        'th',
    ];

    /** @var string[] */
    private const ALLOWED_ATTRIBUTES = [
        'href',
        'src',
        'alt',
        'decoding',
        'title',
        'target',
        'rel',
        'loading',
        'width',
        'height',
        'aria-label',
        'placeholder',
        'value',
        'name',
        'type',
        'required',
        'disabled',
        'readonly',
    ];

    /** @var string[] */
    private const BOOLEAN_ATTRIBUTES = ['required', 'disabled', 'readonly'];

    /** @var string[] */
    private const UNSAFE_FRAGMENT_TAGS = ['script', 'style', 'template', 'iframe', 'object', 'embed'];

    private readonly RichTextBlockNormalizer $richTextBlocks;

    public function __construct(?RichTextBlockNormalizer $richTextBlocks = null)
    {
        $this->richTextBlocks = $richTextBlocks ?? new RichTextBlockNormalizer();
    }

    /**
     * @param array<string, mixed> $target
     * @param array<int, array<string, mixed>> $changes
     * @return array{html: string, selector: string, promoted: bool}
     */
    public function apply(Section $section, array $target, array $changes): array
    {
        $html = $section->content()->html();

        $sourcePath = $this->stringValue($target, 'source_path');
        $selector    = $this->nullableString($target, 'selector');
        $identity    = $this->nullableString($target, 'identity');
        $expectedTag = $this->nullableString($target, 'tag');

        $selectorResult = $this->resolveStableTarget(
            html: $html,
            selector: $selector,
            identity: $identity,
            sourcePath: $sourcePath,
            seed: $section->id() . '|' . $sourcePath,
            expectedTag: $expectedTag,
            changes: $changes,
        );

        // Resolver.
        if (!$selectorResult->isResolved() || $selectorResult->selector() === null) {
            throw new \InvalidArgumentException('Could not resolve a stable target for this node.');
        }

        $doc = $this->loadDom($selectorResult->html());
        $root = $this->firstElementChild($doc);
        if (!$root instanceof DOMElement) {
            throw new \InvalidArgumentException('Could not load section HTML.');
        }

        $targetElement = $this->findByStableSelector($doc, $selectorResult->selector());
        if (!$targetElement instanceof DOMElement) {
            throw new \InvalidArgumentException('Selected node no longer exists. Select it again.');
        }

        foreach ($changes as $change) {
            $this->applyChange($doc, $root, $targetElement, $change);
        }

        return [
            'html'     => $this->serialize($doc),
            'selector' => $selectorResult->selector(),
            'promoted' => $selectorResult->wasPromoted(),
        ];
    }

    /**
     * @param array<string, mixed> $change
     */
    private function applyChange(DOMDocument $doc, DOMElement $root, DOMElement $targetElement, array $change): void
    {
        $kind = $this->stringValue($change, 'kind');
        $name = $this->stringValue($change, 'name');
        $value = $this->stringValue($change, 'value');
        $path = $this->stringValue($change, 'path');

        if ($kind === 'content' && $name === 'text') {
            $node = $path !== '' ? $this->locateByPath($doc, $path) : $targetElement;
            if (!$node instanceof DOMNode || !$this->nodeIsInsideTarget($node, $targetElement)) {
                throw new \InvalidArgumentException('Selected content no longer exists. Select it again.');
            }
            $this->assertNodeIsNotBindingOwned($node, $root);
            $this->replaceText($doc, $node, $value);
            return;
        }

        if ($kind === 'content' && $name === 'safe_html') {
            $node = $path !== '' ? $this->locateByPath($doc, $path) : $targetElement;
            if (
                (!$node instanceof DOMElement || !$this->nodeIsInsideTarget($node, $targetElement))
                && $targetElement instanceof DOMElement
            ) {
                $node = $targetElement;
            }
            if (!$node instanceof DOMElement || !$this->nodeIsInsideTarget($node, $targetElement)) {
                throw new \InvalidArgumentException('Selected content no longer exists. Select it again.');
            }
            $this->assertNodeIsNotBindingOwned($node, $root);
            $this->replaceWithSafeInlineHtml($doc, $node, $value);
            return;
        }

        if ($kind === 'style' && $name === 'text-align') {
            $this->assertNodeIsNotBindingOwned($targetElement, $root);
            $this->setTextAlignment($targetElement, $value);
            return;
        }

        if ($kind === 'attribute' && in_array($name, self::ALLOWED_ATTRIBUTES, true)) {
            $node = $path !== '' ? $this->locateByPath($doc, $path) : $targetElement;
            if (!$node instanceof DOMElement || !$this->nodeIsInsideTarget($node, $targetElement)) {
                throw new \InvalidArgumentException('Selected attribute no longer exists. Select it again.');
            }
            $this->assertNodeIsNotBindingOwned($node, $root);
            $this->setOptionalAttribute($node, $name, $value);
            return;
        }

        if ($kind === 'shortcode' && $name === 'source') {
            $previousValue = $this->stringValue($change, 'previous_value');
            $occurrence = $this->intValue($change, 'occurrence', -1);
            $this->replaceShortcodeSource($targetElement, $previousValue, $value, $occurrence);
            return;
        }

        if ($kind === 'remove' && $name === 'node') {
            $this->removeElement($root, $targetElement);
            return;
        }

        throw new \InvalidArgumentException('Node change is not supported.');
    }

    private function replaceText(DOMDocument $doc, DOMNode $node, string $value): void
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            $node->nodeValue = $value;
            return;
        }

        if (!$node instanceof DOMElement) {
            throw new \InvalidArgumentException('Selected content no longer exists. Select it again.');
        }

        while ($node->firstChild) {
            $node->removeChild($node->firstChild);
        }
        if ($value !== '') {
            $node->appendChild($doc->createTextNode($value));
        }
    }

    private function replaceShortcodeSource(
        DOMElement $targetElement,
        string $previousValue,
        string $nextValue,
        int $occurrence,
    ): void {
        if (trim($previousValue) === '' || !str_contains($previousValue, '[')) {
            throw new \InvalidArgumentException('Selected shortcode source no longer exists. Select it again.');
        }
        if (trim($nextValue) === '' || !str_contains($nextValue, '[') || str_contains($nextValue, '<')) {
            throw new \InvalidArgumentException('Shortcode source is not valid.');
        }

        $seen = 0;
        foreach ($this->textNodesInside($targetElement) as $node) {
            $text = $node->nodeValue ?? '';
            $offset = 0;

            while (($position = strpos($text, $previousValue, $offset)) !== false) {
                if ($occurrence < 0 || $seen === $occurrence) {
                    $node->nodeValue = substr($text, 0, $position)
                        . $nextValue
                        . substr($text, $position + strlen($previousValue));
                    return;
                }

                ++$seen;
                $offset = $position + strlen($previousValue);
            }
        }

        throw new \InvalidArgumentException('Selected shortcode source no longer exists. Select it again.');
    }

    /**
     * @return \Generator<int, \DOMText>
     */
    private function textNodesInside(DOMElement $targetElement): \Generator
    {
        if ($targetElement->ownerDocument instanceof DOMDocument) {
            $xpath = new DOMXPath($targetElement->ownerDocument);
            foreach ($xpath->query('.//text()[contains(., "[")]', $targetElement) as $node) {
                if ($node instanceof \DOMText) {
                    yield $node;
                }
            }
        }
    }

    private function replaceWithSafeInlineHtml(DOMDocument $doc, DOMElement $node, string $html): void
    {
        $fragment = $this->loadInlineFragment($html);
        $container = $fragment->getElementById('__upb_inline_fragment');
        if (!$container instanceof DOMElement) {
            throw new \InvalidArgumentException('Safe HTML could not be parsed.');
        }

        $this->assertSafeHtmlChildren($container);

        $safeHtml = $this->serializeChildren($container);
        if ($this->richTextBlocks->replaceEditableHtml($doc, $node, $safeHtml)) {
            return;
        }

        while ($node->firstChild) {
            $node->removeChild($node->firstChild);
        }

        foreach (iterator_to_array($container->childNodes) as $child) {
            if (!$child instanceof DOMNode) {
                continue;
            }
            $node->appendChild($doc->importNode($child, true));
        }
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

    /**
     * Repairable rich text can round-trip through a different DOM shape in PHP
     * than it did in the browser. For safe_html writes without a stable
     * selector or identity, retry the nearest matching ancestor path before we
     * give up on the target entirely.
     *
     * @param array<int, array<string, mixed>> $changes
     */
    private function resolveStableTarget(
        string $html,
        ?string $selector,
        ?string $identity,
        string $sourcePath,
        string $seed,
        ?string $expectedTag,
        array $changes,
    ): StableSelectorResult {
        $resolved = StableSelector::resolve(
            html: $html,
            selector: $selector,
            identity: $identity,
            sourcePath: $sourcePath,
            seed: $seed,
            expectedTag: $expectedTag,
        );

        if ($resolved->isResolved() || !$this->shouldRetryRepairableSafeHtmlTarget($selector, $identity, $sourcePath, $expectedTag, $changes)) {
            return $resolved;
        }

        $requiresBlockSafeHtmlHost = $this->safeHtmlChangesContainBlockMarkup($changes);

        foreach ($this->ancestorSourcePaths($sourcePath) as $candidatePath) {
            $candidate = StableSelector::resolve(
                html: $html,
                selector: $selector,
                identity: $identity,
                sourcePath: $candidatePath,
                seed: $seed,
                expectedTag: $expectedTag,
            );

            if ($candidate->isResolved()) {
                return $candidate;
            }

            $candidate = StableSelector::resolve(
                html: $html,
                selector: $selector,
                identity: $identity,
                sourcePath: $candidatePath,
                seed: $seed,
                expectedTag: null,
            );

            if ($candidate->isResolved() && $this->canAcceptRecoveredSafeHtmlTarget($candidate, $requiresBlockSafeHtmlHost)) {
                return $candidate;
            }
        }

        return $resolved;
    }

    /**
     * @param array<int, array<string, mixed>> $changes
     */
    private function shouldRetryRepairableSafeHtmlTarget(
        ?string $selector,
        ?string $identity,
        string $sourcePath,
        ?string $expectedTag,
        array $changes,
    ): bool {
        if ($sourcePath === '' || $expectedTag === '' || $selector !== null || $identity !== null) {
            return false;
        }

        foreach ($changes as $change) {
            if (
                $this->stringValue($change, 'kind') === 'content'
                && $this->stringValue($change, 'name') === 'safe_html'
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function ancestorSourcePaths(string $sourcePath): array
    {
        $candidates = [];
        $current = $sourcePath;

        while (str_contains($current, '.')) {
            $current = substr($current, 0, (int) strrpos($current, '.'));
            if ($current !== '') {
                $candidates[] = $current;
            }
        }

        return $candidates;
    }

    /**
     * @param array<int, array<string, mixed>> $changes
     */
    private function safeHtmlChangesContainBlockMarkup(array $changes): bool
    {
        foreach ($changes as $change) {
            if (
                $this->stringValue($change, 'kind') !== 'content'
                || $this->stringValue($change, 'name') !== 'safe_html'
            ) {
                continue;
            }

            if ($this->richTextBlocks->containsBlockHtml($this->stringValue($change, 'value'))) {
                return true;
            }
        }

        return false;
    }

    private function canAcceptRecoveredSafeHtmlTarget(
        StableSelectorResult $candidate,
        bool $requiresBlockSafeHtmlHost,
    ): bool {
        if (!$requiresBlockSafeHtmlHost) {
            return true;
        }

        $resolvedTag = $this->resolvedSelectorTag($candidate);

        return $resolvedTag !== null && in_array($resolvedTag, self::BLOCK_SAFE_HTML_HOST_TAGS, true);
    }

    private function resolvedSelectorTag(StableSelectorResult $candidate): ?string
    {
        $selector = $candidate->selector();
        if ($selector === null) {
            return null;
        }

        $doc = $this->loadDom($candidate->html());
        $root = $this->firstElementChild($doc);
        if (!$root instanceof DOMElement) {
            return null;
        }

        $element = $this->findByStableSelector($doc, $selector);

        return $element instanceof DOMElement ? strtolower($element->tagName) : null;
    }

    private function assertSafeHtmlChildren(DOMNode $node): void
    {
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                continue;
            }
            if (!$child instanceof DOMElement) {
                throw new \InvalidArgumentException('Safe HTML supports text and HTML elements only.');
            }

            $this->assertSafeElementTree($child);
        }
    }

    private function isUnsafeHref(string $href): bool
    {
        $trimmed = strtolower(trim($href));
        if ($trimmed === '' || str_starts_with($trimmed, '#') || str_starts_with($trimmed, '/')) {
            return false;
        }

        return !preg_match('/^(https?:|mailto:|tel:)/', $trimmed);
    }

    private function setOptionalAttribute(DOMElement $node, string $name, string $value): void
    {
        if (in_array($name, self::BOOLEAN_ATTRIBUTES, true)) {
            $enabled = !in_array(strtolower(trim($value)), ['', '0', 'false', 'off', 'no'], true);
            if (!$enabled) {
                $node->removeAttribute($name);
                return;
            }

            $node->setAttribute($name, $name);
            return;
        }

        if ($value === '') {
            $node->removeAttribute($name);
            return;
        }

        $node->setAttribute($name, $value);
    }

    /**
     * Floating rich-text alignment is the only host style accepted by this
     * content command. Patch that declaration in place so unrelated inline
     * source, including custom properties, remains untouched.
     */
    private function setTextAlignment(DOMElement $node, string $value): void
    {
        $value = strtolower(trim($value));
        if (!in_array($value, ['', 'left', 'center', 'right', 'justify', 'start', 'end'], true)) {
            throw new \InvalidArgumentException('Text alignment is not valid.');
        }

        $style = $node->getAttribute('style');
        $ranges = $this->inlineStyleDeclarationRanges($style, 'text-align');
        $patched = $style;

        if ($ranges !== []) {
            $range = $ranges[array_key_last($ranges)];
            $declaration = substr($style, $range['start'], $range['length']);
            if ($value === '') {
                $patched = substr_replace($style, '', $range['start'], $range['length']);
            } elseif (preg_match('/^(\s*text-align\s*:)(.*?)(\s*)$/is', $declaration, $matches) === 1) {
                $replacement = $matches[1] . ' ' . $value . $matches[3];
                $patched = substr_replace($style, $replacement, $range['start'], $range['length']);
            } else {
                throw new \InvalidArgumentException('Text alignment could not be saved.');
            }
        } elseif ($value !== '') {
            $separator = trim($patched) === '' || str_ends_with(rtrim($patched), ';') ? '' : ';';
            $patched = rtrim($patched) . $separator . ($patched === '' ? '' : ' ') . 'text-align: ' . $value . ';';
        }

        if (trim($patched, "; \t\n\r\0\x0B") === '') {
            $node->removeAttribute('style');
            return;
        }

        $node->setAttribute('style', $patched);
    }

    /**
     * Locate complete inline declarations without treating semicolons inside
     * strings, comments, or functions as declaration boundaries.
     *
     * @return list<array{start: int, length: int}>
     */
    private function inlineStyleDeclarationRanges(string $style, string $property): array
    {
        $ranges = [];
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
            if ($character === ')' && $parentheses > 0) {
                $parentheses--;
                continue;
            }
            if ($character !== ';' || $parentheses > 0) {
                continue;
            }

            $declarationLength = $index - $start;
            $declaration = substr($style, $start, $declarationLength);
            if (preg_match('/^\s*' . preg_quote($property, '/') . '\s*:/i', $declaration) === 1) {
                $ranges[] = ['start' => $start, 'length' => $declarationLength];
            }
            $start = $index + 1;
        }

        return $ranges;
    }

    private function removeElement(DOMElement $root, DOMElement $targetElement): void
    {
        if ($targetElement === $root) {
            throw new \InvalidArgumentException('Use the section delete action to delete a section.');
        }
        if ($this->isDynamicBindingRoot($targetElement)) {
            throw new \InvalidArgumentException('Cannot delete a dynamic binding root.');
        }
        $this->assertNodeIsNotBindingOwned($targetElement, $root);

        $parent = $targetElement->parentNode;
        if (!$parent instanceof DOMNode) {
            throw new \InvalidArgumentException('Selected node no longer exists. Select it again.');
        }

        $parent->removeChild($targetElement);
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

    private function serializeChildren(DOMNode $node): string
    {
        $document = $node->ownerDocument;
        if (!$document instanceof DOMDocument) {
            return '';
        }

        $html = '';
        foreach ($node->childNodes as $child) {
            $serialized = $document->saveHTML($child);
            if (is_string($serialized)) {
                $html .= $serialized;
            }
        }

        return $html;
    }

    private function isDynamicBindingRoot(DOMElement $element): bool
    {
        return trim($element->getAttribute(self::DYNAMIC_SOURCE_ATTRIBUTE)) !== '';
    }

    private function assertNodeIsNotBindingOwned(DOMNode $node, DOMElement $root): void
    {
        if ($this->isInsideDynamicBindingSubtree($node, $root)) {
            throw new \InvalidArgumentException('Cannot edit content rendered by a dynamic binding. Edit the binding instead.');
        }
    }

    private function isInsideDynamicBindingSubtree(DOMNode $node, DOMElement $root): bool
    {
        $current = $node;

        while ($current instanceof DOMNode) {
            if (
                $current instanceof DOMElement
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

    private function findByStableSelector(DOMNode $root, string $selector): ?DOMElement
    {
        if (preg_match('/^#([A-Za-z][\w-]*)$/', $selector, $matches) === 1) {
            return $this->singleElementByAttribute($root, 'id', $matches[1]);
        }

        if (preg_match('/^\[data-upb-lens-id="([A-Za-z0-9_-]+)"\]$/', $selector, $matches) === 1) {
            return $this->singleElementByAttribute($root, StableSelector::PROMOTED_ATTRIBUTE, $matches[1]);
        }

        return null;
    }

    private function singleElementByAttribute(DOMNode $root, string $attribute, string $value): ?DOMElement
    {
        $document = $root instanceof DOMDocument ? $root : $root->ownerDocument;
        if (!$document instanceof DOMDocument) {
            return null;
        }

        $xpath = new DOMXPath($document);
        $literal = $this->xpathLiteral($value);
        $expression = $root instanceof DOMElement
            ? 'self::*[@' . $attribute . ' = ' . $literal . '] | .//*[@' . $attribute . ' = ' . $literal . ']'
            : './/*[@' . $attribute . ' = ' . $literal . ']';
        $nodes = $xpath->query($expression, $root);
        if ($nodes === false || $nodes->length !== 1) {
            return null;
        }

        $node = $nodes->item(0);
        return $node instanceof DOMElement ? $node : null;
    }

    private function nodeIsInsideTarget(DOMNode $node, DOMElement $target): bool
    {
        return $node === $target || $this->nodeContains($target, $node);
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

    private function locateByPath(DOMDocument $doc, string $path): ?DOMNode
    {
        $segments = explode('.', trim($path));
        $rootSegment = array_shift($segments);
        if ($rootSegment === null || $rootSegment === '' || ctype_digit($rootSegment) === false) {
            return null;
        }

        $roots = [];
        foreach ($doc->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $roots[] = $child;
            }
        }
        $current = $roots[(int) $rootSegment] ?? null;
        if (!$current instanceof DOMElement) {
            return null;
        }

        foreach ($segments as $segment) {
            if ($segment === '' || ctype_digit($segment) === false) {
                return null;
            }

            $children = $this->treeChildren($current);
            $index = (int) $segment;
            if (!isset($children[$index])) {
                return null;
            }

            $current = $children[$index];
        }

        return $current;
    }

    /** @return array<int, DOMNode> */
    private function treeChildren(DOMNode $node): array
    {
        $out = [];
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $out[] = $child;
                continue;
            }
            if ($child->nodeType === XML_TEXT_NODE && trim($child->textContent ?? '') !== '') {
                $out[] = $child;
            }
        }

        return $out;
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

    /** @param array<string, mixed> $values */
    private function stringValue(array $values, string $key): string
    {
        $value = $values[$key] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }

    /** @param array<string, mixed> $values */
    private function intValue(array $values, string $key, int $default): int
    {
        $value = $values[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }

    /** @param array<string, mixed> $values */
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
            static fn(string $part): string => "'" . $part . "'",
            explode("'", $value),
        );

        return 'concat(' . implode(', "\'", ', $parts) . ')';
    }
}
