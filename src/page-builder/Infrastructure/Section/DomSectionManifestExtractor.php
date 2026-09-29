<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Section;

use UncannyPageBuilder\Domain\Section\BindingSchema;
use UncannyPageBuilder\Domain\Section\AlpineAttributeProtection;
use UncannyPageBuilder\Domain\Section\DomElementPath;
use UncannyPageBuilder\Domain\Section\Section;
use UncannyPageBuilder\Domain\Section\SectionManifest;
use UncannyPageBuilder\Domain\Section\SectionManifestExtractorInterface;

/**
 * Extract a best-effort section manifest from the stored HTML attribute contract.
 */
final class DomSectionManifestExtractor implements SectionManifestExtractorInterface
{
    public function extract(Section $section): SectionManifest
    {
        $doc = new \DOMDocument();
        $sourceHtml = $section->content()->html();
        $alpine = new AlpineAttributeProtection($sourceHtml);
        $wrappedHtml = '<div id="__upb_manifest_root">' . $alpine->protect($sourceHtml) . '</div>';

        $previousUseInternalErrors = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $wrappedHtml,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);

        $container = $doc->getElementById('__upb_manifest_root');
        $root = $this->findFirstElementChild($container);
        $xpath = new \DOMXPath($doc);

        $dynamicRegions = [];
        foreach ($xpath->query('//*[@data-ai-dynamic]') as $dynamicNode) {
            if ($dynamicNode instanceof \DOMElement) {
                $dynamicRegions[] = $this->extractDynamicRegion($dynamicNode, $root, $alpine);
            }
        }

        return new SectionManifest(
            sectionId: $section->id(),
            pageId: $section->pageId(),
            root: $this->extractRootMetadata($root),
            dynamicRegions: $dynamicRegions,
            constraints: [
                'requires_single_root' => true,
                'allowed_dynamic_sources' => BindingSchema::dynamicSources(),
                'allowed_bind_keys' => BindingSchema::allBindKeys(),
                'scripts_forbidden' => true,
            ],
        );
    }

    /** @return array<string, mixed> */
    private function extractRootMetadata(?\DOMElement $root): array
    {
        if (!$root instanceof \DOMElement) {
            return [
                'tag' => '',
                'class_list' => [],
                'path' => '',
            ];
        }

        $classList = preg_split('/\s+/', trim($root->getAttribute('class'))) ?: [];

        return [
            'tag' => strtolower($root->tagName),
            'class_list' => array_values(array_filter($classList, 'strlen')),
            'path' => DomElementPath::fromRoot($root, $root),
        ];
    }

    /** @return array<string, mixed> */
    private function extractDynamicRegion(
        \DOMElement $node,
        ?\DOMElement $root,
        AlpineAttributeProtection $alpine,
    ): array {
        $source = trim($node->getAttribute('data-ai-dynamic')) ?: 'wp_query';

        $templateRoot = $this->findFirstElementChild($node);
        $bindings = [];

        // Check the card template root itself — getElementsByTagName returns
        // descendants only, so a root-level data-ai-bind would be missed.
        if ($templateRoot instanceof \DOMElement) {
            $rootBindKey = trim($templateRoot->getAttribute('data-ai-bind'));
            if ($rootBindKey !== '') {
                $bindings[] = [
                    'key' => $rootBindKey,
                    'tag' => strtolower($templateRoot->tagName),
                    'path' => DomElementPath::fromRoot($templateRoot, $root),
                ];
            }
        }

        $bindingSource = $templateRoot instanceof \DOMElement ? $templateRoot : $node;
        foreach ($bindingSource->getElementsByTagName('*') as $bindingNode) {
            if (!$bindingNode instanceof \DOMElement) {
                continue;
            }

            $bindKey = trim($bindingNode->getAttribute('data-ai-bind'));
            if ($bindKey === '') {
                continue;
            }

            $bindings[] = [
                'key' => $bindKey,
                'tag' => strtolower($bindingNode->tagName),
                'path' => DomElementPath::fromRoot($bindingNode, $root),
            ];
        }

        $bindKeys = array_values(array_unique(array_map(
            static fn(array $binding): string => (string) $binding['key'],
            $bindings
        )));

        // Extract per-source query attributes.
        $queryData = $this->extractQueryAttributes($node, $source);

        return array_merge([
            'source' => $source,
            'path' => DomElementPath::fromRoot($node, $root),
        ], $queryData, [
            'bind_keys' => $bindKeys,
            'bindings' => $bindings,
            'card_template_html' => $alpine->restore($this->templateHtml($node)),
        ]);
    }

    /**
     * Extract query attributes from the DOM node using the binding declaration config.
     *
     * @return array<string, mixed>
     */
    private function extractQueryAttributes(\DOMElement $node, string $source): array
    {
        $config = BindingSchema::queryAttributeConfigForSource($source);
        if (empty($config)) {
            return [];
        }

        $result = [];
        foreach ($config as $attrName => $attrConfig) {
            $raw = trim($node->getAttribute($attrName));
            $key = str_replace('-', '_', preg_replace('/^data-/', '', $attrName) ?? $attrName);
            $default = (string) ($attrConfig['default'] ?? '');
            $value = $raw !== '' ? $raw : $default;

            if ($value === '') {
                continue;
            }

            $result[$key] = match ($attrConfig['cast'] ?? 'string') {
                'int' => (int) $value,
                'bool' => in_array(strtolower(trim((string) $value)), ['true', '1', 'yes'], true),
                default => (string) $value,
            };
        }

        return $result;
    }

    private function findFirstElementChild(?\DOMElement $container): ?\DOMElement
    {
        if (!$container instanceof \DOMElement) {
            return null;
        }

        foreach ($container->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                return $child;
            }
        }

        return null;
    }

    private function innerHtml(\DOMElement $node): string
    {
        $html = '';
        $document = $node->ownerDocument;

        if (!$document instanceof \DOMDocument) {
            return $html;
        }

        foreach ($node->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }

        return trim($html);
    }

    private function templateHtml(\DOMElement $node): string
    {
        $templateRoot = $this->findFirstElementChild($node);
        if ($templateRoot instanceof \DOMElement) {
            $document = $templateRoot->ownerDocument;
            if ($document instanceof \DOMDocument) {
                return trim($document->saveHTML($templateRoot));
            }
        }

        return $this->innerHtml($node);
    }
}
