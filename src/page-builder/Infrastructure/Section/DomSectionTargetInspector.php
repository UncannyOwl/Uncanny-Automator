<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Section;

use UncannyPageBuilder\Domain\Binding\BindingRegistry;
use UncannyPageBuilder\Domain\Binding\RegionReplaces;
use UncannyPageBuilder\Domain\DesignStyles\ElementStyleRule;
use UncannyPageBuilder\Domain\Editing\SourceTreeChildren;
use UncannyPageBuilder\Domain\Section\DomElementPath;
use UncannyPageBuilder\Domain\Section\Section;

/**
 * Inspects stored section HTML for production agent target discovery.
 */
final class DomSectionTargetInspector
{
    private const PREVIEW_LIMIT = 160;

    public function __construct(private readonly ?BindingRegistry $bindings = null) {}

    /**
     * @return array{tag: string, classes: list<string>, source_path: string}
     */
    public function rootMetadata(Section $section): array
    {
        $root = $this->rootElement($section->content()->html());

        if (!$root instanceof \DOMElement) {
            return [
                'tag' => '',
                'classes' => [],
                'source_path' => '',
            ];
        }

        return [
            'tag' => strtolower($root->tagName),
            'classes' => $this->classList($root),
            'source_path' => '0',
        ];
    }

    /**
     * @return array{text: int, image: int, link: int}
     */
    public function contentTargetCounts(Section $section): array
    {
        $root = $this->rootElement($section->content()->html());
        if (!$root instanceof \DOMElement) {
            return ['text' => 0, 'image' => 0, 'link' => 0];
        }

        return [
            'text' => $this->countTextTargets($root),
            'image' => $this->countElements($root, 'img'),
            'link' => $this->countElements($root, 'a'),
        ];
    }

    /**
     * @param list<string> $types
     * @return array{text: list<array<string, string>>, image: list<array<string, string>>, link: list<array<string, string>>, button: list<array<string, string>>}
     */
    public function contentTargets(Section $section, array $types = ['all']): array
    {
        $root = $this->rootElement($section->content()->html());
        if (!$root instanceof \DOMElement) {
            return ['text' => [], 'image' => [], 'link' => [], 'button' => []];
        }

        $wanted = $this->normalizeTypes($types);

        return [
            'text' => $this->wants($wanted, 'text') ? $this->textTargets($root) : [],
            'image' => $this->wants($wanted, 'image') ? $this->elementTargets($root, 'img', 'image') : [],
            'link' => $this->wants($wanted, 'link') ? $this->elementTargets($root, 'a', 'link') : [],
            'button' => $this->wants($wanted, 'button') ? $this->buttonTargets($root) : [],
        ];
    }

    public function textForTarget(Section $section, string $sourcePath, string $expectedTag): ?string
    {
        $root = $this->rootElement($section->content()->html());
        if (!$root instanceof \DOMElement) {
            return null;
        }

        $target = $this->locateElementByPath($root, $sourcePath);
        if (!$target instanceof \DOMElement) {
            return null;
        }

        if ($expectedTag !== '' && strtolower($target->tagName) !== strtolower($expectedTag)) {
            return null;
        }

        return $this->previewFields($target->textContent ?? '')['text'];
    }

