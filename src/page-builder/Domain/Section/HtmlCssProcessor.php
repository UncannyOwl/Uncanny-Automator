<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Section;

use UncannyPageBuilder\Domain\Binding\BindingRegistry;
use UncannyPageBuilder\Domain\Editing\ExactSourcePatcher;
use UncannyPageBuilder\Domain\Binding\RegionReplaces;
use UncannyPageBuilder\Domain\Binding\RegionTemplate;
use UncannyPageBuilder\Domain\Exception\EditableUpdateException;

/**
 * Pure DOM/CSS processing logic extracted from SectionService.
 *
 * Handles DOMDocument parsing, editable HTML patching, CSS scope
 * validation, and bridge artifact normalization. No WordPress
 * dependencies — only PHP stdlib (DOMDocument, DOMXPath).
 */
final class HtmlCssProcessor
{
    /**
     * Without a registry the legacy patch-normalization rules apply
     * (wp_menu emptied, every other region trimmed to its first element
     * child). With one, each region follows its declaration's RegionContract.
     */
    public function __construct(
        private readonly ?BindingRegistry $bindings = null,
        private readonly ?ExactSourcePatcher $sourcePatcher = null,
        private readonly ?SectionHtmlCleanerInterface $htmlCleaner = null,
    ) {}

    /**
     * Validate replacement HTML, splice it into the stored section, and handle CSS ownership.
     *
     * @return array{html: string, css: string}
     * @throws EditableUpdateException
     */
    public function applyRewriteEditable(
        string $storedHtml,
        string $storedCss,
        SectionEditProposal $proposal,
        int $sectionId,
    ): array {
        $editableKey = $proposal->editableKey();
        $editableType = $proposal->editableType();
        $replacementHtmlStr = $proposal->replacementHtml();

        $this->assertEditableKeyCanBeUsedInXPath((string) $editableKey);

        // 1. Parse stored HTML
        $wrappedStored = '<div id="__upb_rw_root">' . $storedHtml . '</div>';
        $storedDoc = $this->loadDom($wrappedStored);

        $storedRoot = $storedDoc->getElementById('__upb_rw_root');
        if (!$storedRoot instanceof \DOMElement) {
            throw EditableUpdateException::keyNotFound($editableKey);
        }

        // 2. Find original node by editable key
        $storedXpath = new \DOMXPath($storedDoc);
        $nodes = $storedXpath->query('//*[@data-ai-editable="' . $editableKey . '"]');

        if ($nodes === false || $nodes->length === 0) {
            throw EditableUpdateException::keyNotFound($editableKey);
        }
        if ($nodes->length > 1) {
            throw EditableUpdateException::duplicateKey($editableKey);
        }

        $originalNode = $nodes->item(0);
        if (!$originalNode instanceof \DOMElement) {
            throw EditableUpdateException::keyNotFound($editableKey);
        }

        $originalTag = strtolower($originalNode->tagName);

        // 3. Parse replacement HTML
        $wrappedRepl = '<div id="__upb_rw_repl">' . $replacementHtmlStr . '</div>';
        $replDoc = $this->loadDom($wrappedRepl);

        $replRoot = $replDoc->getElementById('__upb_rw_repl');
        if (!$replRoot instanceof \DOMElement) {
            // Fallback: nothing to splice, leave original unchanged.
            return ['html' => $storedHtml, 'css' => $storedCss];
        }

        // Import all replacement children and splice them in place of the original node.
        $parent = $originalNode->parentNode;
        foreach ($replRoot->childNodes as $child) {
            $imported = $storedDoc->importNode($child, true);
            $parent->insertBefore($imported, $originalNode);
        }
        $parent->removeChild($originalNode);

        // Extract patched HTML
        $output = '';
        foreach ($storedRoot->childNodes as $child) {
            $output .= $storedDoc->saveHTML($child);
        }
        $patchedHtml = trim($output);

        // Handle CSS ownership
        $patchedCss = $this->applyEditableCss($storedCss, $editableKey, $proposal->replacementCss(), $sectionId);

        return ['html' => $patchedHtml, 'css' => $patchedCss];
    }

    /**
     * Apply CSS ownership for an editable rewrite using comment markers.
     */
    public function applyEditableCss(
        string $storedCss,
        string $editableKey,
        ?string $replacementCss,
        int $sectionId,
    ): string {
        // If replacementCss is null, preserve existing CSS unchanged
        if ($replacementCss === null) {
            return $storedCss;
        }

        // Strip existing block for this editable key (if any)
        $escapedKey = preg_quote($editableKey, '/');
        $pattern = '/\/\* @upb-editable:' . $escapedKey . ' start \*\/.*?\/\* @upb-editable:' . $escapedKey . ' end \*\//s';
        $strippedCss = preg_replace($pattern, '', $storedCss) ?? $storedCss;
        $strippedCss = trim($strippedCss);

        // If empty string, just return the stripped CSS (explicit removal)
        if ($replacementCss === '') {
            return $strippedCss;
        }

        // Validate replacement CSS
        $this->validateEditableCss($replacementCss, $editableKey, $sectionId);

        // Append new marked block
        $block = "\n/* @upb-editable:{$editableKey} start */\n"
            . $replacementCss . "\n"
            . "/* @upb-editable:{$editableKey} end */";

        if ($strippedCss === '') {
            return trim($block);
        }

        return $strippedCss . $block;
    }

