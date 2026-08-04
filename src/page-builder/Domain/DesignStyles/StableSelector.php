<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\DesignStyles;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Resolves a stable CSS selector for an element, promoting one when needed.
 *
 * Element CSS must never be persisted against a positional source path like
 * "0.3.1" (see the plan's stop conditions). This helper finds an existing stable
 * handle on the located element, or promotes one by injecting a generated
 * `id="upb-el-..."` into the section HTML — onto the element the source path
 * locates, NOT blindly onto the section root.
 *
 * `sourcePath` is a SECTION-RELATIVE locator (dot path of tree-child indices from
 * the section root, mirroring the Design Lens SDK's `treeChildrenOf` counting of
 * element + non-empty text nodes). It is used only to find the element during
 * promotion — it is never persisted as identity. If the path cannot be resolved,
 * the result is unresolved and the caller rejects the commit rather than writing
 * CSS against the wrong node.
 */
final class StableSelector
{
    public const PROMOTED_ATTRIBUTE = 'data-upb-lens-id';

    /** Valid `#id` selector body: starts with a letter, word chars/dashes after. */
    private const ID_PATTERN = '/^[A-Za-z][\w-]*$/';

    /**
     * @param string      $html        Section HTML.
     * @param string|null $selector    Caller-provided selector, if any.
     * @param string|null $identity    Existing Design Lens identity, if any.
     * @param string|null $sourcePath  Section-relative positional locator (root when null).
     * @param string      $seed        Deterministic seed for the generated id.
     * @param string|null $expectedTag Tag name of the located element, for verification.
     */
    public static function resolve(
        string $html,
        ?string $selector,
        ?string $identity,
        ?string $sourcePath,
        string $seed,
        ?string $expectedTag = null,
    ): StableSelectorResult {

        $callerIdSelector = is_string($selector) ? self::idFromSelector($selector) : null;

        // All remaining strategies are structural — parse the fragment once.
        $dom = self::parse($html);
        if ($dom === null) {
            return StableSelectorResult::unresolved($html);
        }

        $root = self::firstElementChild($dom);
        if ($root === null) {
            return StableSelectorResult::unresolved($html);
        }

        if ($callerIdSelector !== null && !self::isSectionRootId($callerIdSelector) && self::idIsUnique($dom, $callerIdSelector)) {
            return new StableSelectorResult('#' . $callerIdSelector, $html, false);
        }

        // Locate the target element by section-relative path (root when absent).
        $target = self::normalizeLocatedTarget(self::locate($root, $sourcePath));
        if (!$target instanceof DOMElement) {
            return StableSelectorResult::unresolved($html);
        }

        // Defend against live-DOM/stored-HTML structural drift: if the caller told
        // us what element it selected, the resolved node must match it.
        if (
            is_string($expectedTag) && $expectedTag !== ''
            && strtolower($target->nodeName) !== strtolower($expectedTag)
        ) {
            return StableSelectorResult::unresolved($html);
        }

        if ($callerIdSelector !== null && self::isSectionRootId($callerIdSelector) && $target === $root) {
            if (self::idExistsOutsideTarget($dom, $callerIdSelector, $target)) {
                return StableSelectorResult::unresolved($html);
            }

            if ($target->getAttribute('id') === $callerIdSelector) {
                return new StableSelectorResult('#' . $callerIdSelector, $html, false);
            }

            $patchedHtml = self::htmlWithElementId($html, $dom, $target, $callerIdSelector);
            if ($patchedHtml === null) {
                return StableSelectorResult::unresolved($html);
            }

            return new StableSelectorResult('#' . $callerIdSelector, $patchedHtml, true);
        }

        // 3. Existing id attribute on the located element.
        $existingId = $target->getAttribute('id');
        if ($existingId !== '' && preg_match(self::ID_PATTERN, $existingId) === 1 && self::idIsUnique($dom, $existingId)) {
            return new StableSelectorResult('#' . $existingId, $html, false);
        }

        // 3b. The browser may have created the preview id first so the live
        // preview, AI context, and persisted source all share one owner.
        if ($callerIdSelector !== null && str_starts_with($callerIdSelector, 'upb-el-') && !self::idExists($dom, $callerIdSelector)) {
            $patchedHtml = self::htmlWithElementId($html, $dom, $target, $callerIdSelector);
            if ($patchedHtml === null) {
                return StableSelectorResult::unresolved($html);
            }

            return new StableSelectorResult('#' . $callerIdSelector, $patchedHtml, true);
        }

        // 4. Promote the located element by adding a generated id,
        //    then patch only that opening tag in the original source.
        $generated = self::generateId($seed);
        $patchedHtml = self::htmlWithElementId($html, $dom, $target, $generated);
        if ($patchedHtml === null) {
            return StableSelectorResult::unresolved($html);
        }

        return new StableSelectorResult('#' . $generated, $patchedHtml, true);
    }

    /** Accept id selectors as stable; reject positional or empty selectors. */
    public static function isStableSelector(string $selector): bool
    {
        $selector = trim($selector);
        if ($selector === '') {
            return false;
        }

        if (preg_match('/^#[A-Za-z][\w-]*$/', $selector) === 1) {
            return true;
        }

        return false;
    }

    private static function idFromSelector(string $selector): ?string
    {
        if (preg_match('/^#([A-Za-z][\w-]*)$/', trim($selector), $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private static function idIsUnique(DOMDocument $dom, string $id): bool
    {
        $matches = 0;
        foreach ($dom->getElementsByTagName('*') as $element) {
            if ($element instanceof DOMElement && $element->getAttribute('id') === $id) {
                $matches++;
                if ($matches > 1) {
                    return false;
                }
            }
        }

        return $matches === 1;
    }

    private static function idExists(DOMDocument $dom, string $id): bool
    {
        foreach ($dom->getElementsByTagName('*') as $element) {
            if ($element instanceof DOMElement && $element->getAttribute('id') === $id) {
                return true;
            }
        }

        return false;
    }

    private static function idExistsOutsideTarget(DOMDocument $dom, string $id, DOMElement $target): bool
    {
        foreach ($dom->getElementsByTagName('*') as $element) {
            if ($element instanceof DOMElement && $element !== $target && $element->getAttribute('id') === $id) {
                return true;
            }
        }

        return false;
    }

    private static function isSectionRootId(string $id): bool
    {
        return preg_match('/^upb-section-[1-9][0-9]*$/', $id) === 1;
    }

    public static function generateId(string $seed): string
    {
        return 'upb-el-' . substr(hash('sha1', $seed), 0, 12);
    }

    // ── HTML parsing ─────────────────────────────────────

    /**
     * Parse an HTML fragment without implying <html>/<body>/doctype wrappers.
     * Returns null when the markup cannot be loaded.
     */
    private static function parse(string $html): ?DOMDocument
    {
        if (trim($html) === '') {
            return null;
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        // The XML encoding hint keeps UTF-8 bytes intact; the flags keep the
        // fragment free of an implied html/body/doctype.
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded === false) {
            return null;
        }

        self::removeProcessingInstructions($dom);

        return $dom;
    }

    private static function removeProcessingInstructions(DOMDocument $dom): void
    {
        $remove = [];
        foreach ($dom->childNodes as $child) {
            if ($child->nodeType === XML_PI_NODE) {
                $remove[] = $child;
            }
        }
        foreach ($remove as $node) {
            $dom->removeChild($node);
        }
    }

    private static function firstElementChild(DOMDocument $dom): ?DOMElement
    {
        foreach ($dom->childNodes as $child) {
            if ($child instanceof DOMElement) {
                return $child;
            }
        }

        return null;
    }

    /**
     * Content edits sometimes submit the text-run path inside the selected
     * element. Promote that node path back to its containing element before we
     * compare tags or inject a stable identity.
     */
    private static function normalizeLocatedTarget(?DOMNode $node): ?DOMElement
    {
        if ($node instanceof DOMElement) {
            return $node;
        }

        return $node?->parentNode instanceof DOMElement
            ? $node->parentNode
            : null;
    }

    /**
     * Walk a section-relative positional path. The path starts with "0" at the
     * section root; each further segment indexes into tree children.
     */
    private static function locate(DOMElement $root, ?string $sourcePath): ?DOMNode
    {
        $path = is_string($sourcePath) ? trim($sourcePath) : '';
        if ($path === '') {
            return $root;
        }

        $segments = explode('.', $path);
        if (($segments[0] ?? '') !== '0') {
            return null;
        }

        $current = $root;
        foreach (array_slice($segments, 1) as $segment) {
            if ($segment === '' || ctype_digit($segment) === false) {
                return null;
            }

            $children = self::treeChildren($current);
            $index = (int) $segment;
            if (!isset($children[$index])) {
                return null;
            }

            $current = $children[$index];
        }

        return $current;
    }

    /**
     * Children that count as tree nodes: element nodes and non-empty text nodes,
     * matching the Design Lens SDK's positional path semantics.
     *
     * @return array<int, DOMNode>
     */
    private static function treeChildren(DOMNode $node): array
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

    private static function htmlWithElementId(
        string $html,
        DOMDocument $dom,
        DOMElement $target,
        string $elementId,
    ): ?string {
        if (preg_match(self::ID_PATTERN, $elementId) !== 1) {
            return null;
        }

        $tagName = strtolower($target->nodeName);
        $ordinal = self::targetOrdinalForTag($dom, $target);
        if ($ordinal === null) {
            return null;
        }

        return self::patchElementIdByTagOrdinal($html, $tagName, $ordinal, $elementId);
    }

    private static function targetOrdinalForTag(DOMDocument $dom, DOMElement $target): ?int
    {
        $tagName = strtolower($target->nodeName);
        $ordinal = 0;

        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof DOMElement || strtolower($element->nodeName) !== $tagName) {
                continue;
            }

            if ($element === $target) {
                return $ordinal;
            }

            $ordinal++;
        }

        return null;
    }

    private static function patchElementIdByTagOrdinal(
        string $html,
        string $tagName,
        int $ordinal,
        string $elementId,
    ): ?string {
        $length = strlen($html);
        $offset = 0;
        $currentOrdinal = 0;

        while ($offset < $length) {
            $tagStart = strpos($html, '<', $offset);
            if ($tagStart === false) {
                return null;
            }

            $next = $html[$tagStart + 1] ?? '';
            if ($next === '') {
                return null;
            }

            if ($next === '!') {
                $offset = self::skipMarkupDeclaration($html, $tagStart);
                continue;
            }

            if ($next === '?') {
                $offset = self::skipProcessingInstruction($html, $tagStart);
                continue;
            }

            if ($next === '/' || preg_match('/[A-Za-z]/', $next) !== 1) {
                $offset = $tagStart + 1;
                continue;
            }

            $nameEnd = self::tagNameEnd($html, $tagStart + 1);
            if ($nameEnd === $tagStart + 1) {
                $offset = $tagStart + 1;
                continue;
            }

            $currentTagName = strtolower(substr($html, $tagStart + 1, $nameEnd - ($tagStart + 1)));
            $tagEnd = self::openingTagEnd($html, $tagStart);
            if ($tagEnd === null) {
                return null;
            }

            if ($currentTagName === $tagName) {
                if ($currentOrdinal === $ordinal) {
                    return self::replaceOpeningTagId($html, $tagStart, $tagEnd, $elementId);
                }

                $currentOrdinal++;
            }

            if (self::isRawTextTag($currentTagName)) {
                $offset = self::rawTextTagEnd($html, $currentTagName, $tagEnd + 1) ?? ($tagEnd + 1);
                continue;
            }

            $offset = $tagEnd + 1;
        }

        return null;
    }

    private static function tagNameEnd(string $html, int $nameStart): int
    {
        $length = strlen($html);
        $i = $nameStart;
        while ($i < $length && preg_match('/[A-Za-z0-9:_-]/', $html[$i]) === 1) {
            $i++;
        }

        return $i;
    }

    private static function openingTagEnd(string $html, int $tagStart): ?int
    {
        $length = strlen($html);
        $quote = null;

        for ($i = $tagStart + 1; $i < $length; $i++) {
            $char = $html[$i];
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
                return $i;
            }
        }

        return null;
    }

    private static function replaceOpeningTagId(string $html, int $tagStart, int $tagEnd, string $elementId): string
    {
        $tag = substr($html, $tagStart, $tagEnd - $tagStart + 1);
        $idAttribute = self::idAttributeRange($tag);
        $replacement = 'id="' . $elementId . '"';

        if ($idAttribute !== null) {
            $tag = substr($tag, 0, $idAttribute[0]) . $replacement . substr($tag, $idAttribute[1]);
        } else {
            $insertAt = self::idInsertOffset($tag);
            $tag = substr($tag, 0, $insertAt) . ' ' . $replacement . substr($tag, $insertAt);
        }

        return substr($html, 0, $tagStart) . $tag . substr($html, $tagEnd + 1);
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private static function idAttributeRange(string $tag): ?array
    {
        $length = strlen($tag);
        $i = self::tagNameEnd($tag, 1);

        while ($i < $length) {
            while ($i < $length && ctype_space($tag[$i])) {
                $i++;
            }

            if ($i >= $length || $tag[$i] === '>' || $tag[$i] === '/') {
                return null;
            }

            $nameStart = $i;
            while ($i < $length && !ctype_space($tag[$i]) && !in_array($tag[$i], ['=', '/', '>'], true)) {
                $i++;
            }

            if ($nameStart === $i) {
                $i++;
                continue;
            }

            $name = strtolower(substr($tag, $nameStart, $i - $nameStart));
            while ($i < $length && ctype_space($tag[$i])) {
                $i++;
            }

            $attributeEnd = $i;
            if ($i < $length && $tag[$i] === '=') {
                $i++;
                while ($i < $length && ctype_space($tag[$i])) {
                    $i++;
                }

                if ($i < $length && ($tag[$i] === '"' || $tag[$i] === "'")) {
                    $quote = $tag[$i];
                    $i++;
                    while ($i < $length && $tag[$i] !== $quote) {
                        $i++;
                    }
                    if ($i < $length) {
                        $i++;
                    }
                    $attributeEnd = $i;
                } else {
                    while ($i < $length && !ctype_space($tag[$i]) && !in_array($tag[$i], ['>', '/'], true)) {
                        $i++;
                    }
                    $attributeEnd = $i;
                }
            }

            if ($name === 'id') {
                return [$nameStart, $attributeEnd];
            }
        }

        return null;
    }

    private static function idInsertOffset(string $tag): int
    {
        $end = strlen($tag) - 1;
        $i = $end - 1;
        while ($i > 0 && ctype_space($tag[$i])) {
            $i--;
        }

        if ($i > 0 && $tag[$i] === '/') {
            $insertAt = $i;
            while ($insertAt > 0 && ctype_space($tag[$insertAt - 1])) {
                $insertAt--;
            }

            return $insertAt;
        }

        return $end;
    }

    private static function skipMarkupDeclaration(string $html, int $tagStart): int
    {
        if (substr($html, $tagStart, 4) === '<!--') {
            $commentEnd = strpos($html, '-->', $tagStart + 4);

            return $commentEnd === false ? strlen($html) : $commentEnd + 3;
        }

        $end = strpos($html, '>', $tagStart + 2);

        return $end === false ? strlen($html) : $end + 1;
    }

    private static function skipProcessingInstruction(string $html, int $tagStart): int
    {
        $end = strpos($html, '?>', $tagStart + 2);
        if ($end !== false) {
            return $end + 2;
        }

        $end = strpos($html, '>', $tagStart + 2);

        return $end === false ? strlen($html) : $end + 1;
    }

    private static function isRawTextTag(string $tagName): bool
    {
        return in_array($tagName, ['script', 'style', 'textarea', 'title'], true);
    }

    private static function rawTextTagEnd(string $html, string $tagName, int $offset): ?int
    {
        $tail = substr($html, $offset);
        if (preg_match('/<\/\s*' . preg_quote($tagName, '/') . '\s*>/i', $tail, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        return $offset + (int) $matches[0][1] + strlen((string) $matches[0][0]);
    }
}