    public function attributeForTarget(Section $section, string $sourcePath, string $expectedTag, string $attribute): ?string
    {
        $root = $this->rootElement($section->content()->html());
        if (!$root instanceof \DOMElement) {
            return null;
        }

        $target = $this->locateElementByPath($root, $sourcePath);
        if (!$target instanceof \DOMElement) {
            return null;
        }

        if ($expectedTag !== '' && strtolower($target->tagName) !== strtolower($expectedTag)) {
            return null;
        }

        return $target->getAttribute($attribute);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function designTargets(Section $section, bool $includeCss = false): array
    {
        $root = $this->rootElement($section->content()->html());
        if (!$root instanceof \DOMElement) {
            return [];
        }

        $sectionId = (int) ($section->id() ?? 0);
        $css = $includeCss ? $section->content()->css() : '';
        $elementStyles = $section->content()->elementStyles();
        $targets = [];
        $this->walkElements($root, function (\DOMElement $element, string $path) use (&$targets, $css, $includeCss, $sectionId, $elementStyles): void {
            if ($this->isNonContentTag($element)) {
                return;
            }

            $id = trim($element->getAttribute('id'));
            $classes = $this->classList($element);
            $elementStyleRules = $id !== '' ? $elementStyles->rulesForElementId($id) : [];
            $generatedRules = $includeCss ? $this->generatedCssCandidates($css, $id, $classes) : [];
            $hasInlineStyle = trim($element->getAttribute('style')) !== '';
            $styleOwnership = $elementStyleRules !== [] ? 'element_style' : ($hasInlineStyle ? 'inline_attribute' : 'unstyled');
            $recommendedWrite = match ($styleOwnership) {
                'element_style' => 'edit_part mode=durable_style',
                'inline_attribute' => 'edit_part mode=source_patch',
                default => 'edit_part mode=css_rule',
            };

            $target = array_merge([
                'label' => $this->label($element),
                'tag' => strtolower($element->tagName),
                'source_path' => $path,
                'id' => $id,
                'element_id' => $id,
                'compiled_selector' => $this->compiledElementSelector($sectionId, $id),
                'classes' => $classes,
                'style_ownership' => $styleOwnership,
                'recommended_write' => $recommendedWrite,
            ], $this->previewFields($element->textContent ?? ''));

            if ($hasInlineStyle) {
                $target['inline_style'] = trim($element->getAttribute('style'));
            }
            $target['element_styles'] = $this->elementStyleLines($elementStyleRules);
            if ($includeCss) {
                $target['generated_css_candidates'] = $generatedRules;
            }

            $targets[] = $target;
        });

        return $targets;
    }

    private function rootElement(string $html): ?\DOMElement
    {
        $doc = new \DOMDocument();
        $previousUseInternalErrors = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"><div id="__upb_target_root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);

        $container = $doc->getElementById('__upb_target_root');
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

    /**
     * @return list<string>
     */
    private function classList(\DOMElement $element): array
    {
        $classes = preg_split('/\s+/', trim($element->getAttribute('class'))) ?: [];

        return array_values(array_filter($classes, static fn (string $class): bool => $class !== ''));
    }

    private function countElements(\DOMElement $root, string $tag): int
    {
        $count = strtolower($root->tagName) === $tag ? 1 : 0;

        foreach ($root->getElementsByTagName($tag) as $node) {
            if ($node instanceof \DOMElement) {
                ++$count;
            }
        }

        return $count;
    }

    private function countTextTargets(\DOMElement $root): int
    {
        $count = $this->elementHasDirectText($root) && !$this->isNonContentTag($root) ? 1 : 0;

        foreach ($root->getElementsByTagName('*') as $node) {
            if (!$node instanceof \DOMElement || $this->isNonContentTag($node)) {
                continue;
            }

            if ($this->elementHasDirectText($node)) {
                ++$count;
            }
        }

        return $count;
    }

    private function elementHasDirectText(\DOMElement $element): bool
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMText && trim($child->wholeText) !== '') {
                return true;
            }
        }

        return false;
    }

    private function isNonContentTag(\DOMElement $element): bool
    {
        return in_array(strtolower($element->tagName), ['script', 'style', 'template'], true);
    }

    /**
     * @param list<string> $types
     * @return list<string>
     */
    private function normalizeTypes(array $types): array
    {
        $normalized = [];
        foreach ($types as $type) {
            $value = strtolower(trim($type));
            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return $normalized !== [] ? array_values(array_unique($normalized)) : ['all'];
    }

    /**
     * @param list<string> $types
     */
    private function wants(array $types, string $type): bool
    {
        return in_array('all', $types, true) || in_array($type, $types, true);
    }

    /**
     * @return list<array<string, string>>
     */
    private function textTargets(\DOMElement $root): array
    {
        $targets = [];
        $this->walkElements($root, function (\DOMElement $element, string $path) use (&$targets, $root): void {
            $tag = strtolower($element->tagName);
            if ($this->isNonContentTag($element) || in_array($tag, ['a', 'button', 'img'], true)) {
                return;
            }

            if (!$this->elementHasDirectText($element)) {
                return;
            }

            $targets[] = array_merge([
                'target_id' => 'text-' . (count($targets) + 1),
                'label' => $this->label($element),
                'tag' => $tag,
                'source_path' => $path,
                'recommended_tool' => 'edit_part mode=text',
            ], $this->previewFields($element->textContent ?? ''), $this->bindingOwnership($element, $root));
        });

        return $targets;
    }

    /**
     * @return list<array<string, string>>
     */
    private function elementTargets(\DOMElement $root, string $tag, string $targetType): array
    {
        $targets = [];
        $this->walkElements($root, function (\DOMElement $element, string $path) use (&$targets, $tag, $targetType, $root): void {
            if (strtolower($element->tagName) !== $tag) {
                return;
            }

            $target = [
                'target_id' => $targetType . '-' . (count($targets) + 1),
                'label' => $this->label($element),
                'tag' => $tag,
                'source_path' => $path,
                'recommended_tool' => $targetType === 'image' ? 'edit_part mode=image' : 'edit_part mode=link',
            ];

            if ($targetType === 'image') {
                $target['src'] = $element->getAttribute('src');
                $target['alt'] = $element->getAttribute('alt');
            } else {
                $target['href'] = $element->getAttribute('href');
                $target = array_merge($target, $this->previewFields($element->textContent ?? ''));
            }

            $target = array_merge($target, $this->bindingOwnership($element, $root));
            $targets[] = $target;
        });

        return $targets;
    }

    /**
     * @return list<array<string, string>>
     */
    private function buttonTargets(\DOMElement $root): array
    {
        $targets = [];
        $this->walkElements($root, function (\DOMElement $element, string $path) use (&$targets, $root): void {
            $tag = strtolower($element->tagName);
            $classes = $this->classList($element);
            $looksLikeButton = $tag === 'button'
                || $element->getAttribute('role') === 'button'
                || count(array_filter($classes, static fn (string $class): bool => str_contains(strtolower($class), 'button') || str_contains(strtolower($class), 'btn'))) > 0;

            if (!$looksLikeButton) {
                return;
            }

            $targets[] = array_merge([
                'target_id' => 'button-' . (count($targets) + 1),
                'label' => $this->label($element),
                'tag' => $tag,
                'source_path' => $path,
                'href' => $element->getAttribute('href'),
                'recommended_tool' => $tag === 'a' ? 'edit_part mode=link' : 'edit_part mode=text',
            ], $this->previewFields($element->textContent ?? ''), $this->bindingOwnership($element, $root));
        });

        return $targets;
    }

    /**
     * @return array<string, string>
     */
    private function bindingOwnership(\DOMElement $element, \DOMElement $root): array
    {
        $owner = $element;
        while ($owner instanceof \DOMElement) {
            $source = trim($owner->getAttribute('data-ai-dynamic'));
            if ($source !== '') {
                $bindingId = $source . ':' . DomElementPath::fromRoot($owner, $root);
                $contract = $this->bindings?->regionContractFor($source);
                if ($contract?->replaces === RegionReplaces::SelfElement) {
                    return $this->directSourceOwnership();
                }
                if ($contract?->replaces === RegionReplaces::HostAttribute) {
                    if (!$element->isSameNode($owner)) {
                        return $this->directSourceOwnership();
                    }

                    $tag = strtolower($element->tagName);
                    $ownedField = match ($tag) {
                        'a' => 'href',
                        'img' => 'src',
                        default => 'host_attribute',
                    };
                    $storedFields = match ($tag) {
                        'a' => 'text',
                        'img' => 'alt',
                        default => 'children',
                    };

                    return [
                        'binding_ownership' => 'field_owned',
                        'value_source' => 'mixed_source',
                        'binding_source' => $source,
                        'binding_id' => $bindingId,
                        'binding_owned_field' => $ownedField,
                        'stored_source_fields' => $storedFields,
                        'recommended_tool' => $tag === 'a'
                            ? 'edit_part mode=text'
                            : 'edit_part mode=source_patch after read_part include=source',
                    ];
                }

                return [
                    'binding_ownership' => 'binding_owned',
                    'value_source' => 'template_source',
                    'binding_source' => $source,
                    'binding_id' => $bindingId,
                    'recommended_tool' => 'manage_binding operation=update_template',
                ];
            }

            if ($owner->isSameNode($root)) {
                break;
            }

            $parent = $owner->parentNode;
            if (!$parent instanceof \DOMElement) {
                break;
            }
            $owner = $parent;
        }

        return $this->directSourceOwnership();
    }

    /** @return array{binding_ownership: string, value_source: string} */
    private function directSourceOwnership(): array
    {
        return [
            'binding_ownership' => 'direct_source',
            'value_source' => 'stored_source',
        ];
    }

    /**
     * @param callable(\DOMElement, string): void $visit
     */
    private function walkElements(\DOMElement $root, callable $visit): void
    {
        $this->walkElement($root, '0', $visit);
    }

    /**
     * @param callable(\DOMElement, string): void $visit
     */
    private function walkElement(\DOMElement $element, string $path, callable $visit): void
    {
        $visit($element, $path);

        // Agent reads and browser edits must return the same source positions.
        foreach (SourceTreeChildren::of($element) as $index => $child) {
            if ($child instanceof \DOMElement) {
                $this->walkElement($child, $path . '.' . $index, $visit);
            }
        }
    }

    private function locateElementByPath(\DOMElement $root, string $path): ?\DOMElement
    {
        $segments = explode('.', trim($path));
        if (($segments[0] ?? '') !== '0') {
            return null;
        }

        $current = $root;
        foreach (array_slice($segments, 1) as $segment) {
            if ($segment === '' || !ctype_digit($segment)) {
                return null;
            }

            $children = SourceTreeChildren::of($current);
            $child = $children[(int) $segment] ?? null;
            if (!$child instanceof \DOMElement) {
                return null;
            }

            $current = $child;
        }

        return $current;
    }

    private function label(\DOMElement $element): string
    {
        $label = strtolower($element->tagName);
        $id = trim($element->getAttribute('id'));
        if ($id !== '') {
            return $label . '#' . $id;
        }

        $classes = array_slice($this->classList($element), 0, 3);
        if ($classes !== []) {
            return $label . '.' . implode('.', $classes);
        }

        return $label;
    }

    /**
     * @return array<string, string>
     */
    private function previewFields(string $text): array
    {
        $normalized = trim((string) preg_replace('/\s+/', ' ', $text));
        $sourceBytes = strlen($normalized);
        if ($sourceBytes <= self::PREVIEW_LIMIT) {
            return ['text' => $normalized];
        }

        $prefix = substr($normalized, 0, self::PREVIEW_LIMIT - 3);
        while ($prefix !== '' && preg_match('//u', $prefix) !== 1) {
            $prefix = substr($prefix, 0, -1);
        }
        $preview = rtrim($prefix) . '...';

        return [
            'text' => $preview,
            'text_preview_truncated' => 'yes',
            'text_preview_bytes' => (string) strlen($preview),
            'text_source_bytes' => (string) $sourceBytes,
        ];
    }

    /**
     * @param list<string> $classes
     */
    /**
     * @return list<string>
     */
    private function generatedCssCandidates(string $css, string $id, array $classes): array
    {
        $candidates = [];
        if ($id !== '') {
            $candidates = array_merge($candidates, $this->cssRulesForSelector($css, '#' . $id));
        }
        foreach (array_slice($classes, 0, 3) as $class) {
            $candidates = array_merge($candidates, $this->cssRulesForSelector($css, '.' . $class));
        }

        return array_values(array_unique($candidates));
    }

    private function compiledElementSelector(int $sectionId, string $elementId): string
    {
        if ($sectionId <= 0 || $elementId === '') {
            return 'none';
        }

        $sectionSelector = '#upb-section-' . $sectionId;

        return $elementId === 'upb-section-' . $sectionId
            ? $sectionSelector
            : $sectionSelector . ' #' . $elementId;
    }

    /**
     * @param ElementStyleRule[] $rules
     * @return list<string>
     */
    private function elementStyleLines(array $rules): array
    {
        $lines = [];
        foreach ($rules as $rule) {
            foreach ($rule->declarations() as $property => $value) {
                $lines[] = sprintf('%s %s %s %s: %s', $rule->kind(), $rule->viewport(), $rule->state(), $property, $value);
            }
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function cssRulesForSelector(string $css, string $selector): array
    {
        if ($selector === '' || $selector === 'none') {
            return [];
        }

        $rules = [];
        $quotedSelector = preg_quote($selector, '/');
        if (preg_match_all('/([^{}]*' . $quotedSelector . '[^{]*)\{([^{}]*)\}/s', $css, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        foreach ($matches as $match) {
            $ruleSelector = trim((string) $match[1]);
            $block = trim((string) preg_replace('/\s+/', ' ', $match[2]));
            if ($ruleSelector !== '' && $block !== '') {
                $rules[] = $ruleSelector . ' { ' . $block . ' }';
            }
        }

        return $rules;
    }
}