    /**
     * CSS scope validation removed — agent is free to write any CSS.
     */
    public function validateEditableCss(string $css, string $editableKey, int $sectionId): void
    {
        // No-op: agent-authored CSS is accepted as-is.
    }

    /**
     * Patch stored HTML by applying editable updates via DOMDocument.
     *
     * @param EditableUpdate[] $updates
     * @throws EditableUpdateException
     */
    public function applyEditableUpdates(string $html, array $updates): string
    {
        $wrappedHtml = '<div id="__upb_eu_root">' . $html . '</div>';
        $doc = $this->loadDom($wrappedHtml);

        $root = $doc->getElementById('__upb_eu_root');
        if (!$root instanceof \DOMElement) {
            return $html;
        }

        $xpath = new \DOMXPath($doc);

        foreach ($updates as $update) {
            $this->assertEditableKeyCanBeUsedInXPath($update->key());

            $nodes = $xpath->query('//*[@data-ai-editable="' . $update->key() . '"]');
            if ($nodes === false || $nodes->length === 0) {
                throw EditableUpdateException::keyNotFound($update->key());
            }

            if ($nodes->length > 1) {
                throw EditableUpdateException::duplicateKey($update->key());
            }

            $node = $nodes->item(0);
            if (!$node instanceof \DOMElement) {
                throw EditableUpdateException::keyNotFound($update->key());
            }

            $storedType = $node->getAttribute('data-ai-type');
            if ($storedType !== $update->type()) {
                throw EditableUpdateException::typeMismatch($update->key(), $storedType, $update->type());
            }

            match ($update->type()) {
                'text', 'textarea' => $this->applyTextUpdate($doc, $node, $update),
                'link' => $this->applyLinkUpdate($doc, $node, $update),
                'image' => $this->applyImageUpdate($node, $update),
                'bg-image' => $this->applyBgImageUpdate($node, $update),
                default => throw EditableUpdateException::typeMismatch($update->key(), $update->type(), $update->type()),
            };
        }

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $doc->saveHTML($child);
        }

