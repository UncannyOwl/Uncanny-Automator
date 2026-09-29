<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Section;

use UncannyPageBuilder\Domain\Binding\BindingRegistry;
use UncannyPageBuilder\Domain\Editing\ExactSourcePatcher;
use UncannyPageBuilder\Domain\Binding\RegionReplaces;
use UncannyPageBuilder\Domain\Binding\RegionTemplate;

/**
 * Pure DOM/CSS processing logic extracted from SectionService.
 *
 * Handles DOMDocument parsing, source patches, binding changes, and bridge
 * artifact normalization. No WordPress dependencies — only PHP stdlib
 * (DOMDocument, DOMXPath).
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

        $alpine = new AlpineAttributeProtection($cleaned);
        $encoded = $alpine->protect($cleaned);

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

        $output = $alpine->restore($output);

        return trim($output);
    }

    /**
     * Apply a binding change (query args or template replacement) to a dynamic region.
     *
     * @throws \InvalidArgumentException
     */
    public function applyBindingChange(string $html, string $bindingId, string $changeType, array $params): string
    {
        $templateHtml = $changeType === 'template'
            ? (string) ($params['template_html'] ?? '')
            : '';
        $alpine = new AlpineAttributeProtection($html . "\0" . $templateHtml);
        $dom = $this->loadDom($alpine->protect($html));
        $xpath = new \DOMXPath($dom);

        // Find dynamic region element.
        $regionNodes = $xpath->query('//*[@data-ai-dynamic]');
        $root = $dom->documentElement instanceof \DOMElement ? $dom->documentElement : null;
        /** @var \DOMElement|null $regionEl */
        $regionEl = null;
        /** @var \DOMElement|null $legacyRegionEl */
        $legacyRegionEl = null;
        if ($regionNodes !== false) {
            for ($i = 0; $i < $regionNodes->length; $i++) {
                $el = $regionNodes->item($i);
                if ($el instanceof \DOMElement) {
                    $source = $el->getAttribute('data-ai-source') ?: $el->getAttribute('data-ai-dynamic');
                    $computedPath = DomElementPath::fromRoot($el, $root);
                    $authoredPath = trim($el->getAttribute('data-ai-path'));

                    // The inspected DOM path is the current identity. Keep the
                    // authored path only as a fallback for legacy callers.
                    if ($source . ':' . $computedPath === $bindingId) {
                        $regionEl = $el;
                        break;
                    }
                    if (
                        !$legacyRegionEl instanceof \DOMElement
                        && (
                            $source === $bindingId
                            || ($authoredPath !== '' && $source . ':' . $authoredPath === $bindingId)
                        )
                    ) {
                        $legacyRegionEl = $el;
                    }
                }
            }
        }
        $regionEl ??= $legacyRegionEl;
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
            // Replace region's children with new template.
            while ($regionEl->firstChild) {
                $regionEl->removeChild($regionEl->firstChild);
            }
            $tempDoc = new \DOMDocument('1.0', 'UTF-8');
            libxml_use_internal_errors(true);
            $tempDoc->loadHTML(
                '<?xml encoding="utf-8" ?><div id="__w">'
                    . $alpine->protect($templateHtml)
                    . '</div>',
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

        return $alpine->restore($this->saveDom($dom));
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
