<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Section;

use UncannyPageBuilder\Domain\Section\BindingSchema;
use UncannyPageBuilder\Domain\Section\EditableManifestEntry;
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
        $wrappedHtml = '<div id="__upb_manifest_root">' . $section->content()->html() . '</div>';

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

        $editables = [];
        foreach ($xpath->query('//*[@data-ai-editable]') as $editableNode) {
            if ($editableNode instanceof \DOMElement) {
                $editables[] = EditableManifestEntry::fromExtracted($this->extractEditableNode($editableNode, $root));
            }
        }

        $dynamicRegions = [];
        foreach ($xpath->query('//*[@data-ai-dynamic]') as $dynamicNode) {
            if ($dynamicNode instanceof \DOMElement) {
                $dynamicRegions[] = $this->extractDynamicRegion($dynamicNode, $root);
            }
        }

        return new SectionManifest(
            sectionId: $section->id(),
            pageId: $section->pageId(),
            root: $this->extractRootMetadata($root),
            editables: $editables,
            dynamicRegions: $dynamicRegions,
            constraints: [
                'requires_single_root' => true,
                'allowed_editable_types' => BindingSchema::editableTypes(),
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
            'path' => $this->buildNodePath($root, $root),
        ];
    }

    /** @return array<string, mixed> */
    private function extractEditableNode(\DOMElement $node, ?\DOMElement $root): array
    {
        $type = trim($node->getAttribute('data-ai-type')) ?: 'text';
        $editable = [
            'key' => trim($node->getAttribute('data-ai-editable')),
            'type' => $type,
            'tag' => strtolower($node->tagName),
            'path' => $this->buildNodePath($node, $root),
        ];

        if ($type === 'link') {
            $editable['text_value'] = trim($node->textContent);
            $editable['url_value'] = $node->getAttribute('href');
            return $editable;
        }

        if ($type === 'image') {
            $editable['src_value'] = $node->getAttribute('src');
            $editable['alt_value'] = $node->getAttribute('alt');
            return $editable;
        }

        if ($type === 'bg-image') {
            $editable['style_value'] = $node->getAttribute('style');
            return $editable;
        }

        $editable['text_value'] = trim($node->textContent);
        return $editable;
    }

    /** @return array<string, mixed> */
    private function extractDynamicRegion(\DOMElement $node, ?\DOMElement $root): array
    {
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
                    'path' => $this->buildNodePath($templateRoot, $root),
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
                'path' => $this->buildNodePath($bindingNode, $root),
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
            'path' => $this->buildNodePath($node, $root),
        ], $queryData, [
            'bind_keys' => $bindKeys,
            'bindings' => $bindings,
            'card_template_html' => $this->templateHtml($node),
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

    private function buildNodePath(\DOMElement $node, ?\DOMElement $root): string
    {
        $segments = [];
        $current = $node;

        while ($current instanceof \DOMElement) {
            $segments[] = strtolower($current->tagName) . '[' . $this->indexWithinSiblingTag($current) . ']';

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

    private function indexWithinSiblingTag(\DOMElement $node): int
    {
        $parent = $node->parentNode;
        if (!$parent instanceof \DOMNode) {
            return 1;
        }

        $index = 0;
        foreach ($parent->childNodes as $sibling) {
            if (!$sibling instanceof \DOMElement) {
                continue;
            }

            if (strtolower($sibling->tagName) !== strtolower($node->tagName)) {
                continue;
            }

            $index++;
            if ($sibling->isSameNode($node)) {
                return $index;
            }
        }

        return 1;
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