        return trim($output);
    }

    /**
     * Normalize patched HTML by stripping bridge artifacts and collapsing dynamic regions.
     */
    public function normalizePatchedHtml(string $html): string
    {
        // Step 1: Strip shared bridge artifacts (badges, section-ids, contenteditable).
        $cleaned = $this->htmlCleaner?->clean($html) ?? $html;

        // Step 2: Normalize dynamic regions (patch-specific — not needed at render time).
        // Skip DOMDocument entirely when there are no dynamic regions to collapse.
        if (!str_contains($cleaned, 'data-ai-dynamic')) {
            return $cleaned;
        }

        // Encode Alpine @ shorthand attributes before DOMDocument parsing.
        // DOMDocument's HTML4 parser strips @ prefixed attribute names.
        $encoded = preg_replace('/\s@([\w.:+-]+)=/', ' data-x-on-$1=', $cleaned);

        $wrappedHtml = '<div id="__upb_patch_dyn">' . $encoded . '</div>';
        $doc = $this->loadDom($wrappedHtml);

        $root = $doc->getElementById('__upb_patch_dyn');
        if (!$root instanceof \DOMElement) {
            return $cleaned;
        }

        $xpath = new \DOMXPath($doc);

        foreach ($xpath->query('//*[@data-ai-dynamic]') as $dynamicNode) {
            if (!$dynamicNode instanceof \DOMElement) {
                continue;
            }

            $source = $dynamicNode->getAttribute('data-ai-dynamic');
            $declaration = $this->bindings?->get($source);

            if ($declaration === null) {
                // Unknown binding or no registry: legacy behavior.
                if ($source === 'wp_menu') {
                    $this->removeAllChildren($dynamicNode);
                } else {
                    $this->retainFirstDirectElementChild($dynamicNode);
                }
                continue;
            }

            $contract = $declaration->regionContract();

            // Conditionals wrap real authored content, and url-shaped
            // bindings only write an attribute on the host element — their
            // children must never be trimmed.
            if (
                $contract->replaces === RegionReplaces::SelfElement
                || $contract->replaces === RegionReplaces::HostAttribute
            ) {
                continue;
            }

            if ($contract->template === RegionTemplate::FirstChild) {
                $this->retainFirstDirectElementChild($dynamicNode);

                // Card templates are authored markup — keep them whole. A
                // self-rendering template consumer (wp_menu) only reads the
                // template element's attributes, so its rendered children
                // are dropped instead of being stored back.
                if (!$declaration->isCard()) {
                    $template = $this->firstDirectElementChild($dynamicNode);
                    if ($template !== null) {
                        $this->removeAllChildren($template);
                    }
                }

                continue;
            }

            // Fully projected regions: children are discarded placeholders.
            $this->removeAllChildren($dynamicNode);
        }

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $doc->saveHTML($child);
        }

        // Decode Alpine @ shorthand back from placeholders.
        $output = preg_replace('/\sdata-x-on-([\w.:+-]+)=/', ' @$1=', $output);

        return trim($output);
    }

    /**
     * Apply editable content updates via DOMDocument (agent tool: update_content).
     *
     * Receives already-sanitized values from the controller.
     *
     * @param array<int, array{key: string, type: string, value?: string, url?: string, src?: string, alt?: string}> $updates
     * @throws \InvalidArgumentException
     */
    public function applyContentUpdates(string $html, array $updates): string
    {
        $dom = $this->loadDom($html);
        $xpath = new \DOMXPath($dom);

        foreach ($updates as $i => $update) {
            $key = $update['key'] ?? '';
            $type = $update['type'] ?? '';
            if ($key === '' || $type === '') {
                throw new \InvalidArgumentException("updates[{$i}]: key and type are required.");
            }

            try {
                $this->assertEditableKeyCanBeUsedInXPath($key);
            } catch (\InvalidArgumentException) {
                throw new \InvalidArgumentException("updates[{$i}]: key contains invalid characters.");
            }

            $nodes = $xpath->query('//*[@data-ai-editable="' . $key . '"]');
            if ($nodes === false || $nodes->length === 0) {
                throw new \InvalidArgumentException("updates[{$i}]: key \"{$key}\" not found.");
            }

            /** @var \DOMElement $node */
            $node = $nodes->item(0);

            if ($type === 'text' || $type === 'textarea' || $type === 'link') {
                $value = $update['value'] ?? '';
                while ($node->firstChild) {
                    $node->removeChild($node->firstChild);
                }
                $node->appendChild($dom->createTextNode($value));
                if ($type === 'link' && isset($update['url'])) {
                    $node->setAttribute('href', $update['url']);
                }
            } elseif ($type === 'image') {
                if (isset($update['src'])) {
                    $node->setAttribute('src', $update['src']);
                }
                if (isset($update['alt'])) {
                    $node->setAttribute('alt', $update['alt']);
                }
            }
        }

        return $this->saveDom($dom);
    }

    /**
     * Apply a binding change (query args or template replacement) to a dynamic region.
     *
     * @throws \InvalidArgumentException
     */
    public function applyBindingChange(string $html, string $bindingId, string $changeType, array $params): string
    {
        $dom = $this->loadDom($html);
        $xpath = new \DOMXPath($dom);

        // Find dynamic region element.
        $regionNodes = $xpath->query('//*[@data-ai-dynamic]');
        /** @var \DOMElement|null $regionEl */
        $regionEl = null;
        if ($regionNodes !== false) {
            for ($i = 0; $i < $regionNodes->length; $i++) {
                $el = $regionNodes->item($i);
                if ($el instanceof \DOMElement) {
                    $source = $el->getAttribute('data-ai-source') ?: $el->getAttribute('data-ai-dynamic');
                    $path = $el->getAttribute('data-ai-path') ?: '';
                    if ($source . ':' . $path === $bindingId || $source === $bindingId) {
                        $regionEl = $el;
                        break;
                    }
                }
            }
        }
        if ($regionEl === null) {
            throw new \InvalidArgumentException('Dynamic region element not found in HTML.');
        }

        if ($changeType === 'query') {
            $queryArgs = $params['query_args'] ?? [];
            $allowedQueryAttributes = $params['allowed_query_attributes'] ?? null;
            foreach ($queryArgs as $key => $value) {
                $attrName = str_starts_with((string) $key, 'data-')
                    ? (string) $key
                    : 'data-' . str_replace('_', '-', (string) $key);

                if (str_starts_with($attrName, 'data-ai-')) {
                    throw new \InvalidArgumentException('Binding query updates cannot change reserved data-ai-* attributes.');
                }

                if (is_array($allowedQueryAttributes) && !in_array($attrName, $allowedQueryAttributes, true)) {
                    throw new \InvalidArgumentException(sprintf('Binding query attribute "%s" is not declared for this binding.', $attrName));
                }

                $regionEl->setAttribute($attrName, $this->serializeBindingQueryAttributeValue($value));
            }
        } else {
            $templateHtml = $params['template_html'] ?? '';
            // Replace region's children with new template.
            while ($regionEl->firstChild) {
                $regionEl->removeChild($regionEl->firstChild);
            }
            $tempDoc = new \DOMDocument('1.0', 'UTF-8');
            libxml_use_internal_errors(true);
            $tempDoc->loadHTML(
                '<?xml encoding="utf-8" ?><div id="__w">' . $templateHtml . '</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
            );
            libxml_clear_errors();
            $wrap = $tempDoc->getElementById('__w');
            if ($wrap) {
                foreach ($wrap->childNodes as $child) {
                    $regionEl->appendChild($dom->importNode($child, true));
                }
            }
        }

        return $this->saveDom($dom);
    }

    private function serializeBindingQueryAttributeValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        throw new \InvalidArgumentException('Binding query attribute values must be scalar.');
    }

    /**
     * Apply source patches with exact-match and whitespace-normalized fallback.
     *
     * The public tool contract is search/replace. The action/content shape is
     * also accepted because models commonly express insertion as
     * {action:"insert_after", search:"...", content:"..."}.
     *
     * @return array{0: string, 1: ?string} [result, errorMessage]
     */
    public function applyStringPatches(string $subject, array $patches, string $field): array
    {
        return $this->sourcePatcher()->apply($subject, $patches, $field);
    }

    private function sourcePatcher(): ExactSourcePatcher
    {
        return $this->sourcePatcher ?? new ExactSourcePatcher();
    }

    // ── Private helpers ──────────────────────────────────────────────

    private function loadDom(string $html): \DOMDocument
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        return $dom;
    }

    private function saveDom(\DOMDocument $dom): string
    {
        $output = $dom->saveHTML() ?: '';
        return trim(preg_replace('/<\?xml[^?]*\?>\s*/i', '', $output));
    }

    private function assertEditableKeyCanBeUsedInXPath(string $editableKey): void
    {
        // Editable keys are interpolated into DOMXPath literals. Keep the key
        // vocabulary small and explicit so callers cannot alter the query.
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $editableKey)) {
            throw new \InvalidArgumentException('Editable key contains invalid characters.');
        }
    }

    private function applyTextUpdate(\DOMDocument $doc, \DOMElement $node, EditableUpdate $update): void
    {
        while ($node->firstChild) {
            $node->removeChild($node->firstChild);
        }
        $node->appendChild($doc->createTextNode($update->textValue()));
    }

    private function applyLinkUpdate(\DOMDocument $doc, \DOMElement $node, EditableUpdate $update): void
    {
        $node->setAttribute('href', $update->urlValue());

        if ($this->hasChildElements($node)) {
            throw EditableUpdateException::hasNestedMarkup($update->key());
        }

        while ($node->firstChild) {
            $node->removeChild($node->firstChild);
        }
        $node->appendChild($doc->createTextNode($update->textValue()));
    }

    private function hasChildElements(\DOMElement $node): bool
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                return true;
            }
        }
        return false;
    }

    private function applyImageUpdate(\DOMElement $node, EditableUpdate $update): void
    {
        $node->setAttribute('src', $update->srcValue());
        $node->setAttribute('alt', $update->altValue());
    }

    private function applyBgImageUpdate(\DOMElement $node, EditableUpdate $update): void
    {
        $existingStyle = trim($node->getAttribute('style'));
        $newBgValue = "--bg-image: url('" . $update->srcValue() . "')";

        if ($existingStyle === '') {
            $node->setAttribute('style', $newBgValue);
            return;
        }

        // Parse existing declarations, replace or append --bg-image
        $declarations = array_map('trim', explode(';', $existingStyle));
        $declarations = array_filter($declarations, static fn(string $d) => $d !== '');

        $found = false;
        $result = [];
        foreach ($declarations as $decl) {
            if (str_starts_with(trim($decl), '--bg-image')) {
                $result[] = $newBgValue;
                $found = true;
            } else {
                $result[] = $decl;
            }
        }

        if (!$found) {
            $result[] = $newBgValue;
        }

        $node->setAttribute('style', implode('; ', $result));
    }

    private function firstDirectElementChild(\DOMElement $container): ?\DOMElement
    {
        foreach ($container->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                return $child;
            }
        }

        return null;
    }

    private function retainFirstDirectElementChild(\DOMElement $container): void
    {
        $directElementChildren = [];
        foreach ($container->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $directElementChildren[] = $child;
            }
        }

        if (count($directElementChildren) <= 1) {
            return;
        }

        for ($i = 1; $i < count($directElementChildren); $i++) {
            $container->removeChild($directElementChildren[$i]);
        }
    }

    private function removeAllChildren(\DOMElement $node): void
    {
        while ($node->firstChild) {
            $node->removeChild($node->firstChild);
        }
    }
}
